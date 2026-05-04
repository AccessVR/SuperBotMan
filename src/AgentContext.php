<?php

namespace OrchestrateXR\SuperBotMan;

/**
 * Request-scoped, read-only key/value bag agents and tools resolve to
 * find out what state was passed in by the channel — current page URL,
 * resource id, custom values from the agent's registered context
 * resolver, etc.
 *
 * Bound into the container by SuperBotManController before each agent
 * run via app()->instance(AgentContext::class, $context).
 */
final readonly class AgentContext
{
    public function __construct(public array $data = []) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function all(): array
    {
        return $this->data;
    }
}
