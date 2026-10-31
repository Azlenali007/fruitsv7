<?php
/**
 * Automated Test Suite: Automatic Invoicing & PDF Generation
 * N.A Fresh Fruits & Coconuts
 */
require_once __DIR__ . '/../app/bootstrap.php';

$pdo = Database::getConnection();
$invPass = 0;
$invFail = 0;

function it_inv(string $name, bool $cond, string $detail = '') {
    global $invPass, $invFail;
    if ($cond) {
        $invPass++;
        echo "  [PASS] {$name}\n";
    } else {
        $invFail++;
        echo "  [FAIL] {$name} - {$detail}\n";
    }
}

echo "========================================================\n";
echo "11. AUTOMATIC INVOICE & PDF GENERATION TESTS\n";
echo "========================================================\n\n";

// 1. INVOICE CREATION FOR AN ORDER
echo "A. ORDER INVOICE CREATION & SEQUENCING:\n";

$prod = Product::getAll()[0];
$testOrderData = [
    'customer_name' => 'Invoice Test Customer',
    'customer_phone' => '9876543210',
    'fulfilment_method' => 'delivery',
    'delivery_address' => '42 Coconut Grove, Bhopal',
    'payment_method' => 'cod',
    'idempotency_key' => 'idem_inv_' . uniqid()
];
$createdOrderRes = Order::createOrder($testOrderData, [['product_id' => $prod['id'], 'quantity' => 2]]);
$orderRecord = Order::getById((int)$createdOrderRes['order_id']);
it_inv("Test order created for invoicing", !empty($orderRecord));

// Create invoice for order
$res = Invoice::createForOrder($orderRecord);
it_inv("createForOrder succeeds", $res['success'] === true);
$invoice = $res['invoice'];

it_inv("Invoice record exists", !empty($invoice) && !empty($invoice['id']));
$currentYear = (int)date('Y');
it_inv("Generated invoice number follows NAF-{YYYY}-{000001} format ({$invoice['invoice_number']})", (bool)preg_match('/^NAF-' . $currentYear . '-\d{6}$/', $invoice['invoice_number']));
it_inv("Invoice links to correct order_id", (int)$invoice['order_id'] === (int)$orderRecord['id']);
it_inv("COD invoice initialized with payment_status containing 'Pending'", str_contains($invoice['payment_status'], 'Pending'));
it_inv("Invoice total matches order total amount", (float)$invoice['total_amount'] === (float)$orderRecord['total_amount']);
it_inv("Invoice snapshot preserves customer name", $invoice['customer_name'] === $testOrderData['customer_name']);

// Duplicate call is idempotent and does not generate second invoice
$dupRes = Invoice::createForOrder($orderRecord);
it_inv("createForOrder is idempotent (returns existing invoice)", (int)$dupRes['invoice']['id'] === (int)$invoice['id'] && $dupRes['is_new'] === false);

// 2. PAYMENT STATUS UPDATE TO PAID
echo "\nB. INVOICE PAYMENT STATUS UPDATE:\n";
$paidUpdate = Invoice::updatePaymentStatus((int)$orderRecord['id'], 'Paid', 'pay_sampleRazorpay123');
it_inv("Invoice payment status updated to Paid", $paidUpdate === true);

$updatedInv = Invoice::getByOrderId((int)$orderRecord['id']);
it_inv("Invoice record now reflects payment_status 'Paid'", $updatedInv['payment_status'] === 'Paid');
it_inv("Invoice records gateway payment_reference", $updatedInv['payment_reference'] === 'pay_sampleRazorpay123');

// 3. PURE NATIVE PHP PDF GENERATION TESTS
echo "\nC. NATIVE PDF STREAM GENERATION TESTS:\n";

$rawPdf = InvoiceService::generatePdfString($orderRecord);
it_inv("generatePdfString returns non-empty binary string", !empty($rawPdf) && strlen($rawPdf) > 500);
it_inv("PDF binary starts with '%PDF-1.4' header", str_starts_with($rawPdf, "%PDF-1.4"));
it_inv("PDF binary contains standard cross-reference table ('xref')", str_contains($rawPdf, "xref"));
it_inv("PDF binary ends with standard '%%EOF' trailer", str_contains($rawPdf, "%%EOF"));
it_inv("PDF contains invoice number text string", str_contains($rawPdf, $invoice['invoice_number']));
it_inv("PDF contains customer name", str_contains($rawPdf, "Invoice Test Customer"));

// 4. ADMIN INVOICE LISTING & FILTERING
echo "\nD. INVOICE MODEL QUERY & PAGINATION TESTS:\n";

$invoicesList = Invoice::getAll(['search' => 'Invoice Test Customer']);
it_inv("Invoice::getAll finds invoice by customer search query", !empty($invoicesList));
$count = Invoice::countAll(['search' => 'Invoice Test Customer']);
it_inv("Invoice::countAll returns matching count", $count >= 1);

// Cleanup test order
$pdo->prepare("DELETE FROM `orders` WHERE `id` = ?")->execute([$orderRecord['id']]);

echo "\n--- INVOICE TEST SUITE SUMMARY: {$invPass} PASSED, {$invFail} FAILED ---\n";
exit($invFail > 0 ? 1 : 0);
