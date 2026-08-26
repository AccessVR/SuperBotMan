<?php

namespace OrchestrateXR\SuperBotMan;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use OrchestrateXR\SuperBotMan\Contracts\SuperBotManConfigurator as SuperBotManConfiguratorContract;
use OrchestrateXR\SuperBotMan\Models\AnonymousAgentUser;

class SuperBotManConfigurator implements SuperBotManConfiguratorContract
{
    protected const SESSION_TOKEN_KEY = 'super_botman_session_token';

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

    /**
     * Side-effect-free counterpart of agentUser() for read paths: never
     * creates a session token or an AnonymousAgentUser row. A visitor
     * with no record yet simply owns nothing.
     */
    public function agentUserId(): int|string|null
    {
        if ($id = Auth::id()) {
            return $id;
        }

        if (! Session::has(static::SESSION_TOKEN_KEY)) {
            return null;
        }

        return AnonymousAgentUser::query()
            ->where('session_token', Session::get(static::SESSION_TOKEN_KEY))
            ->value('id');
    }

    public function isAnonymous(Authenticatable $user): bool
    {
        return $user instanceof AnonymousAgentUser;
    }

    protected function anonymousSessionToken(): string
    {
        if (! Session::has(static::SESSION_TOKEN_KEY)) {
            Session::put(static::SESSION_TOKEN_KEY, (string) Str::uuid());
        }

        return Session::get(static::SESSION_TOKEN_KEY);
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
                'icons' => $this->renderIcons((string) data_get($config, 'beaconLabelColor', '#ffffff')),
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

    public function widget(array $overrides = []): string
    {
        return $this->render('widget', ['config' => $this->getClientConfig($overrides)]);
    }

    public function embedContext(string $key): ?array
    {
        return null;
    }

    public function frameOverrides(Request $request): array
    {
        $key = (string) $request->query('embed_key', '');

        if ($key === '') {
            return [];
        }

        $context = $this->embedContext($key);

        if ($context === null) {
            // Serving the frame with stock config to an unvalidated
            // embedder would silently fall back to cookie identity.
            abort(404);
        }

        return array_merge([
            'embedded' => true,
            'embedKey' => $key,
            // postMessage target for iframe→host messages. Safe to take
            // from the query string: addressing a message to a claimed
            // origin the embedder doesn't have just drops the message.
            // Hosts that maintain a domain allowlist validate it in
            // their frame middleware and override via embedContext().
            'parentOrigin' => (string) $request->query('parent', ''),
        ], $context);
    }

    public function getEmbedLoaderConfig(string $key): array
    {
        $config = $this->config();
        $query = '?embed_key='.urlencode($key);

        return [
            'embedded' => true,
            'embedKey' => $key,
            'frameEndpoint' => url((string) data_get($config, 'frameEndpoint')).$query,
            'beaconEndpoint' => url((string) data_get($config, 'beaconEndpoint')).$query,
            'beaconSize' => data_get($config, 'beaconSize', 60),
            'desktopWidth' => data_get($config, 'desktopWidth', 375),
            'desktopHeight' => data_get($config, 'desktopHeight', 650),
            'mobileWidth' => data_get($config, 'mobileWidth', '100%'),
            'mobileHeight' => data_get($config, 'mobileHeight', '100%'),
            'openByDefault' => (bool) data_get($config, 'openByDefault', false),
            'hideBeaconClass' => data_get($config, 'hideBeaconClass', '--hide-beacon'),
        ];
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
     * Hosts with session-derived authorization state (an active
     * tenant/team id, locale, ...) override this trio so queued turns
     * run with the same context the sync HTTP path gets from its
     * middleware. The defaults assume authenticating the user (which
     * RunAgentTurn does on the default guard) is enough.
     *
     * @return array<string, mixed>
     */
    public function captureQueuedTurnContext(\Illuminate\Http\Request $request): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $turnContext
     */
    public function prepareQueuedTurn(Authenticatable $user, array $turnContext): void
    {
        // Intentionally empty — see captureQueuedTurnContext().
    }

    public function cleanupQueuedTurn(): void
    {
        // Intentionally empty — see captureQueuedTurnContext().
    }

    /**
     * Render the widget's icon set with the given stroke color. Kept
     * separate from config()'s memoized array so a per-request override
     * of beaconLabelColor (per-tenant branding) can re-render icons
     * without mutating the cached config.
     *
     * @return array<string, string>
     */
    protected function renderIcons(string $stroke): array
    {
        return [
            'back' => $this->render('icons.arrow-left', ['stroke' => $stroke]),
            'bot' => $this->render('icons.bot', ['stroke' => $stroke]),
            'close' => $this->render('icons.close', ['stroke' => $stroke]),
            'closed' => $this->render('icons.comment', ['stroke' => $stroke]),
            'email' => $this->render('icons.email', ['stroke' => $stroke]),
            'open' => $this->render('icons.chevron-down', ['stroke' => $stroke]),
            'search' => $this->render('icons.search', ['stroke' => $stroke]),
            'user' => $this->render('icons.user', ['stroke' => $stroke]),
        ];
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
