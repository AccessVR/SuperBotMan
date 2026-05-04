<?php

namespace OrchestrateXR\SuperBotMan;

/**
 * Request-scoped collector for ClientActions emitted during an agent
 * run. The controller binds a fresh instance into the container before
 * dispatching the agent; tools push to it via the ClientActions facade;
 * the channel reads from it when serializing the response.
 */
final class ClientActionBag
{
    /** @var ClientAction[] */
    private array $actions = [];

    public function push(string $name, array $payload = []): void
    {
        $this->actions[] = new ClientAction($name, $payload);
    }

    /** @return ClientAction[] */
    public function all(): array
    {
        return $this->actions;
    }

    public function isEmpty(): bool
    {
        return empty($this->actions);
    }
}
