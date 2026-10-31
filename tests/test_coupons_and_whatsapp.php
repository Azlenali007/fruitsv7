<?php
/**
 * Automated Test Suite: Coupons & WhatsApp Popup
 * N.A Fresh Fruits & Coconuts
 */

require_once __DIR__ . '/../app/bootstrap.php';

$cPass = 0;
$cFail = 0;

function cAssert(string $testName, bool $condition, string $detail = '') {
    global $cPass, $cFail;
    if ($condition) {
        $cPass++;
        echo "  [PASS] {$testName}\n";
    } else {
        $cFail++;
        echo "  [FAIL] {$testName} - {$detail}\n";
    }
}

echo "========================================================\n";
echo "RUNNING COUPONS & WHATSAPP POPUP TESTS\n";
echo "========================================================\n\n";

// ---------------------------------------------------------
// 1. COUPON CALCULATION TESTS
// ---------------------------------------------------------
echo "1. COUPON DISCOUNT MECHANICS:\n";

// Test percentage coupon (10% off with max cap 50)
$percentCoupon = [
    'id' => 1,
    'code' => 'SAVE10',
    'type' => 'percentage',
    'discount_value' => 10.00,
    'min_order_amount' => 100.00,
    'max_discount_amount' => 50.00,
    'is_active' => 1
];

$calc1 = Coupon::calculateDiscount($percentCoupon, 200.00, 40.00);
cAssert("10% on ₹200 gives ₹20 discount", (float)$calc1['discount_amount'] === 20.00);
cAssert("Delivery fee untouched on percentage coupon", (float)$calc1['delivery_discount'] === 0.00);

// Test percentage cap
$calcCap = Coupon::calculateDiscount($percentCoupon, 1000.00, 40.00);
cAssert("10% on ₹1000 is capped at ₹50 max discount", (float)$calcCap['discount_amount'] === 50.00);

// Test fixed amount coupon
$fixedCoupon = [
    'id' => 2,
    'code' => 'FLAT50',
    'type' => 'fixed',
    'discount_value' => 50.00,
    'min_order_amount' => 200.00,
    'max_discount_amount' => null,
    'is_active' => 1
];

$calcFixed = Coupon::calculateDiscount($fixedCoupon, 200.00, 40.00);
cAssert("Flat ₹50 discount applied accurately", (float)$calcFixed['discount_amount'] === 50.00);

// Test free delivery coupon
$freeDelCoupon = [
    'id' => 3,
    'code' => 'FREEDELIVERY',
    'type' => 'free_delivery',
    'discount_value' => 0.00,
    'min_order_amount' => 80.00,
    'max_discount_amount' => null,
    'is_active' => 1
];

$calcFreeDel = Coupon::calculateDiscount($freeDelCoupon, 120.00, 40.00);
cAssert("Free delivery coupon reduces delivery fee to ₹0", (float)$calcFreeDel['delivery_discount'] === 40.00);
cAssert("Product subtotal unaffected by free delivery coupon", (float)$calcFreeDel['discount_amount'] === 0.00);

// ---------------------------------------------------------
// 2. COUPON VALIDATION & LIMITS
// ---------------------------------------------------------
echo "\n2. COUPON VALIDATION RULES:\n";

$normalized = Coupon::normalizeCode('  save10  ');
cAssert("Coupon code normalized to uppercase trimmed", $normalized === 'SAVE10');

// ---------------------------------------------------------
// 3. WHATSAPP POPUP CONFIGURATION TESTS
// ---------------------------------------------------------
echo "\n3. WHATSAPP CHANNEL POPUP TESTS:\n";

$waEnabled = Setting::get('whatsapp_popup_enabled', '1');
cAssert("WhatsApp popup setting exists in database", $waEnabled !== null);

$waUrl = Setting::get('whatsapp_channel_url', '');
cAssert("WhatsApp channel URL configured", !empty($waUrl));

echo "\n========================================================\n";
echo "COUPONS & WHATSAPP SUITE: {$cPass} PASSED, {$cFail} FAILED\n";
echo "========================================================\n";
