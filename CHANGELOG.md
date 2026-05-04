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
- `agentUser(): Authenticatable` method on the configurator contract.

### Migration notes

- This release is intentionally non-functional end-to-end; the Channel / Controller / AgentRegistry land in the next commit. Use `feature/super-botman` HEAD only when you're ready to follow through.
- Host apps consuming the package must re-run `vendor:publish --force --tag=super-botman-config --tag=super-botman-views --tag=super-botman-assets` after upgrading and delete any leftover `resources/views/vendor/botman-chat-sdk/` and `public/vendor/botman-chat-sdk/` directories.
