<?php
/**
 * Telegram Order Notification Service
 * N.A Fresh Fruits & Coconuts
 * Architected for future Telegram order notifications.
 * Clean, decoupled, post-commit execution that NEVER blocks customer checkout.
 */

class TelegramService {

    /**
     * Check if Telegram notifications are enabled in store settings
     */
    public static function isEnabled(): bool {
        return Setting::get('telegram_notifications_enabled', '0') === '1';
    }

    /**
     * Get configured Telegram Bot Token (server-side only, never expose to client)
     */
    public static function getBotToken(): string {
        return trim((string)Setting::get('telegram_bot_token', ''));
    }

    /**
     * Get configured Telegram Chat / Channel ID
     */
    public static function getChatId(): string {
        return trim((string)Setting::get('telegram_chat_id', ''));
    }

    /**
     * Enqueue order notification asynchronously in the background task queue
     * This guarantees order creation is committed and customer checkout is NEVER delayed
     *
     * @param int $orderId
     * @param string $event Event type ('order_placed' or 'order_paid')
     * @return bool
     */
    public static function enqueueOrderNotification(int $orderId, string $event = 'order_placed'): bool {
        try {
            $idempotencyToken = "telegram_{$event}_{$orderId}";
            BackgroundTaskService::enqueue('telegram_order_notification', $orderId, [
                'order_id' => $orderId,
                'event' => $event,
                'enqueued_at' => date('Y-m-d H:i:s')
            ], $idempotencyToken);
            return true;
        } catch (Throwable $e) {
            error_log("Failed to enqueue Telegram order notification for Order #{$orderId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Dispatch notification for an order
     * Called by BackgroundTaskService worker, NEVER on customer critical path
     *
     * @param array $order Order record
     * @param array $items Order items
     * @return array Result
     */
    public static function sendOrderNotification(array $order, array $items = []): array {
        // 1. If not enabled or token not configured, cleanly skip without making any HTTP calls
        if (!self::isEnabled()) {
            return [
                'success' => true,
                'skipped' => true,
                'message' => 'Telegram order notifications are currently disabled in store settings.'
            ];
        }

        $token = self::getBotToken();
        $chatId = self::getChatId();

        if (empty($token) || empty($chatId)) {
            return [
                'success' => true,
                'skipped' => true,
                'message' => 'Telegram Bot Token or Chat ID not configured.'
            ];
        }

        // 2. Format message
        $messageText = self::formatOrderMessage($order, $items);

        // 3. Send via Telegram Bot API with short strict timeout
        return self::sendApiMessage($token, $chatId, $messageText);
    }

    /**
     * Send test message from admin settings panel
     */
    public static function sendTestMessage(string $token, string $chatId): array {
        if (empty($token) || empty($chatId)) {
            return ['success' => false, 'error' => 'Please provide both Bot Token and Chat ID to test.'];
        }

        $storeName = Setting::get('store_name', 'N.A Fresh Fruits & Coconuts');
        $text = "🥥 *{$storeName}* - Test Notification\n\n"
              . "✅ Your Telegram Bot notification integration is connected and functioning properly!\n"
              . "⏱ Time: " . date('d M Y, h:i A');

        return self::sendApiMessage($token, $chatId, $text);
    }

    /**
     * Format professional Telegram notification message
     */
    public static function formatOrderMessage(array $order, array $items = []): string {
        $storeName = Setting::get('store_name', 'N.A Fresh Fruits & Coconuts');
        $orderNumber = $order['order_number'] ?? ('#' . ($order['id'] ?? ''));
        $customerName = $order['customer_name'] ?? 'Customer';
        $customerPhone = $order['customer_phone'] ?? '';
        $address = $order['delivery_address'] ?? 'Jaora Delivery';
        $areaName = $order['delivery_area_name'] ?? '';
        $total = number_format((float)($order['total_amount'] ?? 0), 2);
        
        $method = ($order['payment_method'] ?? 'cod') === 'online' ? 'Razorpay Online' : 'Cash on Delivery (COD)';
        $status = ucfirst($order['payment_status'] ?? 'pending');

        $lines = [];
        $lines[] = "🥥 *NEW ORDER RECEIVED* — {$storeName}";
        $lines[] = "━━━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "📦 *Order ID:* `{$orderNumber}`";
        $lines[] = "👤 *Customer:* " . self::escapeMarkdown($customerName);
        $lines[] = "📞 *Phone:* `{$customerPhone}`";
        if (!empty($areaName)) {
            $lines[] = "📍 *Area:* " . self::escapeMarkdown($areaName);
        }
        $lines[] = "🏠 *Address:* " . self::escapeMarkdown($address);
        $lines[] = "━━━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "🛒 *ITEMS:*";

        foreach ($items as $item) {
            $qty = (int)($item['quantity'] ?? 1);
            $pName = self::escapeMarkdown($item['product_name'] ?? 'Item');
            $sub = number_format((float)($item['subtotal'] ?? 0), 2);
            $lines[] = " • {$qty}x {$pName} — ₹{$sub}";
        }

        $lines[] = "━━━━━━━━━━━━━━━━━━━━━━";
        if (!empty($order['discount_amount']) && (float)$order['discount_amount'] > 0) {
            $lines[] = "🏷 *Discount:* -₹" . number_format((float)$order['discount_amount'], 2);
        }
        $delCharge = (float)($order['delivery_charge'] ?? 0);
        $lines[] = "🚚 *Delivery:* " . ($delCharge <= 0 ? "FREE" : "₹" . number_format($delCharge, 2));
        $lines[] = "💰 *Total Amount:* *₹{$total}*";
        $lines[] = "💳 *Payment Method:* {$method}";
        $lines[] = "📊 *Payment Status:* {$status}";
        $lines[] = "⏱ *Time:* " . date('d M Y, h:i A');

        return implode("\n", $lines);
    }

    /**
     * Send HTTP POST request to Telegram Bot API
     */
    private static function sendApiMessage(string $token, string $chatId, string $text): array {
        $url = "https://api.telegram.org/bot" . urlencode($token) . "/sendMessage";
        
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'disable_web_page_preview' => true
        ];

        // Safe cURL with strict 3 second timeout so nothing ever hangs
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 3,
                CURLOPT_SSL_VERIFYPEER => true
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) {
                error_log("Telegram API Error: {$err}");
                return ['success' => false, 'error' => "cURL communication error: {$err}"];
            }

            $decoded = json_decode($response, true);
            if ($httpCode === 200 && !empty($decoded['ok'])) {
                return ['success' => true, 'data' => $decoded['result'] ?? []];
            } else {
                $msg = $decoded['description'] ?? "HTTP {$httpCode}";
                error_log("Telegram API Failed ({$httpCode}): {$msg}");
                return ['success' => false, 'error' => "Telegram returned: {$msg}"];
            }
        }

        return ['success' => false, 'error' => 'cURL extension is not installed'];
    }

    /**
     * Escape special Markdown characters for Telegram V1 markdown
     */
    private static function escapeMarkdown(string $text): string {
        return str_replace(['_', '*', '`', '['], ['\\_', '\\*', '\\`', '\\['], $text);
    }
}
