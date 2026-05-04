# Changelog

All notable changes to `super-botman` (formerly `botman-chat-sdk`) will be documented in this file.

## [Unreleased]

### Changed — BREAKING

- **Renamed package** `orchestratexr/botman-chat-sdk` → `orchestratexr/super-botman`. Namespace `OrchestrateXR\BotManChatSDK` → `OrchestrateXR\SuperBotMan`. View namespace `botman-chat-sdk::` → `super-botman::`. Asset publish dir `public/vendor/botman-chat-sdk/` → `public/vendor/super-botman/`. Config file `config/botman-chat-sdk.php` → `config/super-botman.php`. Blade directive `@botman` → `@superbotman`. Facade `BotManChat` → `SuperBotMan` (now under `OrchestrateXR\SuperBotMan\Facades`).
- **JS public surface renamed.** `window.botmanWidget` → `window.superbotmanWidget`. `window.botmanChatWidget` → `window.superbotmanChatWidget`. `postMessage` event prefix `botman-web-widget.*` → `super-botman.*`.
- **Conversation list endpoint** is no longer hardcoded to `/botman/conversations` in `ConversationList.vue`; the widget now reads `conversationsEndpoint` from its per-page config, falling back to widget-level config.

### Removed — BREAKING

- **Dropped BotMan and LLPhant.** `botman/botman`, `botman/driver-web`, and `theodo-group/llphant` are no longer dependencies. `ChatConversation`, `PersistsConversation`, `HasClientActions`, `ClientAction` (old), `BotManChatServerController`, `ConversationHistoryController`, `BotManChatTestController`, `CommandDriver`, `BotManMessage`, `BotManMessageCreated` are all removed. The package no longer ships an LLM back-end of its own; host apps wire in the Laravel AI SDK (`laravel/ai`).
- **Dropped `chat_conversations` and `chat_messages` tables.** A migration drops them on upgrade. Conversation persistence is now provided by the Laravel AI SDK via its `agent_conversations` / `agent_conversation_messages` tables.
- **Removed `botman:chat` artisan command** and the `OPENAI_API_KEY` requirement. A new `super-botman:chat` command lands in the next commit, built on the Agent SDK directly.
- **Removed echo broadcasting config keys** (`useEcho`, `echoChannel`, `echoConfiguration`, `echoEventClass`) and the `BotManMessageCreated` event class. Streaming will be reintroduced as a Channel concern when SSE support lands.

### Added

- `AnonymousAgentUser` Eloquent model + `super_botman_anonymous_users` table. Satisfies the Laravel AI SDK's non-null `agent_conversations.user_id` FK for anonymous visitors. Host apps with their own anonymous-user concept can override `SuperBotManConfigurator::agentUser()` and never touch this table.
- `agentUser(): Authenticatable` and `isAnonymous(Authenticatable): bool` on the configurator contract. `isAnonymous()` is overridable so host apps that authenticate visitors as a real user with an "anonymous" flag (e.g. OrchestrateXR's `User::isAnonymous()`) can convey that to SuperBotMan.
- `Channel` contract + `InboundMessage` / `AgentRunResult` DTOs.
- `WebChannel` — reference implementation for the bundled Vue widget; preserves the existing widget wire format (load-bearing): `{messages: [{type: 'text', text}, ...{type: 'client_action', action, payload}, {type: 'client_action', action: 'setConversationId', payload: {id, title}}]}`.
- `AgentRegistry` + `AgentRegistration` — fluent registration via `SuperBotMan::registerAgent('slug', AgentClass::class)->channel(WebChannel::class)->middleware(['auth'])->context(fn ($r) => [...])`.
- `SuperBotMan` manager class (singleton behind the facade) — holds the registry, proxies configurator methods.
- `SuperBotManController` — single invokable that dispatches every registered agent endpoint, with built-in IDOR check on resume (the Laravel AI SDK does **not** verify conversation ownership on `Agent::continue()`).
- `ConversationsController` — auto-mounted list/show/destroy at `{mount}/{slug}/conversations[/{id}]` for channels that opt in via `supportsConversationHistory()`. Reads `agent_conversations` directly via DB facade with config-overridable table/column names.
- `ClientActionBag` + `ClientActions` facade — request-scoped collector for tool side effects (`ClientActions::emit('navigate', ['url' => ...])`). Tool returns the human-readable string for the LLM; the side-effect rides back via the channel's `outbound()`.
- `AgentContext` — request-scoped read-only key/value bag tools resolve to find page-level state passed in by the channel + agent's registered context resolver.
- `AgentDispatcher` contract + `LaravelAiDispatcher` default. Indirection lets host apps swap in a fake dispatcher for tests or back the package with a different SDK.
- `super-botman:chat {slug}` artisan command with `--continue`, `--conversation-id`, `--system` flags. Interactive CLI for testing a registered agent without a browser.
- Auto-mounted routes: registered agents are exposed at `POST {mount}/{slug}` (mount defaults to `/chat`, configurable via `super-botman.mount`).

### Migration notes

- Host apps consuming the package must re-run `vendor:publish --force --tag=super-botman-config --tag=super-botman-views --tag=super-botman-assets` after upgrading and delete any leftover `resources/views/vendor/botman-chat-sdk/` and `public/vendor/botman-chat-sdk/` directories.
- Host apps must `composer require laravel/ai` themselves (SuperBotMan does not declare it as a hard dependency so apps can swap dispatchers without pulling the SDK in).
- The Vue widget's `ConversationList.vue` now reads its endpoint from `window.superbotmanWidget.conversationsEndpoint` (or per-page `pages[*].conversationsEndpoint`) rather than the hardcoded `/botman/conversations` URL of the previous release.
