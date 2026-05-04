<?php

namespace OrchestrateXR\SuperBotMan\Dispatchers;

use Illuminate\Contracts\Auth\Authenticatable;
use OrchestrateXR\SuperBotMan\AgentRunResult;
use OrchestrateXR\SuperBotMan\Contracts\AgentDispatcher;

/**
 * Default dispatcher backed by the official Laravel AI SDK
 * (laravel/ai). Imports from the SDK are resolved via the global
 * namespace on each call so this class can be instantiated even when
 * the SDK isn't installed (host apps that bind their own dispatcher
 * needn't pull laravel/ai).
 *
 * The exact API surface of laravel/ai may evolve; if upstream renames
 * forUser()/continue()/prompt()/text()/conversationId(), patch this
 * file and nothing else in the package needs to change.
 */
class LaravelAiDispatcher implements AgentDispatcher
{
    public function dispatch(
        string $agentClass,
        string $prompt,
        Authenticatable $user,
        ?string $conversationId = null,
    ): AgentRunResult {
        $agentFacade = '\\Laravel\\Ai\\Facades\\Agent';

        if (! class_exists($agentFacade) && ! class_exists('\\Laravel\\Ai\\Agent')) {
            throw new \RuntimeException(
                'laravel/ai is not installed. Either composer require laravel/ai '.
                'or bind a custom AgentDispatcher implementation.'
            );
        }

        if ($conversationId) {
            $response = $agentFacade::continue($conversationId, as: $user)->prompt($prompt);
        } else {
            $response = (new $agentClass)->forUser($user)->prompt($prompt);
        }

        return new AgentRunResult(
            text: (string) $response->text(),
            conversationId: (string) $response->conversationId(),
            conversationTitle: method_exists($response, 'conversationTitle')
                ? $response->conversationTitle()
                : null,
        );
    }
}
