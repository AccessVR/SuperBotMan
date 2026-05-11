<?php

namespace OrchestrateXR\SuperBotMan;

use Illuminate\Contracts\Auth\Authenticatable;
use OrchestrateXR\SuperBotMan\Contracts\SuperBotManConfigurator as SuperBotManConfiguratorContract;

/**
 * Bound singleton sitting behind the SuperBotMan facade. Holds the
 * agent registry and proxies configurator-level methods (userId,
 * agentUser, widget config, etc.) through to the host-bound
 * SuperBotManConfigurator implementation.
 *
 * @method string userId()
 * @method Authenticatable agentUser()
 * @method bool isAnonymous(Authenticatable $user)
 * @method mixed config(mixed $name = null, mixed $value = null)
 * @method array getClientConfig(array $overrides = [])
 * @method string widget()
 * @method string asset(string $path)
 * @method string renderAssistantText(string $text)
 * @method string renderUserPrompt(string $message, array $context)
 * @method string renderUserText(string $text)
 */
class SuperBotMan
{
    protected AgentRegistry $registry;

    protected ?SuperBotManConfiguratorContract $configurator = null;

    public function __construct()
    {
        $this->registry = new AgentRegistry;
    }

    public function configurator(): SuperBotManConfiguratorContract
    {
        return $this->configurator ??= app(SuperBotManConfiguratorContract::class);
    }

    public function registry(): AgentRegistry
    {
        return $this->registry;
    }

    public function registerAgent(string $slug, string $agentClass): AgentRegistration
    {
        return $this->registry->register($slug, $agentClass);
    }

    public function __call(string $method, array $arguments): mixed
    {
        return $this->configurator()->{$method}(...$arguments);
    }
}
