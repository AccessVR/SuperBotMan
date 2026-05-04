<?php

namespace OrchestrateXR\SuperBotMan\Dispatchers;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Responses\AgentResponse;
use OrchestrateXR\SuperBotMan\AgentRunResult;
use OrchestrateXR\SuperBotMan\Contracts\AgentDispatcher;

/**
 * Default dispatcher backed by the official Laravel AI SDK
 * (laravel/ai). The SDK's API: agent classes implement
 * Laravel\Ai\Contracts\Agent (typically via the Promptable trait,
 * plus RemembersConversations for chat history). The conversation
 * methods forUser($user) / continue($id, as: $user) live on the
 * RemembersConversations trait — instance methods, not static.
 *
 * If laravel/ai isn't installed (or a host app uses a different
 * agent framework), bind a different AgentDispatcher implementation
 * in the container.
 */
class LaravelAiDispatcher implements AgentDispatcher
{
    public function dispatch(
        string $agentClass,
        string $prompt,
        Authenticatable $user,
        ?string $conversationId = null,
    ): AgentRunResult {
        if (! interface_exists(Agent::class)) {
            throw new \RuntimeException(
                'laravel/ai is not installed. Either composer require laravel/ai '.
                'or bind a custom AgentDispatcher implementation.'
            );
        }

        /** @var Agent $agent */
        $agent = app($agentClass);

        if ($conversationId) {
            $agent->continue($conversationId, $user);
        } else {
            $agent->forUser($user);
        }

        /** @var AgentResponse $response */
        $response = $agent->prompt($prompt);

        return new AgentRunResult(
            text: (string) $response->text,
            conversationId: (string) ($response->conversationId ?? ''),
        );
    }
}
