<?php

namespace OrchestrateXR\SuperBotMan\Facades;

use Illuminate\Support\Facades\Facade;
use OrchestrateXR\SuperBotMan\AgentRegistration;
use OrchestrateXR\SuperBotMan\AgentRegistry;

/**
 * @method static AgentRegistration registerAgent(string $slug, string $agentClass)
 * @method static AgentRegistry registry()
 * @method static \OrchestrateXR\SuperBotMan\Contracts\SuperBotManConfigurator configurator()
 * @method static string userId()
 * @method static \Illuminate\Contracts\Auth\Authenticatable agentUser()
 * @method static bool isAnonymous(\Illuminate\Contracts\Auth\Authenticatable $user)
 * @method static mixed config(mixed $name = null, mixed $value = null)
 * @method static array getClientConfig(array $overrides = [])
 * @method static string widget()
 * @method static string asset(string $path)
 *
 * @see \OrchestrateXR\SuperBotMan\SuperBotMan
 */
class SuperBotMan extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \OrchestrateXR\SuperBotMan\SuperBotMan::class;
    }
}
