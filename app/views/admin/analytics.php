<?php
/**
 * Admin Live Traffic & Visitor Analytics View
 * N.A Fresh Fruits & Coconuts
 */

$adminTitle = 'Live Traffic & Analytics - N.A Fresh Fruits & Coconuts';
$activeAdminTab = 'analytics';
require_once __DIR__ . '/layout-header.php';

$rangeDays = max(1, min(90, (int)($_GET['days'] ?? 7)));

$liveVisitors = AnalyticsService::getLiveVisitors(5);
$trafficSummary = AnalyticsService::getTrafficSummary();
$sourcesData = AnalyticsService::getTrafficSources($rangeDays);
$topPages = AnalyticsService::getTopPages($rangeDays, 10);
$devices = AnalyticsService::getDeviceBreakdown($rangeDays);
$locations = AnalyticsService::getLocationDistribution($rangeDays, 6);
$trend = AnalyticsService::getDailyTrend($rangeDays);
?>

<div class="space-y-8" x-data="{
    liveCount: <?= $liveVisitors ?>,
    isRefreshing: false,
    refreshLive() {
        this.isRefreshing = true;
        fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const liveEl = doc.getElementById('liveVisitorCount');
                if (liveEl) {
                    this.liveCount = parseInt(liveEl.innerText, 10) || 0;
                }
                this.isRefreshing = false;
            })
            .catch(() => { this.isRefreshing = false; });
    },
    init() {
        // Auto-refresh live count every 20 seconds
        setInterval(() => this.refreshLive(), 20000);
    }
}">

    <!-- Page Header with Live Indicator & Filter -->
    <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-black text-gray-900 tracking-tight">Live Traffic &amp; Visitor Analytics</h1>
                <!-- Live Pill -->
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-600"></span>
                    </span>
                    <span>LIVE</span>
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-0.5">Real-time first-party analytics for storefront visitors, channels, devices &amp; pages</p>
        </div>

        <div class="flex items-center gap-2">
            <!-- Date Filter Pills -->
            <div class="bg-white border border-gray-200 rounded-xl p-1 flex items-center gap-1 text-xs font-bold text-gray-600">
                <a href="?days=7" class="px-3 py-1.5 rounded-lg transition <?= $rangeDays === 7 ? 'bg-[#235d3a] text-white' : 'hover:text-gray-900' ?>">Last 7 Days</a>
                <a href="?days=30" class="px-3 py-1.5 rounded-lg transition <?= $rangeDays === 30 ? 'bg-[#235d3a] text-white' : 'hover:text-gray-900' ?>">Last 30 Days</a>
            </div>
            <!-- Refresh Button -->
            <button 
                type="button" 
                @click="refreshLive()" 
                class="bg-white border border-gray-200 hover:border-gray-300 text-gray-700 p-2.5 rounded-xl transition shadow-xs flex items-center justify-center"
                title="Refresh Live Data"
            >
                <svg class="w-4 h-4" :class="isRefreshing ? 'animate-spin text-brand-600' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
            </button>
        </div>
    </div>

    <!-- 1. Live Active Visitors Spotlight Hero Card -->
    <div class="bg-gradient-to-br from-[#1b4a2e] to-[#235d3a] rounded-3xl p-6 sm:p-8 text-white shadow-lg relative overflow-hidden">
        <!-- Subtle background watermark -->
        <div class="absolute -right-8 -bottom-8 opacity-10 pointer-events-none">
            <svg class="w-64 h-64" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"></path></svg>
        </div>

        <div class="relative z-10 flex flex-col sm:flex-row justify-between sm:items-center gap-6">
            <div class="space-y-2">
                <span class="text-xs uppercase tracking-wider font-extrabold text-emerald-200">Real-Time Traffic Pulse</span>
                <div class="flex items-baseline gap-4">
                    <span id="liveVisitorCount" class="text-5xl sm:text-6xl font-black tracking-tight" x-text="liveCount"><?= $liveVisitors ?></span>
                    <span class="text-lg font-bold text-emerald-100">Active Visitors Right Now</span>
                </div>
                <p class="text-xs text-emerald-100/90 max-w-md leading-relaxed">
                    Shoppers currently active on your tender coconut water storefront within the last 5 minutes.
                </p>
            </div>

            <div class="bg-white/10 backdrop-blur-md rounded-2xl p-4 border border-white/15 text-xs space-y-2 shrink-0">
                <div class="flex justify-between gap-6">
                    <span class="text-emerald-200">Active Device:</span>
                    <span class="font-bold"><?= $devices['mobile_pct'] >= $devices['desktop_pct'] ? '📱 Mobile First' : '💻 Desktop' ?></span>
                </div>
                <div class="flex justify-between gap-6">
                    <span class="text-emerald-200">Top Channel:</span>
                    <span class="font-bold uppercase"><?= !empty($sourcesData['sources']) ? array_key_first($sourcesData['sources']) : 'Direct' ?></span>
                </div>
                <div class="flex justify-between gap-6">
                    <span class="text-emerald-200">Active Zone:</span>
                    <span class="font-bold">Jaora &amp; Bhopal</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Total Traffic: Today, Yesterday, 7 Days, 30 Days -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Today -->
        <div class="bg-white rounded-2xl p-4 sm:p-5 border border-gray-100 shadow-xs space-y-1">
            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Today</span>
            <p class="text-2xl sm:text-3xl font-black text-gray-900"><?= number_format($trafficSummary['today']['visitors']) ?></p>
            <span class="text-[11px] text-gray-500 font-medium block"><?= number_format($trafficSummary['today']['pageviews']) ?> pageviews</span>
        </div>

        <!-- Yesterday -->
        <div class="bg-white rounded-2xl p-4 sm:p-5 border border-gray-100 shadow-xs space-y-1">
            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Yesterday</span>
            <p class="text-2xl sm:text-3xl font-black text-gray-900"><?= number_format($trafficSummary['yesterday']['visitors']) ?></p>
            <span class="text-[11px] text-gray-500 font-medium block"><?= number_format($trafficSummary['yesterday']['pageviews']) ?> pageviews</span>
        </div>

        <!-- Last 7 Days -->
        <div class="bg-white rounded-2xl p-4 sm:p-5 border border-gray-100 shadow-xs space-y-1">
            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Last 7 Days</span>
            <p class="text-2xl sm:text-3xl font-black text-brand-700"><?= number_format($trafficSummary['last_7_days']['visitors']) ?></p>
            <span class="text-[11px] text-gray-500 font-medium block"><?= number_format($trafficSummary['last_7_days']['pageviews']) ?> pageviews</span>
        </div>

        <!-- Last 30 Days -->
        <div class="bg-white rounded-2xl p-4 sm:p-5 border border-gray-100 shadow-xs space-y-1">
            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Last 30 Days</span>
            <p class="text-2xl sm:text-3xl font-black text-gray-900"><?= number_format($trafficSummary['last_30_days']['visitors']) ?></p>
            <span class="text-[11px] text-gray-500 font-medium block"><?= number_format($trafficSummary['last_30_days']['pageviews']) ?> pageviews</span>
        </div>
    </div>

    <!-- 3. Traffic History Graph (Chart.js) -->
    <div class="bg-white rounded-3xl p-5 sm:p-7 border border-gray-100 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-2">
            <div>
                <h3 class="font-bold text-base text-gray-900">Traffic History &amp; Trend</h3>
                <p class="text-xs text-gray-400">Daily unique visitors and pageviews over the last <?= $rangeDays ?> days</p>
            </div>
            <div class="flex items-center gap-4 text-xs font-bold">
                <span class="flex items-center gap-1.5 text-[#235d3a]">
                    <span class="w-3 h-3 rounded-full bg-[#235d3a]"></span> Visitors
                </span>
                <span class="flex items-center gap-1.5 text-emerald-400">
                    <span class="w-3 h-3 rounded-full bg-emerald-400"></span> Pageviews
                </span>
            </div>
        </div>
        <div class="h-64 sm:h-72 w-full pt-2">
            <canvas id="trafficTrendChart"></canvas>
        </div>
    </div>

    <!-- 4. Two-Column Row: Traffic Sources + Device Tracking -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Traffic Sources (7 Cols) -->
        <div class="lg:col-span-7 bg-white rounded-3xl p-5 sm:p-7 border border-gray-100 shadow-sm space-y-5">
            <div>
                <h3 class="font-bold text-base text-gray-900">Traffic Acquisition Sources</h3>
                <p class="text-xs text-gray-400">Where your visitors are discovering N.A Fresh Fruits (UTM parameters &amp; Referrers)</p>
            </div>

            <?php
            $srcIcons = [
                'whatsapp'  => ['name' => 'WhatsApp', 'color' => '#25D366', 'bg' => 'bg-emerald-50 text-emerald-700', 'badge' => '💬 WhatsApp Channel & Direct Chats'],
                'instagram' => ['name' => 'Instagram', 'color' => '#E1306C', 'bg' => 'bg-pink-50 text-pink-700', 'badge' => '📸 Instagram Bio & Reels'],
                'youtube'   => ['name' => 'YouTube', 'color' => '#FF0000', 'bg' => 'bg-red-50 text-red-700', 'badge' => '▶️ YouTube Videos & Shorts'],
                'facebook'  => ['name' => 'Facebook', 'color' => '#1877F2', 'bg' => 'bg-blue-50 text-blue-700', 'badge' => '👥 Facebook Posts & Ads'],
                'google'    => ['name' => 'Google Search / Ads', 'color' => '#4285F4', 'bg' => 'bg-amber-50 text-amber-700', 'badge' => '🔍 Google Organic & Maps'],
                'direct'    => ['name' => 'Direct Traffic', 'color' => '#235d3a', 'bg' => 'bg-gray-100 text-gray-800', 'badge' => '🌐 Direct URL & Bookmarks'],
                'referral'  => ['name' => 'Other Referrals', 'color' => '#6b7280', 'bg' => 'bg-gray-50 text-gray-600', 'badge' => '🔗 External Websites & Links'],
            ];
            $totalSrc = max(1, $sourcesData['total']);
            ?>

            <div class="space-y-3.5">
                <?php foreach ($srcIcons as $srcKey => $meta): 
                    $count = $sourcesData['sources'][$srcKey] ?? 0;
                    $pct = round(($count / $totalSrc) * 100, 1);
                ?>
                <div class="space-y-1.5">
                    <div class="flex justify-between items-center text-xs">
                        <span class="font-bold text-gray-800 flex items-center gap-2">
                            <span><?= $meta['badge'] ?></span>
                        </span>
                        <div class="flex items-center gap-2 font-mono">
                            <span class="font-bold text-gray-900"><?= number_format($count) ?></span>
                            <span class="text-gray-400 text-[11px]">(<?= $pct ?>%)</span>
                        </div>
                    </div>
                    <!-- Progress Bar -->
                    <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-500" style="width: <?= $pct ?>%; background-color: <?= $meta['color'] ?>;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Device & Location Tracking (5 Cols) -->
        <div class="lg:col-span-5 space-y-6">
            <!-- Device Tracking Card -->
            <div class="bg-white rounded-3xl p-5 sm:p-7 border border-gray-100 shadow-sm space-y-5">
                <div>
                    <h3 class="font-bold text-base text-gray-900">Device Breakdown</h3>
                    <p class="text-xs text-gray-400">Visitor hardware distribution</p>
                </div>

                <div class="grid grid-cols-3 gap-3 text-center">
                    <!-- Mobile -->
                    <div class="bg-emerald-50/70 border border-emerald-100 rounded-2xl p-3.5 space-y-1">
                        <span class="text-2xl block">📱</span>
                        <span class="text-[10px] uppercase font-bold text-emerald-800 block">Mobile</span>
                        <p class="text-lg font-black text-gray-900"><?= $devices['mobile_pct'] ?>%</p>
                        <span class="text-[10px] text-gray-500"><?= number_format($devices['devices']['mobile']) ?></span>
                    </div>

                    <!-- Desktop -->
                    <div class="bg-blue-50/70 border border-blue-100 rounded-2xl p-3.5 space-y-1">
                        <span class="text-2xl block">💻</span>
                        <span class="text-[10px] uppercase font-bold text-blue-800 block">Desktop</span>
                        <p class="text-lg font-black text-gray-900"><?= $devices['desktop_pct'] ?>%</p>
                        <span class="text-[10px] text-gray-500"><?= number_format($devices['devices']['desktop']) ?></span>
                    </div>

                    <!-- Tablet -->
                    <div class="bg-amber-50/70 border border-amber-100 rounded-2xl p-3.5 space-y-1">
                        <span class="text-2xl block">📟</span>
                        <span class="text-[10px] uppercase font-bold text-amber-800 block">Tablet</span>
                        <p class="text-lg font-black text-gray-900"><?= $devices['tablet_pct'] ?>%</p>
                        <span class="text-[10px] text-gray-500"><?= number_format($devices['devices']['tablet']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Approximate Location Card -->
            <div class="bg-white rounded-3xl p-5 sm:p-7 border border-gray-100 shadow-sm space-y-4">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="font-bold text-base text-gray-900">Approximate Service Regions</h3>
                        <p class="text-xs text-gray-400">Privacy-preserving city &amp; region metrics</p>
                    </div>
                    <span class="text-[10px] bg-gray-100 text-gray-600 font-bold px-2 py-0.5 rounded">No GPS tracked</span>
                </div>

                <div class="space-y-2">
                    <?php if (empty($locations)): ?>
                        <p class="text-xs text-gray-400 italic py-2">Traffic location recording is active. Data populates as visitors browse.</p>
                    <?php else: ?>
                        <?php foreach ($locations as $loc): ?>
                        <div class="flex justify-between items-center p-2 rounded-xl bg-gray-50 text-xs font-semibold text-gray-700">
                            <span class="flex items-center gap-1.5">
                                <span>📍</span>
                                <span><?= e($loc['approx_city']) ?></span>
                            </span>
                            <span class="font-mono font-bold text-brand-700"><?= number_format($loc['visitors']) ?> visitors</span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Page Tracking: Most Visited Pages Table -->
    <div class="bg-white rounded-3xl p-5 sm:p-7 border border-gray-100 shadow-sm space-y-4">
        <div>
            <h3 class="font-bold text-base text-gray-900">Most Visited Store Pages</h3>
            <p class="text-xs text-gray-400">Total views and unique shopper engagements by URL route</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-gray-600">
                <thead class="bg-gray-50 text-[11px] uppercase font-bold text-gray-400 border-b border-gray-100">
                    <tr>
                        <th class="py-3 px-4">Page / Route</th>
                        <th class="py-3 px-4">Section Name</th>
                        <th class="py-3 px-4 text-right">Pageviews</th>
                        <th class="py-3 px-4 text-right">Unique Visitors</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 font-medium">
                    <?php if (empty($topPages)): ?>
                    <tr>
                        <td colspan="4" class="py-6 text-center text-gray-400 italic">
                            Live pageview recording active. Browse storefront pages to view real-time traffic statistics.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($topPages as $page): ?>
                        <tr class="hover:bg-gray-50/80 transition">
                            <td class="py-3 px-4 font-mono font-bold text-brand-800">
                                <?= e($page['page_url']) ?>
                            </td>
                            <td class="py-3 px-4 text-gray-900 font-semibold">
                                <?= e($page['page_title']) ?>
                            </td>
                            <td class="py-3 px-4 text-right font-bold text-gray-900">
                                <?= number_format($page['views']) ?>
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-emerald-700 font-bold">
                                <?= number_format($page['unique_visitors']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Notice on Data Integrity -->
    <div class="p-4 rounded-2xl bg-brand-50/60 border border-brand-100 text-xs text-brand-900 flex items-start gap-3">
        <span class="text-base">🛡️</span>
        <div class="space-y-0.5">
            <p class="font-bold">First-Party Privacy Guarantee</p>
            <p class="text-gray-600 leading-relaxed text-[11px]">
                N.A Fresh Fruits visitor analytics operates 100% on your own secure database. No third-party ad networks, no cross-site tracking cookies, no personal form inputs (passwords, OTPs, or payment details) are ever monitored. All IP addresses are irreversibly hashed.
            </p>
        </div>
    </div>

</div>

<!-- Chart.js Trend Rendering Script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('trafficTrendChart');
    if (!ctx) return;

    const labels = <?= json_encode($trend['labels']) ?>;
    const visitors = <?= json_encode($trend['visitors']) ?>;
    const pageviews = <?= json_encode($trend['pageviews']) ?>;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Unique Visitors',
                    data: visitors,
                    borderColor: '#235d3a',
                    backgroundColor: 'rgba(35, 93, 58, 0.08)',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#235d3a',
                    pointRadius: 4
                },
                {
                    label: 'Pageviews',
                    data: pageviews,
                    borderColor: '#34d399',
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    borderDash: [4, 4],
                    tension: 0.35,
                    pointBackgroundColor: '#34d399',
                    pointRadius: 3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1f2937',
                    padding: 10,
                    cornerRadius: 8
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0, font: { size: 10 } },
                    grid: { color: '#f3f4f6' }
                },
                x: {
                    ticks: { font: { size: 10 } },
                    grid: { display: false }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/layout-footer.php'; ?>
