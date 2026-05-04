# CLAUDE.md

Guidance for Claude Code working in this repository.

## Overview

`orchestratexr/super-botman` is a Laravel package that gives any host app a drop-in chat-widget UI plus a thin multi-channel adapter framework on top of the **Laravel AI SDK** (`laravel/ai`, namespace `Laravel\Ai`).

The package owns:

- The **Vue chat widget** (UI, beacon, popup/docked positioning, conversation list).
- A small **Channel** abstraction so the same agent code can serve a Web widget today and Slack/Discord/etc. later, without each transport reinventing the dispatch loop.
- An **AgentRegistry** that turns one fluent `SuperBotMan::registerAgent(...)` call in a host app into auto-mounted routes + dispatch wiring.
- A `SuperBotManConfigurator` contract that lets host apps customize user identity, asset URLs, widget config, and (for anonymous visitors) which `Authenticatable` to attribute agent runs to.

The package does NOT own the LLM, the agent's tool-calling loop, or conversation persistence — those are Laravel AI SDK responsibilities.

## What's NOT here (any more)

This package used to wrap **BotMan** + **LLPhant** to provide its own conversation engine. As of v0.1, all of that is gone:

- No `botman/botman` or `botman/driver-web` dependency.
- No `theodo-group/llphant` dependency.
- No `ChatConversation`, `PersistsConversation`, `HasClientActions`, `ClientAction` (old version).
- No `chat_conversations` / `chat_messages` tables (a one-shot migration drops them on upgrade).
- No `ChatCommand` artisan command (the new one will be reintroduced in commit 2 as `super-botman:chat`, built on the Agent SDK directly).
- No `BotManMessageCreated` echo event.

If you find references to any of the above in this codebase, they're stale and should be removed.

## Development commands

### Frontend assets
- `npm run dev` — Vite dev server with hot reload.
- `npm run build` — production build into `public/build/`.
- `npm run preview` — preview production build.

### PHP
- `composer test` — run PHPUnit tests.
- `composer test-coverage` — run tests with HTML coverage report.

### Package publishing (for host apps)
- `php artisan vendor:publish --tag=super-botman-config` — publish `config/super-botman.php`.
- `php artisan vendor:publish --tag=super-botman-views` — publish Blade views to `resources/views/vendor/super-botman/`.
- `php artisan vendor:publish --tag=super-botman-assets` — publish built JS/CSS to `public/vendor/super-botman/`.

## Architecture

### Layout

- `src/Facades/SuperBotMan.php` — the `SuperBotMan` facade. Resolves to the bound `SuperBotManConfigurator` instance; agent registration flows through here too (added in commit 2).
- `src/SuperBotManConfigurator.php` — default implementation of the configurator contract. Host apps typically extend this to override `userId()` and `agentUser()`.
- `src/Contracts/SuperBotManConfigurator.php` — the host-overridable interface.
- `src/SuperBotManServiceProvider.php` — auto-discovered Laravel provider; binds the configurator, loads views/migrations/routes, registers the `@superbotman` Blade directive, and (commit 2) walks the AgentRegistry to mount agent routes.
- `src/Models/AnonymousAgentUser.php` — placeholder Authenticatable for visitors with no host-app user record. Exists so `agent_conversations.user_id` (a non-null FK in the Laravel AI SDK schema) always has a real row to point at.
- `database/migrations/` — drop-tables migration for the legacy `chat_*` tables and create-table migration for `super_botman_anonymous_users`.
- `resources/js/`, `resources/views/`, `resources/css/` — the Vue widget UI (preserved verbatim from prior versions, only identifiers renamed).
- `routes/web.php` — frame + beacon GET routes for the iframe-based widget.

### Channel + AgentRegistry (commit 2 — not yet present)

When commit 2 lands, expect to see:

- `src/Contracts/Channel.php` — the inbound/outbound seam between transport and agent.
- `src/Channels/WebChannel.php` — reference implementation; reads the existing widget wire format.
- `src/AgentRegistration.php`, `src/AgentRegistry.php` — fluent registration + the registry the service provider walks.
- `src/Http/Controllers/SuperBotManController.php` — single thin invokable that dispatches to whichever agent the route slug points at.
- `src/Http/Controllers/ConversationsController.php` — list/show/destroy endpoints over `agent_conversations`.
- `src/Console/Commands/ChatCommand.php` — `php artisan super-botman:chat {slug}` interactive CLI.
- `src/ClientAction.php`, `src/ClientActionBag.php`, `src/Facades/ClientActions.php` — request-scoped collector for tool side effects (navigate, openUrl).
- `src/AgentContext.php` — request-scoped context bag for the agent and its tools.

### Anonymous users

`Agent::forUser($user)` and `Agent::continue($id, as: $user)` both require an `Authenticatable`; the SDK has no string-id alternative and `agent_conversations.user_id` is non-null. Two valid host-app patterns:

1. **Host app authenticates anonymous visitors as a real `User`** (e.g. OrchestrateXR's "Anonymous User" account). Override `agentUser()` to just return `Auth::user()`. SuperBotMan's `AnonymousAgentUser` is never instantiated.
2. **Host app has no anonymous-user concept.** The default `SuperBotManConfigurator::agentUser()` will get-or-create an `AnonymousAgentUser` row keyed by a session uuid and return it. When that visitor later authenticates, the host app should run a "claim conversations" step to reassign `agent_conversations.user_id` from the `AnonymousAgentUser` row to the real user (helper to be added).

### Wire format (load-bearing)

The Vue widget's transport contract with the back end is documented public surface. Channels MUST emit:

```
{
  "messages": [
    { "type": "text", "text": "..." },                           // assistant content
    { "type": "client_action", "action": "navigate",             // optional
      "payload": { "url": "..." } },
    { "type": "client_action", "action": "setConversationId",    // ALWAYS last
      "payload": { "id": "...", "title": "..." } }
  ]
}
```

The widget reads `setConversationId` to thread the conversation id back into subsequent requests via the inbound `context` field. Snapshot-tested in commit 2.

## Conventions

- Every public class lives under `OrchestrateXR\SuperBotMan\…` and is PSR-4 autoloaded from `src/`.
- View namespace is `super-botman::` (e.g. `super-botman::widget`).
- Asset publish dir is `public/vendor/super-botman/`.
- Config key is `super-botman` (file `config/super-botman.php` after publish).
- Blade directive is `@superbotman`.
- Route name prefix is `super-botman.`.
- JS event prefix on `postMessage` is `super-botman.` (e.g. `super-botman.chat.api`).
- Window globals are `window.superbotmanWidget` (config) and `window.superbotmanChatWidget` (widget controller API).

If any of these naming conventions appear with `botman` (without the `super-` prefix) in the package source, that's a leftover from the rename and should be fixed.

## Environment

- PHP 8.3+ (Laravel AI SDK floor).
- Laravel 11+ / 12+.
- Node.js 20+ for frontend builds.
- Anthropic / OpenAI / etc. API keys live in the host app's `config/ai.php` — SuperBotMan does NOT read provider secrets directly.
