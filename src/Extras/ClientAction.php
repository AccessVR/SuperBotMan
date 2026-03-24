<?php

namespace OrchestrateXR\BotManChatSDK\Extras;

use BotMan\BotMan\Interfaces\WebAccess;

class ClientAction implements WebAccess
{
    public function __construct(
        protected string $action,
        protected array $payload = [],
    ) {}

    public static function make(string $action, array $payload = []): static
    {
        return new static($action, $payload);
    }

    /** @return array{type: string, action: string, payload: array<string, mixed>} */
    public function toWebDriver(): array
    {
        return [
            'type' => 'client_action',
            'action' => $this->action,
            'payload' => $this->payload,
        ];
    }
}
