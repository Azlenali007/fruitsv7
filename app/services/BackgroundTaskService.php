<?php
/**
 * Background Task Service
 * N.A Fresh Fruits & Coconuts
 * Provides reliable, database-backed, non-blocking asynchronous task execution
 * for post-commit operations (e.g. Telegram notifications, invoice caching, email receipts).
 */

class BackgroundTaskService {
    private static bool $shutdownRegistered = false;

    /**
     * Enqueue a background task reliably with idempotency protection
     *
     * @param string $taskType Type of task (e.g. 'telegram_order_notification')
     * @param int|null $referenceId Associated record ID (e.g. order_id)
     * @param array $payload Task payload data
     * @param string|null $idempotencyToken Unique token to prevent duplicate task execution
     * @return int Task ID
     */
    public static function enqueue(string $taskType, ?int $referenceId, array $payload, ?string $idempotencyToken = null): int {
        $pdo = Database::getConnection();
        
        $token = $idempotencyToken ?: ($taskType . '_' . ($referenceId ?? 'gen') . '_' . bin2hex(random_bytes(8)));

        // Check for existing task with same idempotency token
        $stmtCheck = $pdo->prepare("SELECT `id` FROM `background_tasks` WHERE `idempotency_token` = ? LIMIT 1");
        $stmtCheck->execute([$token]);
        $existingId = $stmtCheck->fetchColumn();
        if ($existingId) {
            return (int)$existingId;
        }

        $stmtInsert = $pdo->prepare("
            INSERT INTO `background_tasks` (
                `task_type`, `reference_id`, `idempotency_token`, `payload_json`,
                `status`, `attempts`, `max_attempts`, `next_attempt_at`, `created_at`
            ) VALUES (?, ?, ?, ?, 'pending', 0, 5, NOW(), NOW())
        ");
        
        $stmtInsert->execute([
            $taskType,
            $referenceId,
            $token,
            json_encode($payload, JSON_UNESCAPED_UNICODE)
        ]);

        $taskId = (int)$pdo->lastInsertId();

        // Register post-commit shutdown runner so customer is never blocked
        self::registerShutdownWorker();

        return $taskId;
    }

    /**
     * Register shutdown function to process pending tasks after HTTP response is sent
     */
    public static function registerShutdownWorker(): void {
        if (self::$shutdownRegistered) {
            return;
        }
        self::$shutdownRegistered = true;

        register_shutdown_function(function() {
            // Close HTTP connection to browser first if fastcgi available
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            
            // Process queue safely without crashing the request
            try {
                self::processQueue(3);
            } catch (Throwable $e) {
                error_log("Background task runner error: " . $e->getMessage());
            }
        });
    }

    /**
     * Process pending background tasks with atomic row locking and exponential backoff
     *
     * @param int $limit Max tasks to process in one batch
     * @return array Results summary
     */
    public static function processQueue(int $limit = 5): array {
        $pdo = Database::getConnection();
        $results = ['processed' => 0, 'succeeded' => 0, 'failed' => 0];

        try {
            // Find eligible pending tasks
            $stmt = $pdo->prepare("
                SELECT * FROM `background_tasks`
                WHERE `status` IN ('pending', 'failed')
                  AND `attempts` < `max_attempts`
                  AND `next_attempt_at` <= NOW()
                ORDER BY `id` ASC
                LIMIT ?
            ");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $tasks = $stmt->fetchAll();

            foreach ($tasks as $task) {
                $taskId = (int)$task['id'];
                
                // Atomically claim task
                $stmtLock = $pdo->prepare("
                    UPDATE `background_tasks`
                    SET `status` = 'processing', `locked_at` = NOW()
                    WHERE `id` = ? AND `status` != 'processing'
                ");
                $stmtLock->execute([$taskId]);
                if ($stmtLock->rowCount() === 0) {
                    continue; // Task already claimed by another worker
                }

                $results['processed']++;
                $payload = json_decode($task['payload_json'], true) ?: [];

                try {
                    $success = self::executeTaskHandler($task['task_type'], $task['reference_id'], $payload);
                    
                    if ($success) {
                        $stmtComplete = $pdo->prepare("
                            UPDATE `background_tasks`
                            SET `status` = 'completed', `completed_at` = NOW(), `last_error` = NULL
                            WHERE `id` = ?
                        ");
                        $stmtComplete->execute([$taskId]);
                        $results['succeeded']++;
                    } else {
                        self::handleTaskFailure($taskId, $task, "Task handler returned false");
                        $results['failed']++;
                    }
                } catch (Throwable $e) {
                    self::handleTaskFailure($taskId, $task, $e->getMessage());
                    $results['failed']++;
                }
            }
        } catch (Throwable $e) {
            error_log("BackgroundTaskService processQueue exception: " . $e->getMessage());
        }

        return $results;
    }

    /**
     * Route task to appropriate service handler
     */
    private static function executeTaskHandler(string $taskType, ?int $referenceId, array $payload): bool {
        switch ($taskType) {
            case 'telegram_order_notification':
                $orderId = $referenceId ?: (int)($payload['order_id'] ?? 0);
                if ($orderId > 0) {
                    $order = Order::getById($orderId);
                    if ($order) {
                        $items = Order::getItems($orderId);
                        $res = TelegramService::sendOrderNotification($order, $items);
                        return !empty($res['success']);
                    }
                }
                return true;

            default:
                // Unknown task type, mark completed to prevent looping
                return true;
        }
    }

    /**
     * Record failure with exponential backoff retry scheduling
     */
    private static function handleTaskFailure(int $taskId, array $task, string $errorMessage): void {
        $pdo = Database::getConnection();
        $newAttempts = (int)$task['attempts'] + 1;
        $maxAttempts = (int)$task['max_attempts'];
        
        // Exponential backoff: 30s, 60s, 120s, 300s...
        $delaySeconds = min(600, 30 * pow(2, $newAttempts - 1));
        $newStatus = ($newAttempts >= $maxAttempts) ? 'failed' : 'pending';

        $stmt = $pdo->prepare("
            UPDATE `background_tasks`
            SET `status` = ?,
                `attempts` = ?,
                `last_error` = ?,
                `next_attempt_at` = DATE_ADD(NOW(), INTERVAL ? SECOND)
            WHERE `id` = ?
        ");
        $stmt->execute([$newStatus, $newAttempts, $errorMessage, $delaySeconds, $taskId]);
    }
}
