# Changelog

All notable changes to `super-botman` (formerly `botman-chat-sdk`) will be documented in this file.

## [0.3.0] — Light and dark themes

### Added

- **The widget can now follow a host app's light/dark theme.** Every neutral in the UI (panel grounds, bubbles, inputs, text tones, borders) is a CSS custom property (`--sbm-*`) with built-in light and dark values; the new `theme` config picks between them — pinned (`'light'`/`'dark'`), following a dark class on the host `<html>` (`'class'`, Tailwind convention, live), or following the OS preference (`'media'`, live). The host script resolves the theme, boots the frames with it (`?theme=` on the frame URLs, so nothing paints in the wrong theme), and relays flips over the existing message bus.
- **Host palettes.** The new `palette` config overrides any token per theme, so a host can restyle the widget to its own ramp without rebuilding. `mainColor` remains the header/action surface and is now themeable as the `main` token; text on those surfaces reads the `on-main` token.
- `window.superbotmanChatWidget.theme('dark'|'light')` for hosts whose theme source none of the config modes can see.

### Changed

- Component markup now uses the `sbm-*` Tailwind color tokens instead of hardcoded grays; the light defaults reproduce the previous look exactly, so existing installs are unaffected until they opt into `theme`/`palette`.

## [0.2.2] — No zoom on focus

### Fixed

- **Focusing a field no longer zooms the page on iOS.** Every text field in the widget was `text-sm` (14px). iOS Safari zooms whenever a focused `input`/`textarea` computes under 16px, and does not zoom back out on blur — on a full-bleed mobile panel that left the widget unusable. The chat composer and the four Contact Support fields now carry `.sbm-form-field`, which is 16px on touch and falls back to the compact 0.875rem for precise pointers. Deliberately not fixed with `maximum-scale=1` / `user-scalable=no`: iOS has ignored those by default since iOS 10 because they break pinch-zoom for low-vision users.

## [0.2.1] — Responsive breakpoint

### Fixed

- **The mobile/desktop breakpoint is live, and measures the viewport.** `widget.js` chose its layout once at load from `window.screen.width` — the physical screen, which never changes when a window is resized and says nothing about how much room the page actually has. A desktop window dragged narrow kept the 375×650 popup instead of going full-bleed, and a device-emulation session only picked up the right geometry after a reload. It now reads `matchMedia('(max-width: 639px)')` and re-applies the panel, beacon, and docked-margin geometry whenever the breakpoint flips. The `mobile=true` hint on the frame URLs still reflects the viewport at boot: the frames are not reloaded on a change, and nothing in the package reads the parameter.

## [0.2.0] — Offsite embedding

The widget can now be embedded on external websites (different origins than the app serving it). Same-origin installs are unchanged except where noted.

### Added

- **Embed loader endpoint** `GET {mount}/embed/{key}.js` (`EmbedController` + `embed.blade.php`): a cacheable, cookieless script for one-line third-party embedding. Emits a pruned `window.superbotmanWidget` (absolute frame URLs carrying `?embed_key=`, geometry — no session-derived values) and async-injects `widget.js`. Inert by default: it 404s until the host implements `embedContext()`.
- **Host hooks on the configurator contract**: `embedContext(string $key): ?array` (resolve an embed key to config overrides; null = unknown), `frameOverrides(Request): array` (per-request overrides for the frame/beacon GETs — the previously hardcoded-empty `$config`), and `getEmbedLoaderConfig(string $key): array`. New `config('super-botman.frame_middleware')` appends host validation middleware to the frame routes.
- **`agentUserId(): int|string|null`** on the configurator contract — side-effect-free identity for read paths. `ConversationsController` index/show/destroy now use it (null → empty list / 404), so a drive-by GET never mints an `AnonymousAgentUser` row.
- **Embedded mode (`config.embedded`)**: `widget.js` appends `parent=<origin>` to frame srcs, disables dock mode (no reflowing someone else's page), and `ChatMessage.vue` stops intercepting app links (no host router offsite). `chat.js` persists/exchanges a host-minted visitor token (`config.embedToken` + `config.tokenExchangeEndpoint`) and sends it as `X-Embed-Chat-Token` on every request; `utils.client()` attaches it as an axios default.
- `widget(array $overrides = [])` — the `@superbotman` directive's argument is no longer silently discarded.
- `renderIcons(string $stroke)` extracted so per-request branding can re-render icons without touching the memoized config.

### Fixed / hardened (applies to same-origin installs too)

- **postMessage is now origin-addressed and origin-validated everywhere.** All sends pass an explicit targetOrigin (`appOrigin` derived from `frameEndpoint`; `parentOrigin` from config); all listeners validate `event.origin` (and, in `widget.js`, `event.source` against our two frames). Previously no call passed a targetOrigin and no listener checked origins — any frame on the page could drive the widget.
- **`super-botman.chat.api` no longer honors caller-supplied endpoints** — a message sender could previously make the iframe POST the visitor's cookies to an arbitrary URL. `api()` routes only to configured `chatServer` values.
- **Per-page `chatServer` routing actually works**: `api()`'s first param was named `server` while every caller passed `chatServer`, so all messages went to page[0]'s endpoint.
- **`Beacon.vue` referenced an undefined `$store`**, throwing in its message handler — the beacon icon never toggled between open/closed. Also fixed the `widget.js` relay predicate (`method?.indexOf(...) !== -1` is true for `undefined`) that relayed every unrelated postMessage and dropped `super-botman.widget.*` messages entirely.
- **`widget.js` mounts even when loaded after `DOMContentLoaded`** (async loaders).
- **localStorage split**: `widget.js` keeps `{open, docked}` under its own `super-botman:widget` key (embedding origin); `chat.js` keeps the chat slice under `super-botman:state:*` (app origin). The cross-frame merge-writes assumed one shared store, which is false cross-origin. One-time effect: existing users lose their remembered open/docked state once.

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
