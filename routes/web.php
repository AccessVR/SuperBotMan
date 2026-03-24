<?php

use Illuminate\Http\Request;
use OrchestrateXR\BotManChatSDK\BotManChat;
use OrchestrateXR\BotManChatSDK\Http\Controllers\ConversationHistoryController;

Route::middleware('web')->group(function () {
    Route::get(BotManChat::config('frameEndpoint'), function (Request $request) {
        return BotManChat::view('chat', ['config' => ['isMobile' => $request->isMobile]]);
    })->name('botman-web-widget.chat');

    Route::get(BotManChat::config('beaconEndpoint'), function (Request $request) {
        return BotManChat::view('beacon', ['config' => ['isMobile' => $request->isMobile]]);
    })->name('botman-web-widget.beacon');

    Route::get('/botman/conversations', [ConversationHistoryController::class, 'index']);
    Route::get('/botman/conversations/{id}', [ConversationHistoryController::class, 'show']);
    Route::delete('/botman/conversations/{id}', [ConversationHistoryController::class, 'destroy']);
});
