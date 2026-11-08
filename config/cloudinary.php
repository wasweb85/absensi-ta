<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cloudinary Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi Cloudinary untuk penyimpanan berkas dan gambar invoice SPP.
    | Mendukung konfigurasi via CLOUDINARY_URL lengkap atau kredensial individual.
    |
    */

    'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
    'api_key' => env('CLOUDINARY_API_KEY'),
    'api_secret' => env('CLOUDINARY_API_SECRET'),
    'url' => env('CLOUDINARY_URL'),
    'secure' => (bool) env('CLOUDINARY_SECURE', true),
    'folder' => env('CLOUDINARY_FOLDER', 'tpq_invoices'),
];

