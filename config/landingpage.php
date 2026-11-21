<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Footer "Comunidad" links
    |--------------------------------------------------------------------------
    |
    | Toggles each link of the footer community column. Events, competitions
    | and blog have no page yet, so they stay hidden until they exist. The
    | review panel link depends on the environment while its content grows.
    | The staff panel link is enabled by default and stays independent from
    | AUTH_LOGIN_VISIBLE so the team can reach the main login from the footer.
    | The column is hidden when every link is off.
    |
    */

    'footer' => [
        'community' => [
            'events' => false,
            'competitions' => false,
            'blog' => false,
            'review' => (bool) env('LANDING_REVIEW_LINK_VISIBLE', false),
            'staff_access' => (bool) env('LANDING_STAFF_ACCESS_VISIBLE', true),
        ],
    ],

];
