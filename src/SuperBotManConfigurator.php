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

    public function isAnonymous(Authenticatable $user): bool
    {
        return $user instanceof AnonymousAgentUser;
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
        $merged = array_merge($this->config(), [
            'userId' => $this->userId(),
        ], $overrides);

        $mount = rtrim((string) data_get($merged, 'mount', '/chat'), '/');

        // Auto-derive a `pages` array from the agent registry when the
        // host hasn't specified one. Each registered agent becomes a
        // launcher button on the widget home screen.
        if (empty($merged['pages'])) {
            $registry = app(SuperBotMan::class)->registry();

            $merged['pages'] = array_map(function ($registration) use ($mount) {
                return [
                    'id' => $registration->slug,
                    'title' => Str::headline($registration->slug),
                    'buttonTitle' => Str::headline($registration->slug),
                    'buttonDescription' => null,
                    'chatServer' => $mount.'/'.$registration->slug,
                    'conversationsEndpoint' => $mount.'/'.$registration->slug.'/conversations',
                ];
            }, $registry->all());
        } else {
            // Host-defined pages: auto-fill chatServer / conversationsEndpoint
            // from a `slug` field when the host hasn't set them explicitly.
            $merged['pages'] = array_map(function ($page) use ($mount) {
                if (! isset($page['chatServer']) && isset($page['slug'])) {
                    $page['chatServer'] = $mount.'/'.$page['slug'];
                }
                if (! isset($page['conversationsEndpoint']) && isset($page['slug'])) {
                    $page['conversationsEndpoint'] = $mount.'/'.$page['slug'].'/conversations';
                }
                if (! empty($page['avatar'])) {
                    $page['avatar'] = $this->resolveAssetUrl($page['avatar']);
                }

                return $page;
            }, $merged['pages']);
        }

        // Top-level chatServer / conversationsEndpoint default to the
        // first page's, so widget components that don't yet thread
        // pageId still hit a working URL.
        if (! isset($merged['chatServer']) && ! empty($merged['pages'])) {
            $merged['chatServer'] = $merged['pages'][0]['chatServer'] ?? null;
        }
        if (! isset($merged['conversationsEndpoint']) && ! empty($merged['pages'])) {
            $merged['conversationsEndpoint'] = $merged['pages'][0]['conversationsEndpoint'] ?? null;
        }

        return $merged;
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
        return $this->render('widget', ['config' => $this->getClientConfig()]);
    }

    public function asset(string $path): string
    {
        return $this->hotAsset($path) ?: $this->buildAsset($path);
    }

    public function renderAssistantText(string $text): string
    {
        return $text;
    }

    public function renderUserPrompt(string $message, array $context): string
    {
        return $message;
    }

    public function renderUserText(string $text): string
    {
        return $text;
    }

    /**
     * Resolve a host-supplied asset reference (e.g. `page.avatar`) to a
     * URL the browser can fetch. Absolute URLs and protocol-relative
     * URLs pass through unchanged; bare paths are funneled through
     * Laravel's `asset()` helper so they pick up the configured CDN
     * origin in environments like Vapor. Resolved at request time, not
     * config-load time, so `asset()` is safe to call here.
     */
    protected function resolveAssetUrl(string $url): string
    {
        if (preg_match('#^(https?:)?//#i', $url) || str_starts_with($url, 'data:')) {
            return $url;
        }

        return asset($url);
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
