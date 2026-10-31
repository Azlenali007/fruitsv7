<?php
/**
 * Benchmark Checkout & Order Placement
 * Measures exact execution timing for every step
 */
require_once __DIR__ . '/../app/bootstrap.php';

$t0 = microtime(true);

echo "Starting checkout benchmark...\n";

// 1. Customer Auth
$tStep0 = microtime(true);
$customer = Customer::findByPhone('9876543210');
if (!$customer) {
    $cId = Customer::create([
        'name' => 'Speed Test Customer',
        'phone' => '9876543210',
        'password' => 'Pass@123',
        'address' => '42 Test Colony, Jaora'
    ]);
    $customer = Customer::findById($cId);
}
$_SESSION[CustomerAuthService::SESSION_CUSTOMER_ID] = $customer['id'];
$_SESSION[CustomerAuthService::SESSION_CUSTOMER_NAME] = $customer['name'];
$_SESSION[CustomerAuthService::SESSION_CUSTOMER_PHONE] = $customer['phone'];
$tAuth = (microtime(true) - $tStep0) * 1000;
echo sprintf("Step 1 - Customer Auth: %.2f ms\n", $tAuth);

// 2. Cart setup
$tStep1 = microtime(true);
$products = Product::getAll();
$prod = $products[0];
CartService::clear();
CartService::addItem((int)$prod['id'], 2);
$cart = CartService::getCart();
$tCart = (microtime(true) - $tStep1) * 1000;
echo sprintf("Step 2 - Cart Setup & Validation: %.2f ms\n", $tCart);

// 3. Area eligibility check
$tStep2 = microtime(true);
$eligibility = AreaService::checkDeliveryEligibility(null, null, null);
$tArea = (microtime(true) - $tStep2) * 1000;
echo sprintf("Step 3 - Delivery Eligibility Check: %.2f ms\n", $tArea);

// 4. Order creation via OrderService
$tStep3 = microtime(true);
$postData = [
    CSRF_TOKEN_KEY => csrf_token(),
    'customer_name' => $customer['name'],
    'customer_phone' => $customer['phone'],
    'fulfilment_method' => 'delivery',
    'delivery_address' => '101 Coconut Avenue, Jaora',
    'payment_method' => 'cod',
    'idempotency_key' => 'speed_' . uniqid() . '_' . microtime(true)
];

$res = OrderService::processCheckout($postData);
$tOrderService = (microtime(true) - $tStep3) * 1000;
echo sprintf("Step 4 - OrderService::processCheckout: %.2f ms\n", $tOrderService);

$totalTime = (microtime(true) - $t0) * 1000;
echo sprintf("TOTAL ORDER PLACEMENT TIME: %.2f ms (%.3f seconds)\n", $totalTime, $totalTime / 1000);

if ($res['success']) {
    echo "SUCCESS: Created order #" . $res['order_number'] . " (ID: " . $res['order_id'] . ")\n";
} else {
    echo "FAILED: " . ($res['error'] ?? 'Unknown error') . "\n";
}
