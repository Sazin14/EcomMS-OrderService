<?php

return [
    'currency' => env('ORDER_CURRENCY', 'BDT'),

    // Shipping fee added at checkout (the client can NOT send its own price, tax or fee).
    'shipping_fee' => env('ORDER_SHIPPING_FEE', '60.00'),
    'free_shipping_over' => env('ORDER_FREE_SHIPPING_OVER'),     // e.g. 5000.00; empty = never free
    'tax_rate' => (float) env('ORDER_TAX_RATE', 0),               // 0.05 = 5 %

    'max_quantity_per_line' => 20,

    // How long stock stays held for an order nobody has confirmed / paid yet (sent to Inventory as ttl_minutes).
    'cod_hold_minutes' => (int) env('ORDER_COD_HOLD_MINUTES', 1440),      // staff phone the customer within a day
    'online_hold_minutes' => (int) env('ORDER_ONLINE_HOLD_MINUTES', 30),  // customer must pay within 30 minutes
    // After pre-ordered stock arrives, the customer / staff have this long before the order is cancelled.
    'preorder_confirm_window_hours' => (int) env('ORDER_PREORDER_WINDOW_HOURS', 48),

    // The expiry job waits this long after hold_expires_at, so Inventory's own "expired" event normally arrives first.
    'expiry_grace_minutes' => 2,

    'return_window_days' => (int) env('ORDER_RETURN_WINDOW_DAYS', 7),

    // Payment gateway used for "online" orders. "sandbox" is a fake gateway for development.
    'gateway' => [
        'driver' => env('PAYMENT_GATEWAY', 'sandbox'),
        'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET', 'change-me'),
        'payment_url' => env('PAYMENT_PAGE_URL', 'http://localhost:3000/pay'),
    ],
];
