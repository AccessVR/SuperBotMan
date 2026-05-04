<?php

namespace OrchestrateXR\SuperBotMan;

class AgentRegistry
{
    /** @var array<string, AgentRegistration> */
    private array $registrations = [];

    public function register(string $slug, string $agentClass): AgentRegistration
    {
        return $this->registrations[$slug] = new AgentRegistration($slug, $agentClass);
    }

    public function bySlug(string $slug): ?AgentRegistration
    {
        return $this->registrations[$slug] ?? null;
    }

    /** @return AgentRegistration[] */
    public function all(): array
    {
        return array_values($this->registrations);
    }

    /** @return string[] */
    public function slugs(): array
    {
        return array_keys($this->registrations);
    }
}
