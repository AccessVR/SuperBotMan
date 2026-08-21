<?php

use Illuminate\Http\Request;
use OrchestrateXR\SuperBotMan\Facades\SuperBotMan;
use OrchestrateXR\SuperBotMan\Http\Controllers\EmbedController;

// The embed loader is deliberately OUTSIDE the web group: it is a
// public, cacheable script for third-party pages — it must not start a
// session per page view of every site embedding the widget, and a
// cross-site <script src> would not send cookies anyway.
Route::get(rtrim((string) config('super-botman.mount', '/chat'), '/').'/embed/{key}.js', EmbedController::class)
    ->where('key', '[A-Za-z0-9_]+')
    ->name('super-botman.embed');

// Host apps append validation middleware for offsite embeds (resolve
// the embed key, check allowed domains, mint visitor tokens) via
// config('super-botman.frame_middleware').
Route::middleware(array_merge(['web'], (array) config('super-botman.frame_middleware', [])))->group(function () {
    Route::get(SuperBotMan::config('frameEndpoint'), function (Request $request) {
        return SuperBotMan::view('chat', ['config' => SuperBotMan::frameOverrides($request)]);
    })->name('super-botman.chat');

    Route::get(SuperBotMan::config('beaconEndpoint'), function (Request $request) {
        return SuperBotMan::view('beacon', ['config' => SuperBotMan::frameOverrides($request)]);
    })->name('super-botman.beacon');

    // Agent endpoint routes and conversation-history routes are
    // registered by SuperBotManServiceProvider after the agent
    // registry has been populated. Added in commit 2.
});
