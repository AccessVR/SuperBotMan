<?php

namespace OrchestrateXR\SuperBotMan\Channels;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OrchestrateXR\SuperBotMan\AgentRunResult;
use OrchestrateXR\SuperBotMan\ClientActionBag;
use OrchestrateXR\SuperBotMan\Contracts\Channel;
use OrchestrateXR\SuperBotMan\Contracts\SuperBotManConfigurator;
use OrchestrateXR\SuperBotMan\InboundMessage;
use OrchestrateXR\SuperBotMan\Support\WebMessages;
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
        return new JsonResponse([
            'messages' => WebMessages::for($result, $actions, $inbound->message),
        ]);
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
