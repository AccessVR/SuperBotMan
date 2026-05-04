<?php

namespace OrchestrateXR\SuperBotMan;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use OrchestrateXR\SuperBotMan\Console\Commands\ChatCommand;
use OrchestrateXR\SuperBotMan\Contracts\AgentDispatcher;
use OrchestrateXR\SuperBotMan\Contracts\SuperBotManConfigurator as SuperBotManConfiguratorContract;
use OrchestrateXR\SuperBotMan\Dispatchers\LaravelAiDispatcher;
use OrchestrateXR\SuperBotMan\Http\Controllers\ConversationsController;
use OrchestrateXR\SuperBotMan\Http\Controllers\SuperBotManController;

class SuperBotManServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'super-botman');

        $this->app->singleton(SuperBotMan::class);

        $this->app->singleton(
            SuperBotManConfiguratorContract::class,
            fn ($app) => new SuperBotManConfigurator($app),
        );

        $this->app->singleton(AgentDispatcher::class, LaravelAiDispatcher::class);

        Blade::directive('superbotman', function (string $expression) {
            return "<?php echo \OrchestrateXR\SuperBotMan\Facades\SuperBotMan::widget({$expression}); ?>";
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'super-botman');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->bootAgentRoutes();

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

            $this->commands([ChatCommand::class]);
        }
    }

    /**
     * Walk the AgentRegistry and mount one route per (agent ×
     * channel-endpoint), plus per-agent conversation history routes
     * when the channel supports them.
     */
    protected function bootAgentRoutes(): void
    {
        $manager = $this->app->make(SuperBotMan::class);
        $mount = rtrim((string) config('super-botman.mount', '/chat'), '/');

        foreach ($manager->registry()->all() as $registration) {
            $channel = $this->app->make($registration->channelClass);

            $base = $registration->pathOverride ?? $mount.'/'.$registration->slug;
            $middleware = array_values(array_unique(array_merge(
                $channel->middleware(),
                $registration->extraMiddleware,
            )));

            foreach ($channel->endpoints() as [$verb, $suffix]) {
                $path = rtrim($base, '/').($suffix === '/' ? '' : $suffix);

                Route::match([strtolower($verb)], $path, SuperBotManController::class)
                    ->middleware($middleware)
                    ->defaults('_super_botman_slug', $registration->slug)
                    ->name("super-botman.agent.{$registration->slug}".($suffix === '/' ? '' : '.'.trim($suffix, '/')));
            }

            if ($channel->supportsConversationHistory()) {
                $convosBase = rtrim($base, '/').'/conversations';

                Route::get($convosBase, [ConversationsController::class, 'index'])
                    ->middleware($middleware)
                    ->defaults('slug', $registration->slug)
                    ->name("super-botman.conversations.{$registration->slug}.index");

                Route::get($convosBase.'/{id}', [ConversationsController::class, 'show'])
                    ->middleware($middleware)
                    ->defaults('slug', $registration->slug)
                    ->name("super-botman.conversations.{$registration->slug}.show");

                Route::delete($convosBase.'/{id}', [ConversationsController::class, 'destroy'])
                    ->middleware($middleware)
                    ->defaults('slug', $registration->slug)
                    ->name("super-botman.conversations.{$registration->slug}.destroy");
            }
        }
    }
}
