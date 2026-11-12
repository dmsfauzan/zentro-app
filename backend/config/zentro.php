<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seller documents (KYC)
    |--------------------------------------------------------------------------
    |
    | Dokumen seller bersifat sensitif. Simpan pada disk privat (default: local
    | yang root-nya storage/app/private). Di produksi arahkan ke disk S3 privat.
    |
    */

    'documents_disk' => env('SELLER_DOCUMENTS_DISK', 'local'),

    'documents_max_kb' => (int) env('SELLER_DOCUMENT_MAX_KB', 5120),

    'documents_mimes' => ['jpg', 'jpeg', 'png', 'pdf'],

    /*
    |--------------------------------------------------------------------------
    | Product images
    |--------------------------------------------------------------------------
    |
    | Disimpan pada disk yang URL-nya publik. Default lokal: public/uploads/products
    | (tanpa symlink, ramah Windows). Produksi dapat diarahkan ke S3/CDN.
    |
    */

    'product_images_disk' => env('PRODUCT_IMAGES_DISK', 'product_images'),

    'product_images_max_kb' => (int) env('PRODUCT_IMAGE_MAX_KB', 5120),

    'product_images_mimes' => ['jpg', 'jpeg', 'png', 'webp'],

    'product_images_max_per_product' => (int) env('PRODUCT_IMAGES_MAX', 5),

    'product_images_min_dimension' => (int) env('PRODUCT_IMAGE_MIN_DIMENSION', 200),

    'product_images_max_dimension' => (int) env('PRODUCT_IMAGE_MAX_DIMENSION', 4000),

    /*
    |--------------------------------------------------------------------------
    | Catalog cache
    |--------------------------------------------------------------------------
    */

    'catalog_cache_ttl' => (int) env('CATALOG_CACHE_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Shipping (placeholder sampai Phase 6)
    |--------------------------------------------------------------------------
    |
    | Phase 4 memakai tarif tetap per seller agar checkout utuh. Phase 6 akan
    | mengganti implementasi ShippingRateProvider dengan agregator (RajaOngkir/
    | Biteship) tanpa mengubah business logic checkout.
    |
    */

    'shipping' => [
        // manual = seller input resi sendiri (tanpa agregator). fake = test/simulator.
        'driver' => env('SHIPPING_DRIVER', 'manual'),
        'webhook_secret' => env('SHIPPING_WEBHOOK_SECRET', 'zentro-shipping-secret'),
        'flat_rate' => (int) env('SHIPPING_FLAT_RATE', 15000),
        'free_shipping_threshold' => env('SHIPPING_FREE_THRESHOLD') !== null
            ? (int) env('SHIPPING_FREE_THRESHOLD')
            : null,
        'currency' => 'IDR',
    ],

    /*
    |--------------------------------------------------------------------------
    | Order & reservation (Phase 4)
    |--------------------------------------------------------------------------
    */

    'order' => [
        'payment_timeout_hours' => (int) env('ORDER_PAYMENT_TIMEOUT_HOURS', 24),
        'max_items_per_checkout' => (int) env('ORDER_MAX_ITEMS', 50),
        'max_quantity_per_item' => (int) env('ORDER_MAX_QTY_PER_ITEM', 100),
    ],

    'idempotency_ttl_hours' => (int) env('IDEMPOTENCY_TTL_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | KYC wajib
    |--------------------------------------------------------------------------
    |
    | Asumsi default (B-12): KTP wajib, NPWP/NIB opsional. Ubah di sini bila
    | kebijakan bisnis berubah.
    |
    */

    'kyc_required_documents' => ['ktp'],

    /*
    |--------------------------------------------------------------------------
    | Payment (Phase 5)
    |--------------------------------------------------------------------------
    |
    | `driver` memilih gateway aktif. Tanpa kredensial Midtrans, pakai `fake`
    | sehingga seluruh alur (signature, idempotency, commit stok) tetap teruji.
    | Mengaktifkan Midtrans cukup mengganti `PAYMENT_GATEWAY=midtrans` + mengisi
    | kredensial — tidak ada perubahan kode.
    |
    */

    'payment' => [
        'driver' => env('PAYMENT_GATEWAY', 'fake'),
        'default_provider' => env('PAYMENT_PROVIDER', 'midtrans'),
        'timeout_seconds' => (int) env('PAYMENT_TIMEOUT_SECONDS', 15),
        'fake_secret' => env('PAYMENT_FAKE_SECRET', 'zentro-fake-secret'),
        'webhook_ip_allowlist' => array_values(array_filter(explode(',', (string) env('PAYMENT_WEBHOOK_IPS', '')))),

        'midtrans' => [
            'server_key' => env('MIDTRANS_SERVER_KEY'),
            'client_key' => env('MIDTRANS_CLIENT_KEY'),
            // Key untuk verifikasi signature notifikasi; pada akun terbaru berbeda
            // dari server key. Bila kosong, verifikasi jatuh ke server key.
            'signature_key' => env('MIDTRANS_SIGNATURE_KEY'),
            'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
            'snap_base_url' => env('MIDTRANS_SNAP_URL', 'https://app.sandbox.midtrans.com'),
            'api_base_url' => env('MIDTRANS_API_URL', 'https://api.sandbox.midtrans.com'),
            // Metode yang ditawarkan pembeli (Core API /v2/charge, tanpa Snap).
            'methods' => array_values(array_filter(explode(',', (string) env(
                'MIDTRANS_PAYMENTS',
                'qris,gopay,bca_va,bni_va,bri_va,cimb_va,permata_va,mandiri_va',
            )))),
            'timeout' => (int) env('MIDTRANS_TIMEOUT_SECONDS', 15),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Finance: komisi, wallet, payout (Phase 7)
    |--------------------------------------------------------------------------
    */

    'commission' => [
        // Default 5% (B-4). Basis = subtotal produk (ongkir TIDAK termasuk, B-3).
        'default_rate_bp' => (int) env('COMMISSION_DEFAULT_RATE_BP', 500),
        'includes_shipping' => false,
    ],

    'wallet' => [
        'currency' => 'IDR',
    ],

    'payout' => [
        'min_amount' => (int) env('PAYOUT_MIN_AMOUNT', 10000),
        // 0 = dual control nonaktif (B-13, disiapkan).
        'dual_control_threshold' => (int) env('PAYOUT_DUAL_CONTROL_THRESHOLD', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Settlement & order (placeholder fase berikutnya)
    |--------------------------------------------------------------------------
    */

    'settlement_hold_days' => (int) env('SETTLEMENT_HOLD_DAYS', 7),

    'auto_complete_days' => (int) env('AUTO_COMPLETE_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Observability (Phase 8.5)
    |--------------------------------------------------------------------------
    |
    | Ambang request lambat (ms) untuk dicatat sebagai warning, dan channel log
    | yang dipakai untuk log error 5xx / slow request.
    |
    */

    'observability' => [
        'slow_request_ms' => (int) env('OBSERVABILITY_SLOW_REQUEST_MS', 1000),
        'log_channel' => env('OBSERVABILITY_LOG_CHANNEL', 'stack'),
    ],

];
