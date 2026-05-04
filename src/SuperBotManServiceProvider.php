<?php

namespace OrchestrateXR\SuperBotMan;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use OrchestrateXR\SuperBotMan\Contracts\SuperBotManConfigurator as SuperBotManConfiguratorContract;

class SuperBotManServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'super-botman');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('super-botman.php'),
            ], 'super-botman-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/super-botman'),
            ], 'super-botman-views');

            $this->publishes([
                __DIR__.'/../public/build' => public_path('vendor/super-botman'),
            ], 'super-botman-assets');
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'super-botman');

        $this->app->singleton(
            SuperBotManConfiguratorContract::class,
            fn ($app) => new SuperBotManConfigurator($app),
        );

        Blade::directive('superbotman', function (string $expression) {
            return "<?php echo \OrchestrateXR\SuperBotMan\Facades\SuperBotMan::widget({$expression}); ?>";
        });
    }
}
