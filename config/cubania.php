<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Social profiles
    |--------------------------------------------------------------------------
    |
    | Public profile URLs used in the landing navigation, WhatsApp float,
    | footer, and CTA. Leave a value empty to hide that icon.
    |
    */

    'social' => [
        'instagram' => env('CUBANIA_INSTAGRAM_URL', 'https://www.instagram.com/cubania.sc'),
        'tiktok' => env('CUBANIA_TIKTOK_URL', 'https://www.tiktok.com/@cubania.sc'),
        'whatsapp' => env('CUBANIA_WHATSAPP_URL', 'https://wa.me/+584122801334'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Hero background video
    |--------------------------------------------------------------------------
    |
    | YouTube watch URL used as the landing hero background. Playback rate
    | is snapped to a rate YouTube allows: 0.25, 0.5, 0.75, 1, 1.25, 1.5,
    | 1.75, or 2.
    |
    */

    'hero' => [
        'youtube_url' => env('CUBANIA_HERO_YOUTUBE_URL', 'https://www.youtube.com/watch?v=s4DT0BFxDEk'),
        'playback_rate' => (float) env('CUBANIA_HERO_PLAYBACK_RATE', 0.75),
    ],

    /*
    |--------------------------------------------------------------------------
    | Hero instructor cards
    |--------------------------------------------------------------------------
    |
    | Placeholder portraits for the floating hero cards. Replace these URLs
    | with the academy instructor photos when they are ready.
    |
    */

    'instructors' => [
        [
            'image' => env(
                'CUBANIA_INSTRUCTOR_1_IMAGE',
                'https://images.unsplash.com/photo-1518834107812-67b0b7c58434?auto=format&fit=crop&w=800&q=80',
            ),
        ],
        [
            'image' => env(
                'CUBANIA_INSTRUCTOR_2_IMAGE',
                'https://images.unsplash.com/photo-1547153760-18fc86324498?auto=format&fit=crop&w=800&q=80',
            ),
        ],
        [
            'image' => env(
                'CUBANIA_INSTRUCTOR_3_IMAGE',
                'https://images.unsplash.com/photo-1508700115892-45ecd05ae2ad?auto=format&fit=crop&w=800&q=80',
            ),
        ],
    ],

];
