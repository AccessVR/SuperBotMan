# SuperBotMan

[![Latest Version on Packagist](https://img.shields.io/packagist/v/orchestratexr/super-botman.svg?style=flat-square)](https://packagist.org/packages/orchestratexr/super-botman)
[![License](https://img.shields.io/github/license/AccessVR/SuperBotMan)](https://github.com/AccessVR/SuperBotMan/blob/main/LICENSE.md)

> **This repository is a read-only mirror.** SuperBotMan is developed inside
> OrchestrateXR's application repo and published here automatically by
> `git subtree split` on every merge. Pull requests opened against this
> repository cannot be merged — the next publish would overwrite them. Please
> open an issue describing the change and a maintainer will carry the patch
> upstream, crediting you. Issues, discussions, and bug reports are very welcome
> here.

A Laravel package that gives any host app a drop-in chat-widget UI plus a thin multi-channel adapter framework on top of the [Laravel AI SDK](https://github.com/laravel/ai).

SuperBotMan is the evolution of the prior `orchestratexr/botman-chat-sdk` package. The widget UI carried over; the LLM back-end (previously a hand-rolled BotMan + LLPhant integration) has been replaced by `laravel/ai`. See [`CHANGELOG.md`](CHANGELOG.md) for the full break-down — anything BotMan- or LLPhant-related is gone.

## What you get

- A bundled **Vue chat widget** (beacon + popup/docked iframe) that drops onto any Laravel page with one Blade directive.
- A **`Channel`** abstraction so the same agent code can serve the Web widget today, and Slack / Discord / your-transport-of-choice tomorrow, without the agents needing to know.
- An **`AgentRegistry`** so registering an agent and getting auto-mounted routes (POST endpoint, conversation list/show/delete) is three lines in your `AppServiceProvider`.
- A **CLI** (`php artisan super-botman:chat {slug}`) for testing a registered agent end-to-end without a browser, complete with `--continue` / `--conversation-id` / `--system` flags.

## What it isn't

- **Not an LLM framework.** Agents, tools, providers, structured output, streaming — all of that is `laravel/ai`'s job. SuperBotMan is the integration shell around it.
- **Not opinionated about persistence.** Conversation history lives in `laravel/ai`'s `agent_conversations` / `agent_conversation_messages` tables. SuperBotMan just exposes them through HTTP endpoints the widget understands.
- **Not a multi-tenant agent marketplace.** It's a tool for the host app's own developers to wire up agents. There's no UI for end-users to register their own.

## Installation

```bash
composer require orchestratexr/super-botman laravel/ai
```

Publish the package's config, views, and built JS/CSS assets, then run migrations:

```bash
php artisan vendor:publish --tag=super-botman-config
php artisan vendor:publish --tag=super-botman-views
php artisan vendor:publish --tag=super-botman-assets
php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"
php artisan migrate
```

Add an Anthropic (or other Lab provider) key to your `.env`:

```bash
ANTHROPIC_API_KEY=sk-ant-...
```

## The 3-line host-app integration

Drop these in `AppServiceProvider::boot()`:

```php
use OrchestrateXR\SuperBotMan\Facades\SuperBotMan;
use OrchestrateXR\SuperBotMan\Channels\WebChannel;

SuperBotMan::registerAgent('chat', \App\Agents\ChatAgent::class)
    ->channel(WebChannel::class);
```

That auto-registers:

| Method | URL | Purpose |
|---|---|---|
| `POST` | `{mount}/chat` | Agent endpoint (the widget posts here) |
| `GET` | `{mount}/chat/conversations` | List the current user's conversations |
| `GET` | `{mount}/chat/conversations/{id}` | Fetch a conversation's messages (for resume) |
| `DELETE` | `{mount}/chat/conversations/{id}` | Delete a conversation |

`{mount}` defaults to `/chat` and is configurable in `config/super-botman.php`.

Drop the widget onto any Blade page:

```blade
@superbotman
```

Define your agent as a normal `laravel/ai` Agent class:

```php
namespace App\Agents;

use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::Anthropic)]
#[Model('claude-sonnet-4-5')]
class ChatAgent implements Agent, Conversational
{
    use Promptable, RemembersConversations;

    public function instructions(): string
    {
        return 'You are a helpful assistant.';
    }
}
```

## Customizing user identity

Most apps will want to override how SuperBotMan identifies the visitor — for naming the echo channel, for scoping conversation history, and for telling `laravel/ai` which user owns the conversation. Extend the default configurator and bind your subclass:

```php
namespace App\Services;

use OrchestrateXR\SuperBotMan\SuperBotManConfigurator;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

class MyChat extends SuperBotManConfigurator
{
    public function agentUser(): Authenticatable
    {
        return Auth::user() ?? parent::agentUser();
    }

    public function isAnonymous(Authenticatable $user): bool
    {
        // E.g. for an app that authenticates everyone as a real user
        // and uses an `is_anonymous` flag for the shared visitor account:
        return $user->is_anonymous ?? parent::isAnonymous($user);
    }
}
```

```php
// AppServiceProvider::register()
$this->app->singleton(
    \OrchestrateXR\SuperBotMan\Contracts\SuperBotManConfigurator::class,
    fn ($app) => new \App\Services\MyChat($app),
);
```

If your User model uses a non-standard primary key column, that's already handled — SuperBotMan wraps the user in a `ConversationParticipant` adapter before handing it to the SDK.

## Anonymous visitors

Two patterns are supported out of the box:

1. **Your app already authenticates every visitor as a real `User` row** (e.g. an "Anonymous User" account that unauthenticated sessions get). Override `agentUser()` to return `Auth::user()` and you're done. SuperBotMan never touches its own anonymous table.
2. **Your app has no anonymous-user concept.** The default configurator will get-or-create a row in `super_botman_anonymous_users` keyed by a session UUID and return that. When the visitor signs in, your app can run a "claim" step to reassign their `agent_conversations.user_id` from the anonymous row to the real user (helper to follow).

The Laravel AI SDK requires `agent_conversations.user_id` to be non-null and reference a real Authenticatable; this design is the workaround.

## Multi-channel

The package ships only `WebChannel` today. To add Slack / Discord / etc., implement `OrchestrateXR\SuperBotMan\Contracts\Channel`:

```php
interface Channel
{
    public function inbound(Request $request): InboundMessage;
    public function outbound(AgentRunResult $result, ClientActionBag $actions, InboundMessage $inbound): Response;
    public function middleware(): array;            // ['web'], ['api', 'slack.signature'], ...
    public function endpoints(): array;             // [['POST', '/']], or multi-endpoint
    public function supportsConversationHistory(): bool;
}
```

Then register an agent with that channel:

```php
SuperBotMan::registerAgent('support', \App\Agents\SupportAgent::class)
    ->channel(\App\Channels\SlackChannel::class)
    ->middleware(['slack.signature']);
```

The same agent code runs over either transport.

## Configuration

`config/super-botman.php` (after publishing) covers the widget's appearance + the route mount prefix. The agent registry — *which agents exist, what URL they live at, what channel serves them* — is populated by your code calling `SuperBotMan::registerAgent(...)`, not by config.

## Testing

```bash
composer test
```

## Contributing

See [CONTRIBUTING](CONTRIBUTING.md).

## Security

If you discover any security related issues, please email acollegeman@gmail.com instead of using the issue tracker.

## Credits

- [Aaron Collegeman](https://github.com/collegeman)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). See [LICENSE](LICENSE.md).

## About OrchestrateXR

[OrchestrateXR](https://orchestratexr.com) is the easiest way to create and deploy XR content. Use your web browser to create for mobile, tablets, PCs and XR devices.
