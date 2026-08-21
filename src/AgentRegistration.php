<?php

namespace OrchestrateXR\SuperBotMan;

use Illuminate\Http\Request;
use OrchestrateXR\SuperBotMan\Channels\WebChannel;

/**
 * Fluent builder for one (slug, agent class) pair plus the channel,
 * middleware, context resolver, and optional path override that
 * configure how the agent is exposed to clients.
 */
class AgentRegistration
{
    public string $channelClass = WebChannel::class;

    /** @var string[] */
    public array $extraMiddleware = [];

    /** @var (callable(Request): array)|null */
    public $contextResolver = null;

    public ?string $pathOverride = null;

    public function __construct(
        public readonly string $slug,
        public readonly string $agentClass,
    ) {}

    public function channel(string $channelClass): static
    {
        $this->channelClass = $channelClass;

        return $this;
    }

    public function middleware(array $middleware): static
    {
        $this->extraMiddleware = array_merge($this->extraMiddleware, $middleware);

        return $this;
    }

    /**
     * @param  callable(Request): array  $resolver
     */
    public function context(callable $resolver): static
    {
        $this->contextResolver = $resolver;

        return $this;
    }

    public function path(string $path): static
    {
        $this->pathOverride = '/'.ltrim($path, '/');

        return $this;
    }
}
