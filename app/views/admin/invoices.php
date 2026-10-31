<?php
/**
 * Admin Invoices Management View
 * N.A Fresh Fruits & Coconuts
 */

$adminTitle = 'Invoices & Billing - N.A Fresh Fruits & Coconuts';
$activeAdminTab = 'invoices';
require_once __DIR__ . '/layout-header.php';

$search = trim($_GET['search'] ?? '');
$paymentStatus = $_GET['payment_status'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$filters = [
    'search' => $search,
    'payment_status' => $paymentStatus,
    'date_from' => $dateFrom,
    'date_to' => $dateTo
];

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$totalCount = Invoice::countAll($filters);
$invoices = Invoice::getAll($filters, $limit, $offset);
$totalPages = ceil($totalCount / $limit);
?>

<div class="space-y-6">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-gray-900 tracking-tight">Invoices & Billing Ledger</h1>
            <p class="text-xs text-gray-500">Search, view, and download professional A4 customer invoices and tax bills</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs font-bold text-gray-500 bg-white px-3 py-1.5 rounded-xl border border-gray-200">
                Total Invoices: <span class="text-brand-600"><?= $totalCount ?></span>
            </span>
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-xs">
        <form action="/admin/invoices" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 text-xs">
            <!-- Search -->
            <div class="lg:col-span-2">
                <label class="block font-bold text-gray-700 uppercase mb-1">Search</label>
                <div class="relative">
                    <input 
                        type="text" 
                        name="search" 
                        value="<?= e($search) ?>" 
                        placeholder="Invoice #, Order #, Name or Phone..."
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-9 pr-3 py-2 font-medium focus:ring-2 focus:ring-brand-500 focus:outline-none"
                    >
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
            </div>

            <!-- Payment Status -->
            <div>
                <label class="block font-bold text-gray-700 uppercase mb-1">Payment Status</label>
                <select name="payment_status" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 font-medium focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    <option value="all" <?= $paymentStatus === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="paid" <?= $paymentStatus === 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="pending" <?= $paymentStatus === 'pending' ? 'selected' : '' ?>>Pending / COD</option>
                </select>
            </div>

            <!-- Date From -->
            <div>
                <label class="block font-bold text-gray-700 uppercase mb-1">From Date</label>
                <input type="date" name="date_from" value="<?= e($dateFrom) ?>" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 font-medium focus:ring-2 focus:ring-brand-500 focus:outline-none">
            </div>

            <!-- Date To / Buttons -->
            <div>
                <label class="block font-bold text-gray-700 uppercase mb-1">To Date</label>
                <div class="flex gap-2">
                    <input type="date" name="date_to" value="<?= e($dateTo) ?>" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 font-medium focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-bold px-4 py-2 rounded-xl transition shrink-0">
                        Filter
                    </button>
                    <?php if (!empty($search) || $paymentStatus !== 'all' || !empty($dateFrom) || !empty($dateTo)): ?>
                    <a href="/admin/invoices" class="p-2 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-xl transition flex items-center justify-center shrink-0" title="Clear Filters">
                        ✕
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Invoices Table -->
    <div class="bg-white rounded-3xl border border-gray-100 shadow-xs overflow-hidden">
        <?php if (empty($invoices)): ?>
        <div class="p-12 text-center">
            <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-emerald-50 text-brand-600 flex items-center justify-center text-2xl font-bold">
                🧾
            </div>
            <h3 class="text-base font-bold text-gray-900 mb-1">No Invoices Found</h3>
            <p class="text-xs text-gray-500 max-w-sm mx-auto">
                <?= (!empty($search) || $paymentStatus !== 'all') ? 'No invoices match your search filters. Try clearing your query.' : 'Invoices are automatically generated when customer orders are placed or verified.' ?>
            </p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-gray-50/80 border-b border-gray-100 text-[11px] font-bold uppercase text-gray-400 tracking-wider">
                    <tr>
                        <th class="py-3.5 px-4 sm:px-6">Invoice #</th>
                        <th class="py-3.5 px-4">Order Ref</th>
                        <th class="py-3.5 px-4">Customer</th>
                        <th class="py-3.5 px-4">Date</th>
                        <th class="py-3.5 px-4">Amount</th>
                        <th class="py-3.5 px-4">Payment</th>
                        <th class="py-3.5 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($invoices as $inv): 
                        $isPaid = str_contains(strtolower($inv['payment_status']), 'paid') && !str_contains(strtolower($inv['payment_status']), 'pending');
                    ?>
                    <tr class="hover:bg-gray-50/50 transition">
                        <td class="py-4 px-4 sm:px-6">
                            <span class="font-mono font-bold text-gray-900 text-xs sm:text-sm">
                                <?= e($inv['invoice_number']) ?>
                            </span>
                        </td>
                        <td class="py-4 px-4">
                            <a href="/admin/orders/view?id=<?= $inv['order_id'] ?>" class="text-brand-600 hover:text-brand-700 font-bold hover:underline">
                                #<?= e($inv['order_number'] ?: $inv['order_id']) ?>
                            </a>
                        </td>
                        <td class="py-4 px-4">
                            <p class="font-bold text-gray-900"><?= e($inv['customer_name']) ?></p>
                            <p class="text-[11px] text-gray-400"><?= e($inv['customer_phone']) ?></p>
                        </td>
                        <td class="py-4 px-4 text-gray-500">
                            <?= date('d M Y, h:i A', strtotime($inv['invoice_date'])) ?>
                        </td>
                        <td class="py-4 px-4 font-black text-gray-900">
                            ₹<?= number_format((float)$inv['total_amount'], 2) ?>
                        </td>
                        <td class="py-4 px-4">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold <?= $isPaid ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-amber-50 text-amber-800 border border-amber-200' ?>">
                                <?= $isPaid ? '✓ Paid' : '⏱ ' . e($inv['payment_status']) ?>
                            </span>
                            <span class="block text-[10px] text-gray-400 mt-0.5">
                                <?= e($inv['payment_method']) ?>
                            </span>
                        </td>
                        <td class="py-4 px-4 text-right space-x-1.5 whitespace-nowrap">
                            <a 
                                href="/admin/invoices/view?id=<?= $inv['id'] ?>" 
                                class="inline-flex items-center gap-1 bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold text-[11px] px-2.5 py-1.5 rounded-lg transition"
                                title="View / Print Invoice"
                            >
                                👁 View
                            </a>
                            <a 
                                href="/admin/invoices/download?id=<?= $inv['id'] ?>" 
                                class="inline-flex items-center gap-1 bg-brand-50 hover:bg-brand-100 text-brand-700 font-bold text-[11px] px-2.5 py-1.5 rounded-lg border border-brand-200 transition"
                                title="Download PDF"
                            >
                                📥 PDF
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between text-xs">
            <span class="text-gray-500">
                Page <?= $page ?> of <?= $totalPages ?> (Total <?= $totalCount ?> invoices)
            </span>
            <div class="flex gap-2">
                <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($filters, ['page' => $page - 1])) ?>" class="px-3 py-1.5 bg-white border border-gray-200 rounded-lg font-bold hover:bg-gray-50">
                    &larr; Previous
                </a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                <a href="?<?= http_build_query(array_merge($filters, ['page' => $page + 1])) ?>" class="px-3 py-1.5 bg-white border border-gray-200 rounded-lg font-bold hover:bg-gray-50">
                    Next &rarr;
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/layout-footer.php'; ?>
