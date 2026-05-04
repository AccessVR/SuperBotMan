<?php

/**
 * SuperBotMan widget + agent registry configuration. Published as
 * `config/super-botman.php` via:
 *
 *   php artisan vendor:publish --tag=super-botman-config
 */

return [
    // The route prefix under which SuperBotMan auto-mounts agent
    // endpoints. Each registered agent gets a slug appended:
    // POST {mount}/{slug}, plus conversation history routes when the
    // channel supports them.
    'mount' => '/super-botman',

    // The location of the chat frame URL.
    'frameEndpoint' => '/super-botman/chat',

    // The location of the chat beacon URL.
    'beaconEndpoint' => '/super-botman/beacon',

    // Time format to use.
    'timeFormat' => 'HH:MM',

    // Date-Time format to use.
    'dateTimeFormat' => 'm/d/yy HH:MM',

    // The title to use in the widget.
    'title' => env('SUPER_BOTMAN_WIDGET_TITLE', env('APP_NAME', 'SuperBotMan')),

    // Whether the chat widget should open automatically when the page loads.
    'openByDefault' => true,

    // The default chat page to open when the widget is opened.
    'defaultPage' => 'home',

    // Welcome message every new user sees when the widget is first opened.
    'introMessage' => null,

    // Input placeholder text.
    'placeholderText' => 'Send a message...',

    // Determine if message times should be shown.
    'displayMessageTime' => true,

    // The main color used in the widget header.
    'mainColor' => '#111111',

    // The color to use for the bubble background.
    'bubbleBackground' => '#408591',

    // The image URL to use in the chat bubble.
    'bubbleAvatarUrl' => null,

    // Height of the opened chat widget on desktops.
    'desktopHeight' => 650,

    // Width of the opened chat widget on desktops.
    'desktopWidth' => 375,

    // Height of the opened chat widget on mobile.
    'mobileHeight' => '100%',

    // Width of the opened chat widget on mobile.
    'mobileWidth' => '100%',

    // The color to use for the beacon badge.
    'beaconColor' => '#111111',

    // The color to use for the beacon badge when hovered.
    'beaconColorHover' => '#2b7fff',

    // The label color for the beacon.
    'beaconLabelColor' => '#ffffff',

    // Height to use for embedded videos.
    'videoHeight' => 160,

    // The size of the beacon badge.
    'beaconSize' => 60,

    // Link used for the "about" section in the widget footer.
    'aboutLink' => 'https://github.com/AccessVR/SuperBotMan',

    // Text used for the "about" section in the widget footer.
    'aboutText' => 'Powered by SuperBotMan',
];
