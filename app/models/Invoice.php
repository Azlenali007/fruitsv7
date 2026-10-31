<?php
/**
 * Invoice Model
 * N.A Fresh Fruits & Coconuts
 * Automatic, race-condition-safe, professional invoice generation and audit ledger.
 */

class Invoice {

    /**
     * Generate or retrieve existing invoice for a confirmed order
     *
     * @param array $order Order record from database
     * @param string|null $paymentRef Optional gateway payment ID / transaction reference
     * @return array ['success' => bool, 'invoice' => array, 'is_new' => bool]
     */
    public static function createForOrder(array $order, ?string $paymentRef = null): array {
        $pdo = Database::getConnection();
        $orderId = (int)$order['id'];

        // 1. Idempotency Check: if invoice already exists for this order, return it immediately
        $existing = self::getByOrderId($orderId);
        if ($existing) {
            return [
                'success' => true,
                'invoice' => $existing,
                'is_new' => false
            ];
        }

        $pdo->beginTransaction();
        try {
            // Re-check inside transaction with row lock
            $stmtCheck = $pdo->prepare("SELECT * FROM `invoices` WHERE `order_id` = ? FOR UPDATE");
            $stmtCheck->execute([$orderId]);
            $lockedExisting = $stmtCheck->fetch();
            if ($lockedExisting) {
                $pdo->commit();
                return [
                    'success' => true,
                    'invoice' => $lockedExisting,
                    'is_new' => false
                ];
            }

            // 2. Generate race-condition-safe sequential invoice number (NAF-{YYYY}-{000001})
            $currentYear = (int)date('Y');
            
            // Lock or create sequence row for the current year
            $stmtSeq = $pdo->prepare("SELECT `last_number` FROM `invoice_sequences` WHERE `sequence_year` = ? FOR UPDATE");
            $stmtSeq->execute([$currentYear]);
            $lastNum = $stmtSeq->fetchColumn();

            if ($lastNum === false) {
                // Initialize sequence for new year
                $nextNum = 1;
                $stmtInitSeq = $pdo->prepare("INSERT INTO `invoice_sequences` (`sequence_year`, `last_number`) VALUES (?, ?)");
                $stmtInitSeq->execute([$currentYear, $nextNum]);
            } else {
                $nextNum = (int)$lastNum + 1;
                $stmtUpSeq = $pdo->prepare("UPDATE `invoice_sequences` SET `last_number` = ?, `updated_at` = NOW() WHERE `sequence_year` = ?");
                $stmtUpSeq->execute([$nextNum, $currentYear]);
            }

            $invoiceNumber = sprintf("NAF-%d-%06d", $currentYear, $nextNum);

            // Double check uniqueness constraint
            $stmtCheckUnique = $pdo->prepare("SELECT COUNT(*) FROM `invoices` WHERE `invoice_number` = ?");
            $stmtCheckUnique->execute([$invoiceNumber]);
            if ((int)$stmtCheckUnique->fetchColumn() > 0) {
                // Fallback salt if manual collision occurs
                $invoiceNumber .= '-' . strtoupper(bin2hex(random_bytes(2)));
            }

            // 3. Determine Payment Method & Payment Status Snapshots
            $paymentMethod = $order['payment_method'] ?? 'cod';
            $orderPaymentStatus = $order['payment_status'] ?? 'pending';

            if ($paymentMethod === 'cod') {
                $displayMethod = 'Cash on Delivery';
                // COD is never marked as paid automatically at placement time
                $displayPaymentStatus = 'Payment Pending (Payable on Delivery)';
                $reference = $paymentRef ?: 'COD-' . ($order['order_number'] ?? $orderId);
            } else {
                $displayMethod = 'Razorpay Online';
                if ($orderPaymentStatus === 'paid' || $orderPaymentStatus === 'captured') {
                    $displayPaymentStatus = 'Paid';
                    $reference = $paymentRef ?: ($order['razorpay_payment_id'] ?? 'RZP-ONLINE');
                } else {
                    $displayPaymentStatus = 'Payment Pending';
                    $reference = $paymentRef ?: ($order['razorpay_order_id'] ?? 'RZP-PENDING');
                }
            }

            // 4. Insert Invoice Record
            $subtotal = (float)$order['subtotal'];
            $discount = (float)($order['discount_amount'] ?? 0.00);
            $deliveryCharge = (float)($order['delivery_charge'] ?? 0.00);
            $totalAmount = (float)$order['total_amount'];

            $stmtInsert = $pdo->prepare("
                INSERT INTO `invoices` (
                    `invoice_number`, `order_id`, `customer_id`,
                    `customer_name`, `customer_phone`, `customer_email`,
                    `delivery_address`, `delivery_area_name`,
                    `invoice_date`, `subtotal`, `discount_amount`, `delivery_charge`, `total_amount`,
                    `payment_method`, `payment_status`, `payment_reference`, `notes`, `created_at`
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmtInsert->execute([
                $invoiceNumber,
                $orderId,
                !empty($order['customer_id']) ? (int)$order['customer_id'] : null,
                $order['customer_name'] ?? 'Customer',
                $order['customer_phone'] ?? '',
                $order['customer_email'] ?? null,
                $order['delivery_address'] ?? '',
                $order['delivery_area_name'] ?? null,
                $subtotal,
                $discount,
                $deliveryCharge,
                $totalAmount,
                $displayMethod,
                $displayPaymentStatus,
                $reference,
                $order['notes'] ?? null
            ]);

            $invoiceId = (int)$pdo->lastInsertId();
            $pdo->commit();

            $newInvoice = self::getById($invoiceId);

            return [
                'success' => true,
                'invoice' => $newInvoice,
                'is_new' => true
            ];
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Invoice Generation Error for Order #{$orderId}: " . $e->getMessage());
            return [
                'success' => false,
                'error' => "Failed to generate invoice: " . $e->getMessage()
            ];
        }
    }

    /**
     * Get invoice by ID
     */
    public static function getById(int $id): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `invoices` WHERE `id` = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Get invoice by Order ID
     */
    public static function getByOrderId(int $orderId): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `invoices` WHERE `order_id` = ? LIMIT 1");
        $stmt->execute([$orderId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Get invoice by Invoice Number
     */
    public static function getByInvoiceNumber(string $invoiceNumber): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `invoices` WHERE `invoice_number` = ? LIMIT 1");
        $stmt->execute([trim($invoiceNumber)]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Update invoice payment status safely when gateway confirms payment
     */
    public static function updatePaymentStatus(int $orderId, string $status, ?string $paymentRef = null): bool {
        $pdo = Database::getConnection();
        $fields = ["`payment_status` = ?", "`updated_at` = NOW()"];
        $params = [$status];

        if (!empty($paymentRef)) {
            $fields[] = "`payment_reference` = ?";
            $params[] = $paymentRef;
        }

        $params[] = $orderId;
        $sql = "UPDATE `invoices` SET " . implode(', ', $fields) . " WHERE `order_id` = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Get all invoices for admin management with filters and pagination
     */
    public static function getAll(array $filters = [], int $limit = 20, int $offset = 0): array {
        $pdo = Database::getConnection();
        $where = [];
        $params = [];

        if (!empty($filters['search'])) {
            $term = '%' . trim($filters['search']) . '%';
            $where[] = "(`i`.`invoice_number` LIKE ? OR `o`.`order_number` LIKE ? OR `i`.`customer_name` LIKE ? OR `i`.`customer_phone` LIKE ?)";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($filters['payment_status']) && $filters['payment_status'] !== 'all') {
            $where[] = "`i`.`payment_status` LIKE ?";
            $params[] = '%' . trim($filters['payment_status']) . '%';
        }

        if (!empty($filters['date_from'])) {
            $where[] = "`i`.`invoice_date` >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where[] = "`i`.`invoice_date` <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $whereClause = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";

        $sql = "
            SELECT `i`.*, `o`.`order_number`, `o`.`order_status`, `o`.`tracking_hash`
            FROM `invoices` `i`
            LEFT JOIN `orders` `o` ON `i`.`order_id` = `o`.`id`
            {$whereClause}
            ORDER BY `i`.`id` DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Count invoices matching filters
     */
    public static function countAll(array $filters = []): int {
        $pdo = Database::getConnection();
        $where = [];
        $params = [];

        if (!empty($filters['search'])) {
            $term = '%' . trim($filters['search']) . '%';
            $where[] = "(`i`.`invoice_number` LIKE ? OR `o`.`order_number` LIKE ? OR `i`.`customer_name` LIKE ? OR `i`.`customer_phone` LIKE ?)";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($filters['payment_status']) && $filters['payment_status'] !== 'all') {
            $where[] = "`i`.`payment_status` LIKE ?";
            $params[] = '%' . trim($filters['payment_status']) . '%';
        }

        if (!empty($filters['date_from'])) {
            $where[] = "`i`.`invoice_date` >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where[] = "`i`.`invoice_date` <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $whereClause = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";

        $sql = "
            SELECT COUNT(*)
            FROM `invoices` `i`
            LEFT JOIN `orders` `o` ON `i`.`order_id` = `o`.`id`
            {$whereClause}
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }
}
