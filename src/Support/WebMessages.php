<?php

namespace OrchestrateXR\SuperBotMan\Support;

use Illuminate\Support\Str;
use OrchestrateXR\SuperBotMan\AgentRunResult;
use OrchestrateXR\SuperBotMan\ClientActionBag;
use OrchestrateXR\SuperBotMan\Contracts\SuperBotManConfigurator;

/**
 * Builds the widget wire-format messages[] for a finished agent turn —
 * shared by WebChannel::outbound (sync dispatch) and AgentTurnCompleted
 * (queued dispatch), so both paths speak the identical contract.
 */
final class WebMessages
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function for(AgentRunResult $result, ClientActionBag $actions, string $userMessage): array
    {
        $config = app(SuperBotManConfigurator::class);
        $messages = [];

        if ($result->text !== '') {
            $messages[] = [
                'type' => 'text',
                'text' => $config->renderAssistantText($result->text),
            ];
        }

        foreach ($actions->all() as $action) {
            $messages[] = $action->toArray();
        }

        // Always emit a setConversationId client action last so the
        // widget can thread the conversation id back into subsequent
        // requests via its inbound `context` field.
        $messages[] = [
            'type' => 'client_action',
            'action' => 'setConversationId',
            'payload' => [
                'id' => $result->conversationId,
                'title' => $result->conversationTitle ?? Str::limit($userMessage, 60),
            ],
        ];

        return $messages;
    }
}
