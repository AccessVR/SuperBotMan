<?php

namespace OrchestrateXR\SuperBotMan;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;

/**
 * Normalized envelope a Channel produces from a transport-specific
 * request, ready for the controller to hand to the agent dispatcher.
 */
final readonly class InboundMessage
{
    public function __construct(
        public string $message,
        public string $userId,
        public ?Authenticatable $user,
        public Authenticatable $agentUser,
        public ?string $conversationId,
        public array $context,
        public ?UploadedFile $attachment = null,
        public array $raw = [],
    ) {}
}
