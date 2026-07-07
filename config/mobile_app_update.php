<?php

return [
    'android' => [
        'package_name' => env('APP_UPDATE_ANDROID_PACKAGE_NAME', 'app.abhibs.locatoremployee'),
        'latest_version' => env('APP_UPDATE_ANDROID_LATEST_VERSION', '5.0.11'),
        'latest_build_number' => (int) env('APP_UPDATE_ANDROID_LATEST_BUILD', 5012),
        'minimum_supported_build_number' => (int) env('APP_UPDATE_ANDROID_MIN_SUPPORTED_BUILD', 5012),
        'force_update' => (bool) env('APP_UPDATE_ANDROID_FORCE', true),
        'download_url' => env(
            'APP_UPDATE_ANDROID_DOWNLOAD_URL',
            '/downloads/attica-attendance-5.0.11-5012.apk'
        ),
        'title' => env('APP_UPDATE_ANDROID_TITLE', 'Update required'),
        'message' => env(
            'APP_UPDATE_ANDROID_MESSAGE',
            'A newer version of Attica Attendance is available. Please update the app to continue.'
        ),
        'release_notes' => preg_split('/\r\n|\r|\n/', (string) env('APP_UPDATE_ANDROID_RELEASE_NOTES', '')) ?: [],
    ],
];
