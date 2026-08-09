<?php

namespace OrchestrateXR\SuperBotMan\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\AiManager;
use Laravel\Ai\Messages\UserMessage;
use Throwable;

/**
 * Backfills the title of a conversation that was minted empty by the
 * prepare endpoint (ConversationsController::prepare). Runs after the
 * response is sent, so title generation — an extra LLM call the Laravel AI
 * SDK otherwise performs synchronously inside the first turn — never delays
 * the first reply. Replicates the SDK's RememberConversation titling
 * (cheapest model, 3-5 word title, truncation fallback).
 */
class ConversationTitler
{
    public static function backfillAfterResponse(string $conversationId, string $firstMessage): void
    {
        if ($conversationId === '' || trim($firstMessage) === '') {
            return;
        }

        app()->terminating(function () use ($conversationId, $firstMessage) {
            self::backfill($conversationId, $firstMessage);
        });
    }

    public static function backfill(string $conversationId, string $firstMessage): void
    {
        $table = config('super-botman.agent_conversations_table', 'agent_conversations');

        $untitled = DB::table($table)
            ->where('id', $conversationId)
            ->where('title', '')
            ->exists();

        if (! $untitled) {
            return;
        }

        DB::table($table)
            ->where('id', $conversationId)
            ->where('title', '')
            ->update(['title' => self::generate($firstMessage), 'updated_at' => now()]);
    }

    protected static function generate(string $message): string
    {
        if (! class_exists(AiManager::class)) {
            return Str::limit($message, 100, preserveWords: true);
        }

        try {
            $provider = app(AiManager::class)->textProvider();

            $response = $provider->textGateway()->generateText(
                $provider,
                $provider->cheapestTextModel(),
                'Generate a concise 3-5 word title for a conversation that starts with the following message. Respond with only the title, no quotes or punctuation.',
                [new UserMessage(Str::limit($message, 500))],
            );

            return Str::limit(trim((string) $response->text), 100) ?: Str::limit($message, 100, preserveWords: true);
        } catch (Throwable) {
            return Str::limit($message, 100, preserveWords: true);
        }
    }
}
