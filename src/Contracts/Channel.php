<?php

namespace OrchestrateXR\SuperBotMan\Contracts;

use Illuminate\Http\Request;
use OrchestrateXR\SuperBotMan\AgentRunResult;
use OrchestrateXR\SuperBotMan\ClientActionBag;
use OrchestrateXR\SuperBotMan\InboundMessage;
use Symfony\Component\HttpFoundation\Response;

/**
 * The seam between a transport-specific HTTP request (Web widget,
 * Slack, Discord, etc.) and the agent dispatcher. A channel knows how
 * to translate inbound requests into a normalized InboundMessage and
 * how to format an AgentRunResult back into the transport's expected
 * response shape.
 *
 * Channels are stateless — implementations are resolved fresh from
 * the container on each request.
 */
interface Channel
{
    /**
     * Translate the transport-specific request into a normalized
     * InboundMessage the controller can hand to the agent dispatcher.
     */
    public function inbound(Request $request): InboundMessage;

    /**
     * Serialize an agent run plus any emitted client actions back
     * into the transport's expected HTTP response.
     */
    public function outbound(AgentRunResult $result, ClientActionBag $actions, InboundMessage $inbound): Response;

    /**
     * Route middleware to apply to this channel's auto-mounted endpoints.
     * WebChannel: ['web']. SlackChannel: ['api', 'slack.signature']. Etc.
     *
     * @return string[]
     */
    public function middleware(): array;

    /**
     * HTTP verb + path-suffix pairs the channel exposes under each
     * registered agent's mount point. Most channels return a single
     * [['POST', '/']]. Slack might add [['POST', '/events'], ['POST', '/interactive']].
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public function endpoints(): array;

    /**
     * Whether SuperBotMan should auto-register conversation history
     * routes (list/show/destroy) under this channel's mount point.
     * Web: true. Stateless transports like Slack: false.
     */
    public function supportsConversationHistory(): bool;
}
