<?php
/**
 * Invoice Service
 * N.A Fresh Fruits & Coconuts
 * Provides professional A4 printable invoice views and pure PHP PDF generation.
 */

class InvoiceService {

    /**
     * Get complete invoice details including order and item snapshots
     */
    public static function getInvoiceFullData(int $invoiceId): ?array {
        $invoice = Invoice::getById($invoiceId);
        if (!$invoice) {
            return null;
        }

        $order = Order::getById((int)$invoice['order_id']);
        if (!$order) {
            return null;
        }

        $items = Order::getItems((int)$order['id']);

        return [
            'invoice' => $invoice,
            'order' => $order,
            'items' => $items
        ];
    }

    /**
     * Get complete invoice details by order ID
     */
    public static function getInvoiceFullDataByOrder(int $orderId): ?array {
        $invoice = Invoice::getByOrderId($orderId);
        $order = Order::getById($orderId);
        if (!$order) {
            return null;
        }

        // If invoice doesn't exist yet for this order, create it if eligible
        if (!$invoice) {
            $createRes = Invoice::createForOrder($order);
            if ($createRes['success']) {
                $invoice = $createRes['invoice'];
            }
        }

        if (!$invoice) {
            return null;
        }

        $items = Order::getItems($orderId);

        return [
            'invoice' => $invoice,
            'order' => $order,
            'items' => $items
        ];
    }

    /**
     * Resolve full invoice, order and items data from order or invoice record
     */
    public static function resolveFullData($orderOrInvoice): ?array {
        if (!$orderOrInvoice || !is_array($orderOrInvoice)) {
            return null;
        }

        if (isset($orderOrInvoice['invoice_number'])) {
            $invoice = $orderOrInvoice;
            $order = Order::getById((int)$invoice['order_id']);
            $items = $order ? Order::getItems((int)$order['id']) : [];
            return ['invoice' => $invoice, 'order' => $order ?: [], 'items' => $items];
        }

        // It is an order record
        $order = $orderOrInvoice;
        $orderId = (int)$order['id'];
        $invoice = Invoice::getByOrderId($orderId);
        if (!$invoice) {
            $res = Invoice::createForOrder($order);
            $invoice = $res['invoice'] ?? null;
        }
        $items = Order::getItems($orderId);
        return ['invoice' => $invoice ?: [], 'order' => $order, 'items' => $items];
    }

