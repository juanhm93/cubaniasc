<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hosted Deployment Targets
    |--------------------------------------------------------------------------
    |
    | These environments are published over FTP from GitHub Actions. The
    | develop branch deploys to the Purphura subdomain (staging) and main
    | deploys to cubaniasc.com (production). Secret names match GitHub
    | repository secrets — values stay in GitHub, never in this file.
    |
    */

    'environments' => [

        'staging' => [
            'branch' => 'develop',
            'app_url' => env('STAGING_APP_URL', 'https://cubania.purphura.com/'),
            'ftp_server_dir' => env('FTP_STAGING_SERVER_DIR', '/'),
            'secrets' => [
                'server' => 'FTP_STAGING_SERVER',
                'username' => 'FTP_STAGING_USERNAME',
                'password' => 'FTP_STAGING_PASSWORD',
                'server_dir' => 'FTP_STAGING_SERVER_DIR',
            ],
        ],

        'production' => [
            'branch' => 'main',
            'app_url' => env('PRODUCTION_APP_URL', 'https://cubaniasc.com'),
            'ftp_server_dir' => env('FTP_PRODUCTION_SERVER_DIR', '/'),
            'secrets' => [
                'server' => 'FTP_PRODUCTION_SERVER',
                'username' => 'FTP_PRODUCTION_USERNAME',
                'password' => 'FTP_PRODUCTION_PASSWORD',
                'server_dir' => 'FTP_PRODUCTION_SERVER_DIR',
            ],
        ],

    ],

];
