<?php
/**
 * Admin Delivery Area Create / Edit Form (Clean Non-Map Architecture)
 * N.A Fresh Fruits & Coconuts
 */
$isEdit = !empty($area['id']);
$adminTitle = ($isEdit ? 'Edit Area: ' . e($area['name']) : 'Add Delivery Area') . ' - N.A Fresh Fruits & Coconuts';
$activeAdminTab = 'areas';
require_once __DIR__ . '/layout-header.php';

$actionUrl = $isEdit ? '/admin/areas/update' : '/admin/areas/store';
$initialGeoJson = !empty($area['boundary_geojson']) ? $area['boundary_geojson'] : '';
?>

<div class="max-w-2xl mx-auto space-y-6">

    <!-- Header with Back Link -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="/admin/areas" class="p-2 text-gray-500 hover:text-gray-900 bg-white rounded-xl border border-gray-200 transition" aria-label="Back to areas">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <div>
                <h1 class="text-2xl font-black text-gray-900 tracking-tight">
                    <?= $isEdit ? 'Edit Delivery Area' : 'Add New Delivery Area' ?>
                </h1>
                <p class="text-xs text-gray-500">Configure delivery locality name, charge, and customer activation status</p>
            </div>
        </div>
    </div>

    <form action="<?= $actionUrl ?>" method="POST" id="areaForm" class="space-y-6">
        <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
        <?php if ($isEdit): ?>
        <input type="hidden" name="area_id" value="<?= $area['id'] ?>">
        <?php endif; ?>
        <input type="hidden" name="boundary_geojson" value="<?= e($initialGeoJson) ?>">

        <!-- Basic Details Card -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-xs space-y-6">
            <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                <h2 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Locality & Delivery Settings</h2>
                <span class="text-[10px] font-bold bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-full border border-emerald-200">Jaora (MP)</span>
            </div>

            <div class="space-y-5">
                <!-- Area Name -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1.5">
                        Locality / Area Name <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="name" 
                        required 
                        value="<?= e($area['name'] ?? '') ?>" 
                        placeholder="e.g. Shastri Colony, Station Road, Topkhana" 
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-xs text-gray-900 font-bold focus:ring-2 focus:ring-brand-500 focus:outline-none transition"
                    >
                    <span class="text-[10px] text-gray-400 mt-1 block">Displayed to customers at checkout and printed on order bills & invoices</span>
                </div>

                <!-- Delivery Charge -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1.5">
                        Delivery Charge (₹) <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-2.5 font-bold text-gray-400 text-xs">₹</span>
                        <input 
                            type="number" 
                            step="0.01" 
                            name="delivery_charge" 
                            required 
                            min="0"
                            value="<?= number_format((float)($area['delivery_charge'] ?? 40.00), 2, '.', '') ?>" 
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-8 pr-3 py-2.5 text-xs text-gray-900 font-bold focus:ring-2 focus:ring-brand-500 focus:outline-none transition"
                        >
                    </div>
                    <span class="text-[10px] text-gray-400 mt-1 block">Standard doorstep delivery fee applied when a customer selects this locality</span>
                </div>

                <!-- Status Toggle -->
                <div class="pt-3 border-t border-gray-100 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-bold text-gray-900 block">Area Activation Status</span>
                        <span class="text-[11px] text-gray-500">When active, customers in this zone can choose this locality during checkout.</span>
                    </div>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input 
                            type="checkbox" 
                            name="status" 
                            value="active" 
                            <?= ($area['status'] ?? 'inactive') === 'active' ? 'checked' : '' ?> 
                            class="w-4 h-4 text-brand-600 rounded-sm focus:ring-brand-500"
                        >
                        <span class="text-xs font-bold text-gray-700">Active</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Submit & Cancel Buttons -->
        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="/admin/areas" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold py-3 px-5 rounded-xl transition">
                Cancel
            </a>
            <button 
                type="submit" 
                class="bg-brand-600 hover:bg-brand-700 text-white text-xs font-bold py-3 px-6 rounded-xl shadow-md transition"
            >
                <?= $isEdit ? 'Save Changes' : 'Create Delivery Area' ?>
            </button>
        </div>
    </form>

</div>

<?php require_once __DIR__ . '/layout-footer.php'; ?>
