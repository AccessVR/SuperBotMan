<?php

namespace OrchestrateXR\SuperBotMan\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use OrchestrateXR\SuperBotMan\AgentRunResult;

/**
 * Indirection between SuperBotMan's controller and whichever LLM
 * framework actually runs the agent. Default implementation
 * (LaravelAiDispatcher) calls into laravel/ai. Host apps can swap in
 * their own dispatcher for testing or to back the package with a
 * different SDK.
 */
interface AgentDispatcher
{
    public function dispatch(
        string $agentClass,
        string $prompt,
        Authenticatable $user,
        ?string $conversationId = null,
    ): AgentRunResult;
}
