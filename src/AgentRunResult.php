<?php

namespace OrchestrateXR\SuperBotMan;

/**
 * Wrapper around whatever a concrete agent dispatcher returns. Insulates
 * SuperBotMan's controller and channel layer from upstream SDK API
 * drift — only the dispatcher implementation reads SDK-specific types.
 */
final readonly class AgentRunResult
{
    public function __construct(
        public string $text,
        public string $conversationId,
        public ?string $conversationTitle = null,
    ) {}
}
