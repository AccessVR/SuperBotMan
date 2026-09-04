<?php

/**
 * SuperBotMan widget + agent registry configuration. Published as
 * `config/super-botman.php` via:
 *
 *   php artisan vendor:publish --tag=super-botman-config
 */

// Base URL path segment shared by every SuperBotMan route: the agent
// mount point plus the frame, beacon, and console pages. Host apps
// override this to brand their URLs (e.g. 'paloma' → /paloma/chat).
$path = env('SUPER_BOTMAN_PATH', 'super-botman');

return [
    'path' => $path,

    // The route prefix under which SuperBotMan auto-mounts agent
    // endpoints. Each registered agent gets a slug appended:
    // POST {mount}/{slug}, plus conversation history routes when the
    // channel supports them.
    'mount' => '/'.$path,

    // How agent turns run. 'sync' answers the chat POST with the finished
    // reply (simple, but the whole turn lives inside one HTTP request and
    // its execution ceilings). 'queue' dispatches a RunAgentTurn job and
    // acks immediately; the reply broadcasts on the conversation's private
    // channel (config: activity.channel) as super-botman.turn.completed /
    // .failed, with the widget polling conversation history when no
    // websocket connection is available. Queue mode requires a running
    // queue worker and, ideally, broadcasting.
    'dispatch' => env('SUPER_BOTMAN_DISPATCH', 'sync'),

    // Queue (and optionally connection) for RunAgentTurn jobs. Point this
    // at a dedicated queue when workers that don't run this codebase might
    // share the same queue backend (e.g. several checkouts on one
    // database) — a foreign worker that picks up a turn job fails it as an
    // unknown class and the reply is silently lost. Null = default queue.
    'queue' => env('SUPER_BOTMAN_QUEUE'),
    'queue_connection' => env('SUPER_BOTMAN_QUEUE_CONNECTION'),

    // Soft-delete conversations instead of hard-deleting: a user's
    // "delete" sets agent_conversations.deleted_at (the host must add
    // that nullable timestamp column via its own migration) and every
    // package read excludes soft-deleted rows. Off by default so hosts
    // without the column keep working.
    'conversationSoftDeletes' => false,

    // The location of the chat frame URL.
    'frameEndpoint' => '/'.$path.'/chat',

    // Full-screen chat console: a standalone page (sidebar with the
    // user's conversations + a wide transcript) reusing the widget's
    // transport and message components. `consolePage` pins it to one
    // conversational page id from `pages`.
    'consoleEnabled' => true,
    'consoleEndpoint' => '/'.$path.'/console',
    'consolePage' => 'chat',

    // The location of the chat beacon URL.
    'beaconEndpoint' => '/'.$path.'/beacon',

    // Extra middleware for the frame + beacon GET routes, on top of the
    // `web` group. Host apps use this to validate offsite-embed
    // requests: resolve the embed key to a tenant, check the embedding
    // page's origin against an allowlist, mint visitor tokens.
    'frame_middleware' => [],

    // Time format to use.
    'timeFormat' => 'HH:MM',

    // Date-Time format to use.
    'dateTimeFormat' => 'm/d/yy HH:MM',

    // The title to use in the widget.
    'title' => env('SUPER_BOTMAN_WIDGET_TITLE', env('APP_NAME', 'SuperBotMan')),

    // First-visit fallback: if the visitor has no persisted open/closed state
    // yet (truly fresh — no entry in localStorage under their user id), should
    // the widget open itself? Defaults to false so visitors are not greeted by
    // an unsolicited panel. Once the visitor opens or closes the widget, that
    // choice is persisted and this setting is a no-op.
    'openByDefault' => false,

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

    // Color applied to links the agent emits in chat. Defaults to a
    // traditional blue (Tailwind blue-600) that reads well on the
    // white message background; host apps typically override this in
    // their published config to match brand color.
    'linkColor' => '#2563eb',

    // Whether agent-emitted links render with an underline. Combined
    // with `linkColor` to give a familiar "this is a link" cue.
    'linkUnderline' => true,

    // How the widget decides between its light and dark looks.
    //   'light' / 'dark' — pinned to one theme.
    //   'class' — follow a dark class on the host page's <html> element
    //             (the Tailwind class convention; see themeDarkClass).
    //             The widget re-themes live when the class is toggled.
    //   'media' — follow the visitor's OS preference, live.
    // The standalone console page has no host document, so under 'class'
    // and 'media' it follows the OS preference; a ?theme=dark|light query
    // on any frame or console URL overrides everything at boot.
    'theme' => 'light',

    // The class name `theme => 'class'` watches for on the host <html>.
    'themeDarkClass' => 'dark',

    // Per-theme overrides for the widget's neutral palette. Each entry
    // maps a token to a CSS color; tokens are the --sbm-* custom
    // properties without the prefix: page, surface, surface-soft,
    // surface-strong, ink, ink-soft, ink-muted, edge, main, on-main,
    // link-color. Anything not overridden keeps the built-in default for
    // that theme (see resources/css/common.css); `main` defaults to
    // mainColor and `link-color` to linkColor.
    'palette' => [
        'light' => [],
        'dark' => [],
    ],

    // Height to use for embedded videos.
    'videoHeight' => 160,

    // The size of the beacon badge.
    'beaconSize' => 60,

    // Link used for the "about" section in the widget footer.
    'aboutLink' => 'https://github.com/AccessVR/SuperBotMan',

    // Text used for the "about" section in the widget footer.
    'aboutText' => 'Powered by SuperBotMan',
];
