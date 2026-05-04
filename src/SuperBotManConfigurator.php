<?php

namespace OrchestrateXR\SuperBotMan;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use OrchestrateXR\SuperBotMan\Contracts\SuperBotManConfigurator as SuperBotManConfiguratorContract;
use OrchestrateXR\SuperBotMan\Models\AnonymousAgentUser;

class SuperBotManConfigurator implements SuperBotManConfiguratorContract
{
    protected Application $app;

    protected array $config = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function userId(): string
    {
        if ($id = Auth::id()) {
            return (string) $id;
        }

        return $this->anonymousSessionToken();
    }

    public function agentUser(): Authenticatable
    {
        if ($user = Auth::user()) {
            return $user;
        }

        return AnonymousAgentUser::firstOrCreate(
            ['session_token' => $this->anonymousSessionToken()],
            ['last_seen_at' => now()],
        );
    }

    protected function anonymousSessionToken(): string
    {
        $key = 'super_botman_session_token';

        if (! Session::has($key)) {
            Session::put($key, (string) Str::uuid());
        }

        return Session::get($key);
    }

    public function view(?string $view = null, $data = [], array $mergeData = []): View|ViewFactory
    {
        $factory = $this->app->make(ViewFactory::class);

        if (func_num_args() === 0) {
            return $factory;
        }

        return $factory->make('super-botman::'.$view, $data, $mergeData);
    }

    protected function render(string $view, array $data = [], array $mergeData = []): string
    {
        return $this->view($view, $data, $mergeData)->render();
    }

    public function getClientConfig(array $overrides = []): array
    {
        return array_merge($this->config(), [
            'userId' => $this->userId(),
        ], $overrides);
    }

    public function config(mixed $name = null, mixed $value = null): mixed
    {
        if (empty($this->config)) {
            $config = config('super-botman');

            $this->config = array_merge([
                'icons' => [
                    'back' => $this->render('icons.arrow-left', [
                        'stroke' => data_get($config, 'beaconLabelColor', '#ffffff'),
                    ]),
                    'bot' => $this->render('icons.bot', [
                        'stroke' => data_get($config, 'beaconLabelColor', '#ffffff'),
                    ]),
                    'close' => $this->render('icons.close', [
                        'stroke' => data_get($config, 'beaconLabelColor', '#ffffff'),
                    ]),
                    'closed' => $this->render('icons.comment', [
                        'stroke' => data_get($config, 'beaconLabelColor', '#ffffff'),
                    ]),
                    'email' => $this->render('icons.email', [
                        'stroke' => data_get($config, 'beaconLabelColor', '#ffffff'),
                    ]),
                    'open' => $this->render('icons.chevron-down', [
                        'stroke' => data_get($config, 'beaconLabelColor', '#ffffff'),
                    ]),
                    'search' => $this->render('icons.search', [
                        'stroke' => data_get($config, 'beaconLabelColor', '#ffffff'),
                    ]),
                    'user' => $this->render('icons.user', [
                        'stroke' => data_get($config, 'beaconLabelColor', '#ffffff'),
                    ]),
                ],
            ], $config);
        }

        if (! empty($name)) {
            if (is_array($name)) {
                $this->config = array_merge($this->config, $name);
            } elseif (! empty($value)) {
                data_set($this->config, $name, $value);

                return $this;
            }

            return data_get($this->config, $name);
        }

        return $this->config;
    }

    public function widget(): string
    {
        return $this->render('widget', ['config' => $this->config()]);
    }

    public function asset(string $path): string
    {
        return $this->hotAsset($path) ?: $this->buildAsset($path);
    }

    protected function buildAsset(string $path): string
    {
        $manifest = $this->app->publicPath('vendor/super-botman/manifest.json');

        if (! file_exists($manifest)) {
            throw new \RuntimeException('SuperBotMan asset manifest not found; run `php artisan vendor:publish --tag=super-botman-assets` to generate it.');
        }

        $manifest = json_decode(file_get_contents($manifest), true);
        $asset = $manifest[$path] ?? null;

        if (! $asset) {
            throw new \RuntimeException("Asset '{$path}' not found in SuperBotMan manifest.");
        }

        return asset('vendor/super-botman/'.$asset['file']);
    }

    protected function hotAsset(string $path): string|false
    {
        $hot = __DIR__.'/../public/hot';

        if (file_exists($hot)) {
            return file_get_contents($hot).'/'.ltrim($path, '/');
        }

        return false;
    }
}