    /**
     * Render printable, beautiful A4 HTML invoice
     */
    public static function renderHtml($invoiceOrOrder, ?array $order = null, ?array $items = null, bool $forDownload = false): string {
        if ($order === null) {
            $resolved = self::resolveFullData($invoiceOrOrder);
            if (!$resolved || empty($resolved['invoice'])) {
                throw new Exception("Invoice could not be resolved for order.");
            }
            $invoice = $resolved['invoice'];
            $order = $resolved['order'];
            $items = $resolved['items'];
        } else {
            $invoice = $invoiceOrOrder;
            $items = $items ?? [];
        }

        $storeName = Setting::get('store_name', 'N.A Fresh Fruits & Coconuts');
        $storeTagline = Setting::get('store_tagline', 'Pure • Natural • Refreshing Fresh Tender Coconut Water');
        $storePhone = Setting::get('store_phone', '+91 98765 43210');
        $storeEmail = Setting::get('store_email', 'contact@nafruitsandcoconuts.com');
        $storeAddress = Setting::get('store_address', '123, Green Park, Near Main Road, Bhopal, Madhya Pradesh - 462016');

        $invNumber = $invoice['invoice_number'] ?? 'NAF-BILL';
        $invDate = !empty($invoice['invoice_date']) ? date('d M Y, h:i A', strtotime($invoice['invoice_date'])) : date('d M Y, h:i A');
        $orderNumber = $order['order_number'] ?? ('#' . $order['id']);
        $custName = $invoice['customer_name'];
        $custPhone = $invoice['customer_phone'];
        $custEmail = $invoice['customer_email'] ?: '-';
        $address = $invoice['delivery_address'] ?: 'Customer address on record';
        $areaName = $invoice['delivery_area_name'] ?: 'Jaora Area';

        $subtotal = number_format((float)$invoice['subtotal'], 2);
        $discount = (float)$invoice['discount_amount'];
        $deliveryCharge = (float)$invoice['delivery_charge'];
        $total = number_format((float)$invoice['total_amount'], 2);

        $payMethod = $invoice['payment_method'];
        $payStatus = $invoice['payment_status'];
        $isPaid = str_contains(strtolower($payStatus), 'paid') && !str_contains(strtolower($payStatus), 'pending');
        $payRef = $invoice['payment_reference'] ?: '-';

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Invoice <?= htmlspecialchars($invNumber) ?> - <?= htmlspecialchars($storeName) ?></title>
            <link rel="icon" href="/favicon.ico">
            <style>
                @page {
                    size: A4;
                    margin: 15mm;
                }
                * {
                    box-sizing: border-box;
                    margin: 0;
                    padding: 0;
                }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                    color: #1f2937;
                    background: #f3f4f6;
                    padding: 24px;
                    font-size: 13px;
                    line-height: 1.5;
                }
                .invoice-wrapper {
                    max-width: 800px;
                    margin: 0 auto;
                    background: #ffffff;
                    border-radius: 16px;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
                    overflow: hidden;
                    border: 1px solid #e5e7eb;
                }
                .top-actions {
                    max-width: 800px;
                    margin: 0 auto 16px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .btn {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    padding: 9px 18px;
                    font-size: 13px;
                    font-weight: 700;
                    border-radius: 10px;
                    text-decoration: none;
                    cursor: pointer;
                    border: none;
                    transition: all 0.2s;
                }
                .btn-primary {
                    background: #235d3a;
                    color: #ffffff;
                }
                .btn-primary:hover {
                    background: #1b4a2e;
                }
                .btn-secondary {
                    background: #ffffff;
                    color: #374151;
                    border: 1px solid #d1d5db;
                }
                .btn-secondary:hover {
                    background: #f9fafb;
                }
                .header-bar {
                    background: linear-gradient(135deg, #235d3a 0%, #153e24 100%);
                    color: #ffffff;
                    padding: 32px 36px;
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                }
                .brand-title {
                    font-size: 24px;
                    font-weight: 800;
                    letter-spacing: -0.5px;
                    margin-bottom: 4px;
                }
                .brand-tagline {
                    font-size: 12px;
                    color: #d1fae5;
                    font-weight: 500;
                    margin-bottom: 12px;
                }
                .brand-contact {
                    font-size: 11px;
                    color: #e5e7eb;
                    line-height: 1.6;
                }
                .invoice-meta {
                    text-align: right;
                }
                .invoice-badge {
                    display: inline-block;
                    background: rgba(255, 255, 255, 0.2);
                    padding: 4px 12px;
                    border-radius: 20px;
                    font-size: 11px;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                    margin-bottom: 8px;
                }
                .invoice-num {
                    font-size: 20px;
                    font-weight: 800;
                    font-family: monospace;
                    letter-spacing: 0.5px;
                }
                .invoice-date {
                    font-size: 12px;
                    color: #d1fae5;
                    margin-top: 4px;
                }
                .body-content {
                    padding: 32px 36px;
                }
                .info-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 24px;
                    padding-bottom: 24px;
                    border-bottom: 1px solid #f3f4f6;
                    margin-bottom: 24px;
                }
                .info-block h4 {
                    font-size: 11px;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                    color: #9ca3af;
                    margin-bottom: 8px;
                }
                .info-block p {
                    font-size: 13px;
                    color: #374151;
                    line-height: 1.6;
                }
                .info-block strong {
                    color: #111827;
                    font-weight: 700;
                }
                .table-container {
                    margin-bottom: 24px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    text-align: left;
                }
                th {
                    background: #f9fafb;
                    color: #4b5563;
                    font-size: 11px;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    padding: 12px 16px;
                    border-bottom: 1px solid #e5e7eb;
                }
                td {
                    padding: 14px 16px;
                    border-bottom: 1px solid #f3f4f6;
                    color: #374151;
                }
                .text-right {
                    text-align: right;
                }
                .text-center {
                    text-align: center;
                }
                .totals-section {
                    display: flex;
                    justify-content: flex-end;
                    margin-bottom: 24px;
                }
                .totals-table {
                    width: 320px;
                }
                .totals-table tr td {
                    padding: 8px 12px;
                    border: none;
                }
                .totals-table .total-row td {
                    border-top: 2px solid #235d3a;
                    font-size: 16px;
                    font-weight: 800;
                    color: #235d3a;
                    padding-top: 12px;
                }
                .payment-box {
                    background: #f9fafb;
                    border-radius: 12px;
                    padding: 16px 20px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    border: 1px solid #e5e7eb;
                    margin-bottom: 24px;
                }
                .status-pill {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    padding: 5px 12px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: 700;
                }
                .status-paid {
                    background: #dcfce7;
                    color: #15803d;
                    border: 1px solid #bbf7d0;
                }
                .status-pending {
                    background: #fef3c7;
                    color: #b45309;
                    border: 1px solid #fde68a;
                }
                .footer-notes {
                    border-top: 1px solid #f3f4f6;
                    padding-top: 20px;
                    text-align: center;
                    color: #6b7280;
                    font-size: 12px;
                }
                .footer-notes strong {
                    color: #235d3a;
                }
                @media print {
                    body {
                        background: #ffffff;
                        padding: 0;
                    }
                    .top-actions {
                        display: none !important;
                    }
                    .invoice-wrapper {
                        box-shadow: none;
                        border: none;
                        max-width: 100%;
                    }
                }
            </style>
        </head>
        <body>

            <div class="top-actions">
                <a href="javascript:history.back()" class="btn btn-secondary">
                    &larr; Back
                </a>
                <div style="display: flex; gap: 8px;">
                    <a href="?download=pdf" class="btn btn-primary">
                        📥 Download PDF
                    </a>
                    <button onclick="window.print()" class="btn btn-secondary">
                        🖨️ Print
                    </button>
                </div>
            </div>

            <div class="invoice-wrapper">
                <!-- Header with Brand & Meta -->
                <div class="header-bar">
                    <div>
                        <div class="brand-title"><?= htmlspecialchars($storeName) ?></div>
                        <div class="brand-tagline"><?= htmlspecialchars($storeTagline) ?></div>
                        <div class="brand-contact">
                            <?= htmlspecialchars($storeAddress) ?><br>
                            Phone: <?= htmlspecialchars($storePhone) ?> &bull; Email: <?= htmlspecialchars($storeEmail) ?>
                        </div>
                    </div>
                    <div class="invoice-meta">
                        <div class="invoice-badge">Tax Invoice / Bill</div>
                        <div class="invoice-num"><?= htmlspecialchars($invNumber) ?></div>
                        <div class="invoice-date">Date: <?= htmlspecialchars($invDate) ?></div>
                        <div class="invoice-date">Order: <strong>#<?= htmlspecialchars($orderNumber) ?></strong></div>
                    </div>
                </div>

                <div class="body-content">
                    <!-- Customer & Delivery Info -->
                    <div class="info-grid">
                        <div class="info-block">
                            <h4>Billed & Delivered To</h4>
                            <p>
                                <strong><?= htmlspecialchars($custName) ?></strong><br>
                                Phone: <?= htmlspecialchars($custPhone) ?><br>
                                Email: <?= htmlspecialchars($custEmail) ?>
                            </p>
                        </div>
                        <div class="info-block">
                            <h4>Delivery Address</h4>
                            <p>
                                <?= nl2br(htmlspecialchars($address)) ?><br>
                                <strong>Locality:</strong> <?= htmlspecialchars($areaName) ?>
                            </p>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 50px;" class="text-center">#</th>
                                    <th>Item Description</th>
                                    <th class="text-right" style="width: 120px;">Unit Price</th>
                                    <th class="text-center" style="width: 90px;">Qty</th>
                                    <th class="text-right" style="width: 130px;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $idx = 1; foreach ($items as $item): ?>
                                <tr>
                                    <td class="text-center" style="color: #9ca3af;"><?= $idx++ ?></td>
                                    <td>
                                        <strong style="color: #111827;"><?= htmlspecialchars($item['product_name']) ?></strong>
                                        <div style="font-size: 11px; color: #6b7280;">100% Natural Farm Fresh Produce</div>
                                    </td>
                                    <td class="text-right">₹<?= number_format((float)$item['unit_price'], 2) ?></td>
                                    <td class="text-center"><strong><?= (int)$item['quantity'] ?></strong></td>
                                    <td class="text-right" style="font-weight: 700;">₹<?= number_format((float)$item['subtotal'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Summary & Totals -->
                    <div class="totals-section">
                        <table class="totals-table">
                            <tr>
                                <td>Items Subtotal:</td>
                                <td class="text-right font-medium">₹<?= $subtotal ?></td>
                            </tr>
                            <?php if ($discount > 0): ?>
                            <tr style="color: #15803d;">
                                <td>Coupon Discount (<?= htmlspecialchars($order['coupon_code'] ?? 'PROMO') ?>):</td>
                                <td class="text-right font-bold">-₹<?= number_format($discount, 2) ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td>Delivery Charges:</td>
                                <td class="text-right font-medium">
                                    <?php if ($deliveryCharge <= 0): ?>
                                        <span style="color: #15803d; font-weight: 700;">FREE</span>
                                    <?php else: ?>
                                        ₹<?= number_format($deliveryCharge, 2) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr class="total-row">
                                <td>Total Amount:</td>
                                <td class="text-right">₹<?= $total ?></td>
                            </tr>
                        </table>
                    </div>

                    <!-- Payment Information Bar -->
                    <div class="payment-box">
                        <div>
                            <div style="font-size: 11px; color: #6b7280; text-transform: uppercase; font-weight: 700; margin-bottom: 2px;">
                                Payment Details
                            </div>
                            <div style="font-size: 13px; font-weight: 700; color: #111827;">
                                <?= htmlspecialchars($payMethod) ?>
                            </div>
                            <div style="font-size: 11px; color: #6b7280; margin-top: 2px;">
                                Reference: <code style="font-family: monospace;"><?= htmlspecialchars($payRef) ?></code>
                            </div>
                        </div>
                        <div>
                            <span class="status-pill <?= $isPaid ? 'status-paid' : 'status-pending' ?>">
                                <?= $isPaid ? '✓' : '⏱' ?> <?= htmlspecialchars($payStatus) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Footer Notes -->
                    <div class="footer-notes">
                        <p>Thank you for choosing <strong><?= htmlspecialchars($storeName) ?></strong>!</p>
                        <p style="margin-top: 4px; font-size: 11px; color: #9ca3af;">
                            For customer care or delivery assistance, please reach out to us at <?= htmlspecialchars($storePhone) ?>.
                        </p>
                    </div>
                </div>
            </div>

        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate pure native PDF 1.4 bytecode without external libraries
     * Returns raw PDF binary string with professional A4 layout, clean wrapping, and accounting alignment
     */
    public static function generatePdfString($invoiceOrOrder, ?array $order = null, ?array $items = null): string {
        if ($order === null) {
            $resolved = self::resolveFullData($invoiceOrOrder);
            if (!$resolved || empty($resolved['invoice'])) {
                throw new Exception("Invoice could not be resolved for order.");
            }
            $invoice = $resolved['invoice'];
            $order = $resolved['order'];
            $items = $resolved['items'];
        } else {
            $invoice = $invoiceOrOrder;
            $items = $items ?? [];
        }

        $invNumber = $invoice['invoice_number'] ?? 'NAF-BILL';
        $storeName = Setting::get('store_name', 'N.A Fresh Fruits & Coconuts');
        $storeTagline = Setting::get('store_tagline', 'Pure • Natural • Refreshing Fresh Tender Coconut Water');
        $storePhone = Setting::get('store_phone', '+91 98765 43210');
        $storeEmail = Setting::get('store_email', 'contact@nafruitsandcoconuts.com');
        $storeAddress = Setting::get('store_address', 'Station Road, Jaora, Madhya Pradesh - 457226');

        $invDate = !empty($invoice['invoice_date']) ? date('d M Y, h:i A', strtotime($invoice['invoice_date'])) : date('d M Y, h:i A');
        $orderNumber = $order['order_number'] ?? ('#' . ($order['id'] ?? ''));
        $custName = $invoice['customer_name'] ?? 'Valued Customer';
        $custPhone = $invoice['customer_phone'] ?? '';
        $custEmail = !empty($invoice['customer_email']) ? $invoice['customer_email'] : '';
        $address = !empty($invoice['delivery_address']) ? $invoice['delivery_address'] : 'Customer address on record';
        $areaName = !empty($invoice['delivery_area_name']) ? $invoice['delivery_area_name'] : 'Jaora Service Zone';

        $subtotalVal = (float)$invoice['subtotal'];
        $discountVal = (float)$invoice['discount_amount'];
        $deliveryVal = (float)$invoice['delivery_charge'];
        $totalVal = (float)$invoice['total_amount'];

        $payMethod = $invoice['payment_method'] ?? 'Cash on Delivery';
        $payStatus = $invoice['payment_status'] ?? 'Payment Pending';
        $isPaid = str_contains(strtolower($payStatus), 'paid') && !str_contains(strtolower($payStatus), 'pending');
        $payRef = !empty($invoice['payment_reference']) ? $invoice['payment_reference'] : '-';

        // Layout Geometry
        $pageW = 595.28;
        $pageH = 841.89;
        $mLeft = 36.0;
        $mRight = 559.28;
        $cWidth = 523.28;

        // Pages collector: array of array of PDF stream commands
        $pages = [];
        $currentPageOps = [];

        // Helper to add header on a page
        $renderHeader = function(int $pageNum, int $totalEstPages = 1) use (
            &$currentPageOps, $mLeft, $cWidth, $storeName, $storeTagline, $storeAddress, $storePhone,
            $invNumber, $invDate, $orderNumber
        ) {
            $hY = 728.0;
            $hH = 78.0;

            // Emerald Header Background
            $currentPageOps[] = "q 0.137 0.365 0.227 rg {$mLeft} {$hY} {$cWidth} {$hH} re f Q";

            // Left: Store Branding
            $currentPageOps[] = "BT /F2 15 Tf 1 1 1 rg 50 782 Td (" . self::escapePdfText($storeName) . ") Tj ET";
            $currentPageOps[] = "BT /F1 8 Tf 0.78 0.93 0.82 rg 50 767 Td (" . self::escapePdfText($storeTagline) . ") Tj ET";
            $currentPageOps[] = "BT /F1 7.5 Tf 0.90 0.94 0.91 rg 50 748 Td (" . self::escapePdfText($storeAddress . " | Tel: " . $storePhone) . ") Tj ET";

            // Right: Invoice Meta
            $currentPageOps[] = "BT /F2 12 Tf 1 1 1 rg 380 782 Td (TAX INVOICE / BILL) Tj ET";
            $currentPageOps[] = "BT /F2 9.5 Tf 1 1 1 rg 380 766 Td (Invoice: " . self::escapePdfText($invNumber) . ") Tj ET";
            $currentPageOps[] = "BT /F1 7.5 Tf 0.78 0.93 0.82 rg 380 752 Td (Date: " . self::escapePdfText($invDate) . ") Tj ET";
            $currentPageOps[] = "BT /F1 7.5 Tf 0.78 0.93 0.82 rg 380 740 Td (Order Ref: #" . self::escapePdfText($orderNumber) . ") Tj ET";
        };

        // Render Page 1 Header
        $renderHeader(1);

        // Page 1 Customer & Delivery Cards
        $cardY = 642.0;
        $cardH = 74.0;
        $currentPageOps[] = "q 0.976 0.980 0.984 rg {$mLeft} {$cardY} {$cWidth} {$cardH} re f Q";
        $currentPageOps[] = "q 0.88 0.90 0.89 RG 0.8 w {$mLeft} {$cardY} {$cWidth} {$cardH} re s Q";
        // Divider line between Billed To and Deliver To
        $cardTop = $cardY + $cardH;
        $currentPageOps[] = "q 0.88 0.90 0.89 RG 0.6 w 295 {$cardY} m 295 {$cardTop} l S Q";

        // Billed To Column
        $currentPageOps[] = "BT /F2 7.5 Tf 0.45 0.50 0.48 rg 50 698 Td (BILLED TO / CUSTOMER) Tj ET";
        $currentPageOps[] = "BT /F2 10 Tf 0.12 0.16 0.22 rg 50 683 Td (" . self::escapePdfText($custName) . ") Tj ET";
        $currentPageOps[] = "BT /F1 8.5 Tf 0.30 0.35 0.32 rg 50 669 Td (Phone: " . self::escapePdfText($custPhone) . ") Tj ET";
        if (!empty($custEmail)) {
            $currentPageOps[] = "BT /F1 7.5 Tf 0.45 0.50 0.48 rg 50 656 Td (Email: " . self::escapePdfText($custEmail) . ") Tj ET";
        }

        // Deliver To Column
        $currentPageOps[] = "BT /F2 7.5 Tf 0.45 0.50 0.48 rg 310 698 Td (DELIVERY DESTINATION) Tj ET";
        $currentPageOps[] = "BT /F2 9.5 Tf 0.12 0.16 0.22 rg 310 683 Td (" . self::escapePdfText($areaName) . ") Tj ET";
        $addrLines = self::wrapText($address, 230.0, 7.5);
        $addrY = 669.0;
        foreach (array_slice($addrLines, 0, 2) as $al) {
            $currentPageOps[] = "BT /F1 7.5 Tf 0.35 0.40 0.38 rg 310 {$addrY} Td (" . self::escapePdfText($al) . ") Tj ET";
            $addrY -= 11.0;
        }

        // Table Header
        $tableHeaderY = 608.0;
        $thH = 22.0;
        $renderTableHeader = function($tY) use (&$currentPageOps, $mLeft, $cWidth, $thH) {
            $boxY = $tY - $thH;
            $currentPageOps[] = "q 0.92 0.96 0.93 rg {$mLeft} {$boxY} {$cWidth} {$thH} re f Q";
            $currentPageOps[] = "q 0.78 0.86 0.80 RG 0.8 w {$mLeft} {$boxY} {$cWidth} {$thH} re s Q";

            $textY = $boxY + 7.0;
            $currentPageOps[] = "BT /F2 8.5 Tf 0.137 0.365 0.227 rg 46 {$textY} Td (#) Tj ET";
            $currentPageOps[] = "BT /F2 8.5 Tf 0.137 0.365 0.227 rg 72 {$textY} Td (ITEM DESCRIPTION) Tj ET";
            $currentPageOps[] = "BT /F2 8.5 Tf 0.137 0.365 0.227 rg 330 {$textY} Td (UNIT PRICE) Tj ET";
            $currentPageOps[] = "BT /F2 8.5 Tf 0.137 0.365 0.227 rg 412 {$textY} Td (QTY) Tj ET";
            $currentPageOps[] = "BT /F2 8.5 Tf 0.137 0.365 0.227 rg 495 {$textY} Td (AMOUNT) Tj ET";
        };

        $renderTableHeader($tableHeaderY);
        $currY = $tableHeaderY - $thH;

        // Render Table Rows with Dynamic Wrapping and Height
        $idx = 1;
        foreach ($items as $item) {
            $pName = trim((string)$item['product_name']);
            $uPrice = (float)$item['unit_price'];
            $qty = (int)$item['quantity'];
            $lineSubtotal = (float)$item['subtotal'];

            // Wrap product description cleanly to 245pt column
            $nameLines = self::wrapText($pName, 245.0, 8.5, true);
            $lineCount = count($nameLines);
            $rowH = max(24.0, ($lineCount * 12.0) + 10.0);

            // Check if page break needed (leave room for summary if last item)
            if (($currY - $rowH) < 140.0) {
                // Add page footer to current page
                $currentPageOps[] = "BT /F1 7.5 Tf 0.5 0.5 0.5 rg 260 25 Td (Continued on next page...) Tj ET";
                $pages[] = $currentPageOps;
                $currentPageOps = [];

                // Start new page
                $renderHeader(count($pages) + 1);
                $currY = 710.0;
                $renderTableHeader($currY);
                $currY -= $thH;
            }

            $rowBoxY = $currY - $rowH;

            // Row background and border
            $bgFill = ($idx % 2 === 0) ? "0.985 0.990 0.985 rg" : "1 1 1 rg";
            $currentPageOps[] = "q {$bgFill} {$mLeft} {$rowBoxY} {$cWidth} {$rowH} re f Q";
            $currentPageOps[] = "q 0.90 0.91 0.90 RG 0.5 w {$mLeft} {$rowBoxY} {$cWidth} {$rowH} re s Q";

            // Row vertical text baseline
            $topTextY = $currY - 15.0;

            // # Index
            $currentPageOps[] = "BT /F1 8.5 Tf 0.45 0.50 0.48 rg 48 {$topTextY} Td ({$idx}) Tj ET";

            // Product Name (multi-line)
            $pTextY = $topTextY;
            foreach ($nameLines as $nl) {
                $currentPageOps[] = "BT /F2 8.5 Tf 0.12 0.16 0.22 rg 72 {$pTextY} Td (" . self::escapePdfText($nl) . ") Tj ET";
                $pTextY -= 12.0;
            }

            // Unit Price (Right Aligned in column 330-385)
            $uPriceStr = "Rs. " . number_format($uPrice, 2);
            $currentPageOps[] = "BT /F1 8.5 Tf 0.25 0.30 0.28 rg 335 {$topTextY} Td (" . self::escapePdfText($uPriceStr) . ") Tj ET";

            // Quantity (Centered in column 400-440)
            $currentPageOps[] = "BT /F2 8.5 Tf 0.12 0.16 0.22 rg 420 {$topTextY} Td ({$qty}) Tj ET";

            // Line Amount (Right Aligned in column 450-545)
            $amtStr = "Rs. " . number_format($lineSubtotal, 2);
            $currentPageOps[] = "BT /F2 9.0 Tf 0.12 0.16 0.22 rg 488 {$topTextY} Td (" . self::escapePdfText($amtStr) . ") Tj ET";

            $currY -= $rowH;
            $idx++;
        }

        // Check if room for summary block (needs ~110 pt)
        if (($currY - 110.0) < 60.0) {
            $pages[] = $currentPageOps;
            $currentPageOps = [];
            $renderHeader(count($pages) + 1);
            $currY = 700.0;
        }

        // Summary and Payment Section
        $secY = $currY - 12.0;

        // Left Box: Payment Details Box (X: 36, W: 260)
        $payBoxY = $secY - 86.0;
        $payBoxH = 86.0;
        $currentPageOps[] = "q 0.976 0.980 0.984 rg {$mLeft} {$payBoxY} 265 {$payBoxH} re f Q";
        $currentPageOps[] = "q 0.88 0.90 0.89 RG 0.8 w {$mLeft} {$payBoxY} 265 {$payBoxH} re s Q";

        $currentPageOps[] = "BT /F2 7.5 Tf 0.45 0.50 0.48 rg 48 " . ($payBoxY + 68) . " Td (PAYMENT INFORMATION) Tj ET";
        $currentPageOps[] = "BT /F2 8.5 Tf 0.12 0.16 0.22 rg 48 " . ($payBoxY + 52) . " Td (Method: " . self::escapePdfText($payMethod) . ") Tj ET";
        $currentPageOps[] = "BT /F1 7.5 Tf 0.40 0.45 0.42 rg 48 " . ($payBoxY + 38) . " Td (Reference: " . self::escapePdfText(substr($payRef, 0, 32)) . ") Tj ET";

        // Status Badge Pill
        $badgeBg = $isPaid ? "0.85 0.96 0.88 rg" : "0.99 0.95 0.86 rg";
        $badgeBorder = $isPaid ? "0.35 0.75 0.45 RG" : "0.85 0.60 0.20 RG";
        $badgeTextCol = $isPaid ? "0.08 0.50 0.20 rg" : "0.70 0.35 0.05 rg";
        $currentPageOps[] = "q {$badgeBg} 48 " . ($payBoxY + 12) . " 240 18 re f Q";
        $currentPageOps[] = "q {$badgeBorder} 0.8 w 48 " . ($payBoxY + 12) . " 240 18 re s Q";
        $statusLabel = $isPaid ? "PAID - Verified Online Transaction" : "PAYMENT PENDING (Payable on Delivery)";
        $currentPageOps[] = "BT /F2 7.5 Tf {$badgeTextCol} 56 " . ($payBoxY + 17) . " Td (" . self::escapePdfText($statusLabel) . ") Tj ET";

        // Right Box: Financial Totals Summary (X: 315 to 559.28)
        $totY = $secY;
        $currentPageOps[] = "BT /F1 8.5 Tf 0.40 0.45 0.42 rg 330 {$totY} Td (Items Subtotal:) Tj ET";
        $currentPageOps[] = "BT /F1 8.5 Tf 0.12 0.16 0.22 rg 488 {$totY} Td (Rs. " . number_format($subtotalVal, 2) . ") Tj ET";

        if ($discountVal > 0) {
            $totY -= 15.0;
            $currentPageOps[] = "BT /F1 8.5 Tf 0.137 0.50 0.25 rg 330 {$totY} Td (Coupon Discount:) Tj ET";
            $currentPageOps[] = "BT /F2 8.5 Tf 0.137 0.50 0.25 rg 485 {$totY} Td (-Rs. " . number_format($discountVal, 2) . ") Tj ET";
        }

        $totY -= 15.0;
        $delText = $deliveryVal <= 0 ? "FREE" : "Rs. " . number_format($deliveryVal, 2);
        $currentPageOps[] = "BT /F1 8.5 Tf 0.40 0.45 0.42 rg 330 {$totY} Td (Delivery Charge:) Tj ET";
        $currentPageOps[] = "BT /F1 8.5 Tf 0.12 0.16 0.22 rg 488 {$totY} Td ({$delText}) Tj ET";

        $totY -= 14.0;
        // Final divider bar in emerald
        $currentPageOps[] = "q 0.137 0.365 0.227 RG 1.2 w 320 {$totY} m 559 {$totY} l S Q";

        $totY -= 16.0;
        $currentPageOps[] = "BT /F2 11 Tf 0.137 0.365 0.227 rg 330 {$totY} Td (FINAL PAYABLE:) Tj ET";
        $currentPageOps[] = "BT /F2 12 Tf 0.137 0.365 0.227 rg 478 {$totY} Td (Rs. " . number_format($totalVal, 2) . ") Tj ET";

        // Footer at bottom of page
        $footY = 45.0;
        $currentPageOps[] = "q 0.88 0.90 0.89 RG 0.5 w {$mLeft} {$footY} m {$mRight} {$footY} l S Q";
        $currentPageOps[] = "BT /F2 8.5 Tf 0.137 0.365 0.227 rg 170 " . ($footY - 14) . " Td (Thank you for choosing N.A Fresh Fruits & Coconuts!) Tj ET";
        $currentPageOps[] = "BT /F1 7.5 Tf 0.45 0.50 0.48 rg 140 " . ($footY - 25) . " Td (For queries or delivery updates, call " . self::escapePdfText($storePhone) . " or email " . self::escapePdfText($storeEmail) . ") Tj ET";

        $pages[] = $currentPageOps;
        $totalPages = count($pages);

        // Build valid multi-page PDF 1.4 Object Graph
        $objects = [];
        $objIdx = 1;

        // Object 1: Catalog
        $catalogObj = $objIdx++;
        // Object 2: Pages
        $pagesObj = $objIdx++;

        $pageObjIds = [];
        $contentObjIds = [];

        for ($p = 0; $p < $totalPages; $p++) {
            $pageObjIds[] = $objIdx++;
            $contentObjIds[] = $objIdx++;
        }

        $fontF1 = $objIdx++;
        $fontF2 = $objIdx++;

        // Fill objects
        $kidsStr = implode(' ', array_map(fn($id) => "{$id} 0 R", $pageObjIds));
        $objects[$catalogObj] = "<< /Type /Catalog /Pages {$pagesObj} 0 R >>";
        $objects[$pagesObj] = "<< /Type /Pages /Kids [{$kidsStr}] /Count {$totalPages} >>";

        for ($p = 0; $p < $totalPages; $p++) {
            $pageId = $pageObjIds[$p];
            $contentId = $contentObjIds[$p];

            // Add page numbering to each page stream
            $pNum = $p + 1;
            $pStreamOps = $pages[$p];
            $pStreamOps[] = "BT /F1 7.5 Tf 0.55 0.58 0.55 rg 520 20 Td (Page {$pNum} of {$totalPages}) Tj ET";

            $streamBody = implode("\n", $pStreamOps);
            $streamLen = strlen($streamBody);

            $objects[$pageId] = "<< /Type /Page /Parent {$pagesObj} 0 R /MediaBox [0 0 595.28 841.89] /Contents {$contentId} 0 R /Resources << /Font << /F1 {$fontF1} 0 R /F2 {$fontF2} 0 R >> >> >>";
            $objects[$contentId] = "<< /Length {$streamLen} >>\nstream\n{$streamBody}\nendstream";
        }

        $objects[$fontF1] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objects[$fontF2] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        // Assemble binary PDF output with precise byte offsets
        $output = "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n";
        $offsets = [];

        $totalObjCount = $objIdx - 1;
        for ($i = 1; $i <= $totalObjCount; $i++) {
            $offsets[$i] = strlen($output);
            $output .= "{$i} 0 obj\n" . $objects[$i] . "\nendobj\n";
        }

        $xrefOffset = strlen($output);
        $output .= "xref\n0 " . ($totalObjCount + 1) . "\n";
        $output .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $totalObjCount; $i++) {
            $output .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $output .= "trailer\n<< /Size " . ($totalObjCount + 1) . " /Root {$catalogObj} 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $output;
    }

    /**
     * Wrap text cleanly into multiple lines based on maximum column width
     */
    public static function wrapText(string $text, float $maxWidth, float $fontSize, bool $bold = false): array {
        $avgCharW = $fontSize * ($bold ? 0.53 : 0.48);
        $maxChars = max(10, (int)floor($maxWidth / $avgCharW));

        $cleanText = str_replace(["\r\n", "\r", "\n"], ' ', $text);
        $words = preg_split('/\s+/', trim($cleanText));
        $lines = [];
        $cur = '';

        foreach ($words as $w) {
            if ($w === '') continue;
            $test = $cur === '' ? $w : ($cur . ' ' . $w);
            if (strlen($test) <= $maxChars) {
                $cur = $test;
            } else {
                if ($cur !== '') {
                    $lines[] = $cur;
                }
                while (strlen($w) > $maxChars) {
                    $lines[] = substr($w, 0, $maxChars);
                    $w = substr($w, $maxChars);
                }
                $cur = $w;
            }
        }
        if ($cur !== '') {
            $lines[] = $cur;
        }

        return !empty($lines) ? $lines : [''];
    }

    /**
     * Stream pure native PDF 1.4 bytecode without external libraries
     * Directly serves PDF attachment to browser
     */
    public static function streamPdf($invoiceOrOrder, ?array $order = null, ?array $items = null): void {
        if ($order === null) {
            $resolved = self::resolveFullData($invoiceOrOrder);
            if (!$resolved || empty($resolved['invoice'])) {
                throw new Exception("Invoice could not be resolved for order.");
            }
            $invoice = $resolved['invoice'];
            $order = $resolved['order'];
            $items = $resolved['items'];
        } else {
            $invoice = $invoiceOrOrder;
            $items = $items ?? [];
        }

        $invNumber = $invoice['invoice_number'] ?? 'NAF-BILL';
        $filename = "Invoice-" . preg_replace('/[^A-Za-z0-9_-]/', '', $invNumber) . ".pdf";
        $output = self::generatePdfString($invoice, $order, $items);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($output));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        echo $output;
        exit;
    }

    /**
     * Escape and clean text for PDF Type 1 Helvetica font
     */
    public static function escapePdfText(string $text): string {
        $text = str_replace(["\r\n", "\r", "\n"], ' ', $text);
        $text = str_replace(['‘', '’', '`'], "'", $text);
        $text = str_replace(['“', '”'], '"', $text);
        $text = str_replace(['–', '—'], '-', $text);
        $text = str_replace('•', '.', $text);
        $text = str_replace('₹', 'Rs. ', $text);

        // Escape PDF syntax characters
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace('(', '\\(', $text);
        $text = str_replace(')', '\\)', $text);

        // Keep printable characters
        $clean = preg_replace('/[^\x20-\x7E]/', '', $text);
        return trim($clean);
    }
}
