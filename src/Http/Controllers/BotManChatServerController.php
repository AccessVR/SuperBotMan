<?php

namespace OrchestrateXR\BotManChatSDK\Http\Controllers;

use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Cache\LaravelCache;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\Drivers\Web\WebDriver;
use Illuminate\Http\Request;
use OrchestrateXR\BotManChatSDK\BotManChat;
use OrchestrateXR\BotManChatSDK\Conversations\ChatConversation;
use OrchestrateXR\BotManChatSDK\Models\ChatConversation as ChatConversationModel;

class BotManChatServerController
{
    public function listen(Request $request)
    {
        DriverManager::loadDriver(WebDriver::class);

        $botman = BotManFactory::create([], new LaravelCache);

        $context = json_decode($request->input('context', '{}'), true) ?: [];
        $conversationId = $context['conversationId'] ?? null;
        $pageId = $context['pageId'] ?? 'default';
        $userId = BotManChat::userId();

        $history = null;
        if ($conversationId) {
            $history = ChatConversationModel::forUser($userId)
                ->with('messages')
                ->find($conversationId);
        }

        $botman->hears(BotManChat::ANYTHING, function ($bot, $prompt) use ($userId, $pageId, $history) {
            $conversation = ChatConversation::make($prompt)
                ->forUserId($userId)
                ->forPage($pageId)
                ->withCrawler();

            if ($history) {
                $conversation->loadFromHistory($history);
                $conversation->user($prompt);
            }

            $bot->startConversation($conversation);
        });

        $botman->listen();
    }
}
