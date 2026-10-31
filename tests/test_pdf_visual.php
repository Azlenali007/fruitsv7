<?php
/**
 * Test PDF Generation & Visual Inspection
 * Generates test PDFs with real edge cases and renders to PNG via Ghostscript
 */
require_once __DIR__ . '/../app/bootstrap.php';

// Prepare test order scenarios
$testScenarios = [
    'single_item_cod' => [
        'order' => [
            'id' => 101,
            'order_number' => 'NA1024',
            'customer_name' => 'Rahul Sharma',
            'customer_phone' => '+91 98765 43210',
            'delivery_address' => 'Flat 402, Green Valley Apartments, Near City Hospital, Station Road, Jaora - 457226',
            'delivery_area_name' => 'Station Road Area, Jaora',
            'subtotal' => 80.00,
            'discount_amount' => 0.00,
            'delivery_charge' => 30.00,
            'total_amount' => 110.00,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'order_status' => 'confirmed'
        ],
        'invoice' => [
            'invoice_number' => 'NAF-2026-000001',
            'invoice_date' => date('Y-m-d H:i:s'),
            'customer_name' => 'Rahul Sharma',
            'customer_phone' => '+91 98765 43210',
            'customer_email' => 'rahul.sharma@example.com',
            'delivery_address' => 'Flat 402, Green Valley Apartments, Near City Hospital, Station Road, Jaora - 457226',
            'delivery_area_name' => 'Station Road Area, Jaora',
            'subtotal' => 80.00,
            'discount_amount' => 0.00,
            'delivery_charge' => 30.00,
            'total_amount' => 110.00,
            'payment_method' => 'Cash on Delivery',
            'payment_status' => 'Payment Pending (Payable on Delivery)',
            'payment_reference' => 'COD-NA1024'
        ],
        'items' => [
            [
                'product_name' => 'Fresh Tender Coconut Water (Sweet & Natural)',
                'unit_price' => 40.00,
                'quantity' => 2,
                'subtotal' => 80.00
            ]
        ]
    ],

    'multi_item_coupon_long_name' => [
        'order' => [
            'id' => 102,
            'order_number' => 'NA1025',
            'customer_name' => 'Dr. Meenakshi Sundaram Iyer',
            'customer_phone' => '+91 98220 12345',
            'delivery_address' => 'House No. 12/B, Orchid Residency Phase 2, Behind Jain Temple, Near Old Grain Market, Jaora, Ratlam District, MP - 457226',
            'delivery_area_name' => 'Grain Market & Old City, Jaora',
            'subtotal' => 380.00,
            'discount_amount' => 38.00,
            'delivery_charge' => 0.00,
            'total_amount' => 342.00,
            'payment_method' => 'online',
            'payment_status' => 'captured',
            'order_status' => 'confirmed'
        ],
        'invoice' => [
            'invoice_number' => 'NAF-2026-000002',
            'invoice_date' => date('Y-m-d H:i:s'),
            'customer_name' => 'Dr. Meenakshi Sundaram Iyer',
            'customer_phone' => '+91 98220 12345',
            'customer_email' => 'm.iyer@healthcare.org',
            'delivery_address' => 'House No. 12/B, Orchid Residency Phase 2, Behind Jain Temple, Near Old Grain Market, Jaora, Ratlam District, MP - 457226',
            'delivery_area_name' => 'Grain Market & Old City, Jaora',
            'subtotal' => 380.00,
            'discount_amount' => 38.00,
            'delivery_charge' => 0.00,
            'total_amount' => 342.00,
            'payment_method' => 'Razorpay Online',
            'payment_status' => 'Paid',
            'payment_reference' => 'pay_Pr9xK49dZ8mN12'
        ],
        'items' => [
            [
                'product_name' => 'Fresh Tender Coconut Water - 100% Pure Natural Electrolyte Rich Farm Harvested',
                'unit_price' => 40.00,
                'quantity' => 4,
                'subtotal' => 160.00
            ],
            [
                'product_name' => 'Cold-Pressed Fresh Raw Coconut Water Bottle (Pack of 2, 500ml each)',
                'unit_price' => 90.00,
                'quantity' => 2,
                'subtotal' => 180.00
            ],
            [
                'product_name' => 'Tender Coconut Malai / Pulp Dessert Cup (Natural, No Sugar)',
                'unit_price' => 40.00,
                'quantity' => 1,
                'subtotal' => 40.00
            ]
        ]
    ]
];

@mkdir(__DIR__ . '/../storage/test_pdfs', 0777, true);

foreach ($testScenarios as $key => $sc) {
    echo "Generating PDF for scenario: {$key}...\n";
    $pdfData = InvoiceService::generatePdfString($sc['invoice'], $sc['order'], $sc['items']);
    $pdfPath = __DIR__ . "/../storage/test_pdfs/{$key}.pdf";
    file_put_contents($pdfPath, $pdfData);
    echo "Saved PDF: {$pdfPath} (" . strlen($pdfData) . " bytes)\n";

    // Render to PNG via Ghostscript
    $pngPath = __DIR__ . "/../storage/test_pdfs/{$key}_p%d.png";
    $cmd = "gs -dNOPAUSE -dBATCH -sDEVICE=png16m -r150 -sOutputFile=" . escapeshellarg($pngPath) . " " . escapeshellarg($pdfPath) . " 2>&1";
    exec($cmd, $out, $ret);
    echo "Ghostscript conversion return: {$ret}\n";
}

echo "PDF Visual Test complete.\n";
