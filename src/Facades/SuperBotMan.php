<?php

namespace OrchestrateXR\SuperBotMan\Facades;

use Illuminate\Support\Facades\Facade;
use OrchestrateXR\SuperBotMan\Contracts\SuperBotManConfigurator as SuperBotManConfiguratorContract;

/**
 * @method static string userId()
 * @method static \Illuminate\Contracts\Auth\Authenticatable agentUser()
 * @method static \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory view(?string $view = null, $data = [], array $mergeData = [])
 * @method static mixed config(mixed $name = null, mixed $value = null)
 * @method static array getClientConfig(array $overrides = [])
 * @method static string widget()
 * @method static string asset(string $path)
 *
 * @see \OrchestrateXR\SuperBotMan\SuperBotManConfigurator
 */
class SuperBotMan extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SuperBotManConfiguratorContract::class;
    }
}
