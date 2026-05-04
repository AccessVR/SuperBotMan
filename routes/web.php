<?php

use Illuminate\Http\Request;
use OrchestrateXR\SuperBotMan\Facades\SuperBotMan;

Route::middleware('web')->group(function () {
    Route::get(SuperBotMan::config('frameEndpoint'), function (Request $request) {
        return SuperBotMan::view('chat', ['config' => ['isMobile' => $request->isMobile]]);
    })->name('super-botman.chat');

    Route::get(SuperBotMan::config('beaconEndpoint'), function (Request $request) {
        return SuperBotMan::view('beacon', ['config' => ['isMobile' => $request->isMobile]]);
    })->name('super-botman.beacon');

    // Agent endpoint routes and conversation-history routes are
    // registered by SuperBotManServiceProvider after the agent
    // registry has been populated. Added in commit 2.
});
