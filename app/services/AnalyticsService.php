<?php
/**
 * Live Traffic & Visitor Analytics Service
 * N.A Fresh Fruits & Coconuts
 * Lightweight, privacy-conscious, first-party tracking for storefront visits.
 */

class AnalyticsService {
    private static bool $trackedThisRequest = false;

    /**
     * Track incoming storefront visit and pageview
     */
    public static function track(string $uri, array $getParams = [], ?string $title = null): void {
        if (self::$trackedThisRequest) {
            return;
        }

        // Do not track internal, admin, webhook, API, or static asset requests
        if (
            str_starts_with($uri, '/admin') ||
            str_starts_with($uri, '/api') ||
            str_starts_with($uri, '/webhooks') ||
            str_starts_with($uri, '/install') ||
            preg_match('/\.(png|jpe?g|gif|svg|ico|css|js|map|woff2?|ttf|pdf)$/i', $uri)
        ) {
            return;
        }

        try {
            $pdo = Database::getConnection();

            // Session Identifier
            $sessionId = session_id();
            if (empty($sessionId)) {
                return;
            }

            self::$trackedThisRequest = true;

            // IP Hash (privacy-safe, non-reversible SHA-256 with salt)
            $rawIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            if (str_contains($rawIp, ',')) {
                $rawIp = trim(explode(',', $rawIp)[0]);
            }
            $ipHash = hash('sha256', $rawIp . '_na_analytics_salt');

            $userAgent = substr(trim($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

            // Device detection
            $deviceType = 'desktop';
            if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i', $userAgent)) {
                $deviceType = 'tablet';
            } elseif (preg_match('/Mobile|Android|iP(hone|od)|IEMobile|BlackBerry|Kindle|NetFront|Silk-Accelerated|(hpw|web)OS|Fennec|Minimo|Opera M(obi|ini)|Blazer|Dolfin|Dolphin|Skyfire|Zune/i', $userAgent)) {
                $deviceType = 'mobile';
            }

            // Traffic Source Detection
            $trafficSource = 'direct';
            $referrer = trim($_SERVER['HTTP_REFERER'] ?? '');
            $utmSource = strtolower(trim($getParams['utm_source'] ?? ''));
            $utmMedium = strtolower(trim($getParams['utm_medium'] ?? ''));
            $utmCampaign = strtolower(trim($getParams['utm_campaign'] ?? ''));

            if (!empty($utmSource)) {
                if (str_contains($utmSource, 'whatsapp') || $utmSource === 'wa') {
                    $trafficSource = 'whatsapp';
                } elseif (str_contains($utmSource, 'instagram') || $utmSource === 'ig') {
                    $trafficSource = 'instagram';
                } elseif (str_contains($utmSource, 'youtube') || $utmSource === 'yt') {
                    $trafficSource = 'youtube';
                } elseif (str_contains($utmSource, 'facebook') || $utmSource === 'fb') {
                    $trafficSource = 'facebook';
                } elseif (str_contains($utmSource, 'google')) {
                    $trafficSource = 'google';
                } else {
                    $trafficSource = substr($utmSource, 0, 50);
                }
            } elseif (!empty($referrer)) {
                $refHost = parse_url($referrer, PHP_URL_HOST) ?? '';
                if (str_contains($refHost, 'whatsapp') || str_contains($refHost, 'wa.me')) {
                    $trafficSource = 'whatsapp';
                } elseif (str_contains($refHost, 'instagram.com')) {
                    $trafficSource = 'instagram';
                } elseif (str_contains($refHost, 'youtube.com') || str_contains($refHost, 'youtu.be')) {
                    $trafficSource = 'youtube';
                } elseif (str_contains($refHost, 'facebook.com') || str_contains($refHost, 'fb.com')) {
                    $trafficSource = 'facebook';
                } elseif (str_contains($refHost, 'google.')) {
                    $trafficSource = 'google';
                } else {
                    $trafficSource = 'referral';
                }
            }

            // Approximate City / Area (No GPS tracking - respectful of user privacy)
            $cityHeader = $_SERVER['HTTP_CF_IPCITY'] ?? $_SERVER['HTTP_X_GEOIP_CITY'] ?? '';
            $approxCity = !empty($cityHeader) ? substr(trim($cityHeader), 0, 80) : 'Jaora / Bhopal (Local)';

            // Normalize page URL (e.g. /shop, /cart, /)
            $cleanUri = parse_url($uri, PHP_URL_PATH) ?: '/';
            if ($cleanUri === '') $cleanUri = '/';

            // Page title mapping
            if ($title === null) {
                $titles = [
                    '/' => 'Storefront Homepage',
                    '/shop' => 'Fresh Coconut Water - Shop',
                    '/cart' => 'Shopping Cart',
                    '/checkout' => 'Doorstep Checkout',
                    '/confirmation' => 'Order Confirmation Receipt',
                    '/track' => 'Order Tracking',
                    '/login' => 'Customer Login',
                    '/register' => 'Customer Registration',
                    '/account' => 'Customer Account Profile',
                    '/my-orders' => 'Order History'
                ];
                $title = $titles[$cleanUri] ?? ucfirst(trim($cleanUri, '/'));
            }

            // Record or Update Session
            $stmtSession = $pdo->prepare("
                INSERT INTO `visitor_sessions` (
                    `session_id`, `visitor_ip_hash`, `user_agent`, `device_type`,
                    `traffic_source`, `referrer_url`, `utm_source`, `utm_medium`, `utm_campaign`,
                    `approx_city`, `first_seen_at`, `last_active_at`
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, NOW(), NOW()
                )
                ON DUPLICATE KEY UPDATE 
                    `last_active_at` = NOW(),
                    `device_type` = VALUES(`device_type`)
            ");

            $stmtSession->execute([
                $sessionId,
                $ipHash,
                $userAgent,
                $deviceType,
                $trafficSource,
                substr($referrer, 0, 255),
                substr($utmSource, 0, 50),
                substr($utmMedium, 0, 50),
                substr($utmCampaign, 0, 50),
                $approxCity
            ]);

            // Record Pageview
            $stmtPageView = $pdo->prepare("
                INSERT INTO `visitor_pageviews` (`session_id`, `page_url`, `page_title`, `viewed_at`)
                VALUES (?, ?, ?, NOW())
            ");
            $stmtPageView->execute([
                $sessionId,
                substr($cleanUri, 0, 150),
                substr($title, 0, 100)
            ]);

        } catch (Throwable $e) {
            // Non-blocking: analytics should never interfere with customer browsing or purchasing
            error_log("Analytics tracking error: " . $e->getMessage());
        }
    }

    /**
     * Get live active visitors currently on the website (within last 5 minutes)
     */
    public static function getLiveVisitors(int $timeoutMinutes = 5): int {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT `session_id`) 
                FROM `visitor_sessions` 
                WHERE `last_active_at` >= (NOW() - INTERVAL ? MINUTE)
            ");
            $stmt->execute([$timeoutMinutes]);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Get Total Traffic summary: Today, Yesterday, Last 7 Days, Last 30 Days
     */
    public static function getTrafficSummary(): array {
        try {
            $pdo = Database::getConnection();

            // Today
            $stmtToday = $pdo->query("
                SELECT 
                    COUNT(DISTINCT s.session_id) AS visitors,
                    COUNT(p.id) AS pageviews
                FROM `visitor_sessions` s
                LEFT JOIN `visitor_pageviews` p ON s.session_id = p.session_id 
                    AND p.viewed_at >= CURDATE()
                WHERE s.last_active_at >= CURDATE()
            ");
            $today = $stmtToday->fetch() ?: ['visitors' => 0, 'pageviews' => 0];

            // Yesterday
            $stmtYest = $pdo->query("
                SELECT 
                    COUNT(DISTINCT s.session_id) AS visitors,
                    COUNT(p.id) AS pageviews
                FROM `visitor_sessions` s
                LEFT JOIN `visitor_pageviews` p ON s.session_id = p.session_id 
                    AND p.viewed_at >= (CURDATE() - INTERVAL 1 DAY) AND p.viewed_at < CURDATE()
                WHERE s.first_seen_at >= (CURDATE() - INTERVAL 1 DAY) AND s.first_seen_at < CURDATE()
            ");
            $yesterday = $stmtYest->fetch() ?: ['visitors' => 0, 'pageviews' => 0];

            // Last 7 Days
            $stmt7d = $pdo->query("
                SELECT 
                    COUNT(DISTINCT s.session_id) AS visitors,
                    COUNT(p.id) AS pageviews
                FROM `visitor_sessions` s
                LEFT JOIN `visitor_pageviews` p ON s.session_id = p.session_id 
                    AND p.viewed_at >= (CURDATE() - INTERVAL 7 DAY)
                WHERE s.last_active_at >= (CURDATE() - INTERVAL 7 DAY)
            ");
            $last7d = $stmt7d->fetch() ?: ['visitors' => 0, 'pageviews' => 0];

            // Last 30 Days
            $stmt30d = $pdo->query("
                SELECT 
                    COUNT(DISTINCT s.session_id) AS visitors,
                    COUNT(p.id) AS pageviews
                FROM `visitor_sessions` s
                LEFT JOIN `visitor_pageviews` p ON s.session_id = p.session_id 
                    AND p.viewed_at >= (CURDATE() - INTERVAL 30 DAY)
                WHERE s.last_active_at >= (CURDATE() - INTERVAL 30 DAY)
            ");
            $last30d = $stmt30d->fetch() ?: ['visitors' => 0, 'pageviews' => 0];

            return [
                'today' => ['visitors' => (int)($today['visitors'] ?? 0), 'pageviews' => (int)($today['pageviews'] ?? 0)],
                'yesterday' => ['visitors' => (int)($yesterday['visitors'] ?? 0), 'pageviews' => (int)($yesterday['pageviews'] ?? 0)],
                'last_7_days' => ['visitors' => (int)($last7d['visitors'] ?? 0), 'pageviews' => (int)($last7d['pageviews'] ?? 0)],
                'last_30_days' => ['visitors' => (int)($last30d['visitors'] ?? 0), 'pageviews' => (int)($last30d['pageviews'] ?? 0)],
            ];
        } catch (Throwable $e) {
            return [
                'today' => ['visitors' => 0, 'pageviews' => 0],
                'yesterday' => ['visitors' => 0, 'pageviews' => 0],
                'last_7_days' => ['visitors' => 0, 'pageviews' => 0],
                'last_30_days' => ['visitors' => 0, 'pageviews' => 0],
            ];
        }
    }

    /**
     * Get traffic source breakdown (WhatsApp, Instagram, YouTube, Facebook, Google, Direct, Referral)
     */
    public static function getTrafficSources(int $days = 30): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                SELECT `traffic_source`, COUNT(DISTINCT `session_id`) AS `session_count`
                FROM `visitor_sessions`
                WHERE `first_seen_at` >= (CURDATE() - INTERVAL ? DAY)
                GROUP BY `traffic_source`
                ORDER BY `session_count` DESC
            ");
            $stmt->execute([$days]);
            $rows = $stmt->fetchAll();

            $known = [
                'whatsapp' => 0,
                'instagram' => 0,
                'youtube' => 0,
                'facebook' => 0,
                'google' => 0,
                'direct' => 0,
                'referral' => 0
            ];

            $total = 0;
            foreach ($rows as $r) {
                $src = strtolower($r['traffic_source']);
                $cnt = (int)$r['session_count'];
                if (array_key_exists($src, $known)) {
                    $known[$src] += $cnt;
                } else {
                    $known['referral'] += $cnt;
                }
                $total += $cnt;
            }

            return [
                'sources' => $known,
                'total' => $total
            ];
        } catch (Throwable $e) {
            return ['sources' => [], 'total' => 0];
        }
    }

    /**
     * Get most visited pages tracking
     */
    public static function getTopPages(int $days = 30, int $limit = 10): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                SELECT `page_url`, `page_title`, COUNT(*) AS `views`, COUNT(DISTINCT `session_id`) AS `unique_visitors`
                FROM `visitor_pageviews`
                WHERE `viewed_at` >= (CURDATE() - INTERVAL ? DAY)
                GROUP BY `page_url`, `page_title`
                ORDER BY `views` DESC
                LIMIT ?
            ");
            $stmt->bindValue(1, $days, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Get device breakdown (Mobile vs Desktop vs Tablet)
     */
    public static function getDeviceBreakdown(int $days = 30): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                SELECT `device_type`, COUNT(DISTINCT `session_id`) AS `count`
                FROM `visitor_sessions`
                WHERE `first_seen_at` >= (CURDATE() - INTERVAL ? DAY)
                GROUP BY `device_type`
            ");
            $stmt->execute([$days]);
            $rows = $stmt->fetchAll();

            $devices = ['mobile' => 0, 'desktop' => 0, 'tablet' => 0];
            $total = 0;
            foreach ($rows as $r) {
                $type = strtolower($r['device_type']);
                if (isset($devices[$type])) {
                    $devices[$type] = (int)$r['count'];
                    $total += (int)$r['count'];
                }
            }

            return [
                'devices' => $devices,
                'total' => $total,
                'mobile_pct' => $total > 0 ? round(($devices['mobile'] / $total) * 100, 1) : 0,
                'desktop_pct' => $total > 0 ? round(($devices['desktop'] / $total) * 100, 1) : 0,
                'tablet_pct' => $total > 0 ? round(($devices['tablet'] / $total) * 100, 1) : 0
            ];
        } catch (Throwable $e) {
            return ['devices' => ['mobile' => 0, 'desktop' => 0, 'tablet' => 0], 'total' => 0, 'mobile_pct' => 0, 'desktop_pct' => 0, 'tablet_pct' => 0];
        }
    }

    /**
     * Get approximate location distribution
     */
    public static function getLocationDistribution(int $days = 30, int $limit = 8): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                SELECT `approx_city`, COUNT(DISTINCT `session_id`) AS `visitors`
                FROM `visitor_sessions`
                WHERE `first_seen_at` >= (CURDATE() - INTERVAL ? DAY)
                GROUP BY `approx_city`
                ORDER BY `visitors` DESC
                LIMIT ?
            ");
            $stmt->bindValue(1, $days, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Get daily traffic trend for Chart.js
     */
    public static function getDailyTrend(int $days = 7): array {
        try {
            $pdo = Database::getConnection();

            $dates = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $d = date('Y-m-d', strtotime("-{$i} days"));
                $dates[$d] = [
                    'label' => date('d M', strtotime($d)),
                    'visitors' => 0,
                    'pageviews' => 0
                ];
            }

            $startDate = date('Y-m-d', strtotime("-" . ($days - 1) . " days"));

            // Unique visitors per day
            $stmtVis = $pdo->prepare("
                SELECT DATE(`first_seen_at`) AS `day`, COUNT(DISTINCT `session_id`) AS `cnt`
                FROM `visitor_sessions`
                WHERE DATE(`first_seen_at`) >= ?
                GROUP BY DATE(`first_seen_at`)
            ");
            $stmtVis->execute([$startDate]);
            foreach ($stmtVis->fetchAll() as $r) {
                if (isset($dates[$r['day']])) {
                    $dates[$r['day']]['visitors'] = (int)$r['cnt'];
                }
            }

            // Pageviews per day
            $stmtPv = $pdo->prepare("
                SELECT DATE(`viewed_at`) AS `day`, COUNT(*) AS `cnt`
                FROM `visitor_pageviews`
                WHERE DATE(`viewed_at`) >= ?
                GROUP BY DATE(`viewed_at`)
            ");
            $stmtPv->execute([$startDate]);
            foreach ($stmtPv->fetchAll() as $r) {
                if (isset($dates[$r['day']])) {
                    $dates[$r['day']]['pageviews'] = (int)$r['cnt'];
                }
            }

            return [
                'labels' => array_column($dates, 'label'),
                'visitors' => array_column($dates, 'visitors'),
                'pageviews' => array_column($dates, 'pageviews'),
            ];
        } catch (Throwable $e) {
            return ['labels' => [], 'visitors' => [], 'pageviews' => []];
        }
    }

    /**
     * Alias for getLiveVisitors
     */
    public static function getLiveVisitorCount(int $timeoutMinutes = 5): int {
        return self::getLiveVisitors($timeoutMinutes);
    }

    /**
     * Get unique visitors today
     */
    public static function getTodayVisitorCount(): int {
        $summary = self::getTrafficSummary();
        return (int)($summary['today']['visitors'] ?? 0);
    }

    /**
     * Get recent pageviews for live feed
     */
    public static function getRecentPageviews(int $limit = 15): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                SELECT p.page_url, p.page_title, p.viewed_at, s.device_type, s.traffic_source, s.approx_city
                FROM `visitor_pageviews` p
                JOIN `visitor_sessions` s ON p.session_id = s.session_id
                ORDER BY p.id DESC
                LIMIT ?
            ");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }
}
