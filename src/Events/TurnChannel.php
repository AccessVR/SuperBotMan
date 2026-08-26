<?php

namespace OrchestrateXR\SuperBotMan\Events;

/**
 * Resolves the per-conversation broadcast channel name the turn events
 * share with the host's activity narration — one channel per
 * conversation, one auth rule, configured once.
 */
final class TurnChannel
{
    public static function name(string $conversationId): string
    {
        $pattern = (string) config('super-botman.activity.channel', 'SuperBotMan.Conversation.{conversationId}');

        return str_replace('{conversationId}', $conversationId, $pattern);
    }
}
