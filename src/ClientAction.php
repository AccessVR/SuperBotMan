<?php

namespace OrchestrateXR\SuperBotMan;

/**
 * A side-effect emitted by an agent or tool during a run that the
 * front-end widget should perform after rendering the assistant's
 * reply (e.g. "navigate to URL X", "open dialog Y").
 */
final readonly class ClientAction
{
    public function __construct(
        public string $name,
        public array $payload = [],
    ) {}

    public function toArray(): array
    {
        return [
            'type' => 'client_action',
            'action' => $this->name,
            'payload' => $this->payload,
        ];
    }
}
