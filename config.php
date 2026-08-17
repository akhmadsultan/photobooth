<?php
/**
 * config.php — Central Configuration for Photobooth System
 * Configure Google Drive API, Supabase credentials, and application parameters.
 */

return [
    // ── Application Settings ─────────────────────────────────
    'app_name'         => 'Photobooth Undersea',
    'app_url'          => 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/photobooth',
    'qr_timer_seconds' => 60,

    // ── Google Drive API Settings ───────────────────────────
    // If credentials are left blank, system falls back to local storage URLs for seamless testing.
    'gdrive' => [
        'enabled'         => false, // Set to true once Client ID / Service Account is configured
        'client_id'       => '',
        'client_secret'   => '',
        'refresh_token'   => '',
        'service_account_json' => __DIR__ . '/gdrive_service_account.json', // optional service account path
        'parent_folder_id'=> '', // ID of the root "Photobooth" folder on Google Drive
    ],

    // ── Supabase Database Settings (Video Mapping) ───────────
    'supabase' => [
        'url'   => 'https://YOUR_PROJECT_ID.supabase.co',
        'key'   => 'YOUR_SUPABASE_ANON_OR_SERVICE_KEY',
        'table' => 'video_mapping' // Name of existing database table
    ]
];
