<?php

use OrchestrateXR\SuperBotMan\Facades\SuperBotMan;

Route::middleware('web')->group(function () {
    Route::get(SuperBotMan::config('frameEndpoint'), function () {
        return SuperBotMan::view('chat', ['config' => []]);
    })->name('super-botman.chat');

    Route::get(SuperBotMan::config('beaconEndpoint'), function () {
        return SuperBotMan::view('beacon', ['config' => []]);
    })->name('super-botman.beacon');

    // Agent endpoint routes and conversation-history routes are
    // registered by SuperBotManServiceProvider after the agent
    // registry has been populated. Added in commit 2.
});
