<?php

namespace OrchestrateXR\SuperBotMan\Channels;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OrchestrateXR\SuperBotMan\AgentRunResult;
use OrchestrateXR\SuperBotMan\ClientActionBag;
use OrchestrateXR\SuperBotMan\Contracts\Channel;
use OrchestrateXR\SuperBotMan\Contracts\SuperBotManConfigurator;
use OrchestrateXR\SuperBotMan\InboundMessage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reference channel implementation for the bundled Vue chat widget.
 * Reads the form-data payload the widget POSTs and returns JSON in
 * the messages[] shape the widget expects (see resources/js/utils.js
 * api() and components/Chat.vue).
 */
class WebChannel implements Channel
{
    public function __construct(protected SuperBotManConfigurator $config) {}

    public function inbound(Request $request): InboundMessage
    {
        $context = json_decode((string) $request->input('context', '{}'), true) ?: [];

        return new InboundMessage(
            message: (string) $request->input('message', ''),
            userId: $this->config->userId(),
            user: $request->user(),
            agentUser: $this->config->agentUser(),
            conversationId: $context['conversationId'] ?? null,
            context: $context,
            attachment: $request->file('attachment'),
            raw: $request->all(),
        );
    }

    public function outbound(AgentRunResult $result, ClientActionBag $actions, InboundMessage $inbound): Response
    {
        $messages = [];

        if ($result->text !== '') {
            $messages[] = [
                'type' => 'text',
                'text' => $this->config->renderAssistantText($result->text),
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
                'title' => $result->conversationTitle ?? Str::limit($inbound->message, 60),
            ],
        ];

        return new JsonResponse(['messages' => $messages]);
    }

    public function middleware(): array
    {
        return ['web'];
    }

    public function endpoints(): array
    {
        return [['POST', '/']];
    }

    public function supportsConversationHistory(): bool
    {
        return true;
    }
}
