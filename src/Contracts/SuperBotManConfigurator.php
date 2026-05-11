<?php

namespace OrchestrateXR\SuperBotMan\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;

interface SuperBotManConfigurator
{
    /**
     * Stable identifier for the current visitor — used for view-level
     * config (widget personalization, history scoping). Returns the
     * authenticated user's id when available, or a session-scoped uuid
     * for anonymous visitors. Always a string.
     */
    public function userId(): string;

    /**
     * The Authenticatable instance the Laravel AI SDK should attribute
     * agent runs to. For authenticated requests this is typically
     * Auth::user(). For anonymous visitors, host apps may return a
     * host-managed anonymous user record or fall back to SuperBotMan's
     * AnonymousAgentUser model.
     */
    public function agentUser(): Authenticatable;

    /**
     * Whether the given Authenticatable is conceptually anonymous from
     * the host app's perspective. Some apps (OrchestrateXR among them)
     * authenticate every visitor as a real User row, with a property
     * marking certain rows as the shared anonymous account — in that
     * case this method returns true even though Auth::id() is set.
     *
     * Used downstream when "claiming" anonymous conversations on
     * sign-in: anonymous-owned conversations should reassign to the
     * real user's row.
     */
    public function isAnonymous(Authenticatable $user): bool;

    /**
     * Get the evaluated view contents for the given view.
     *
     * @param  \Illuminate\Contracts\Support\Arrayable|array  $data
     */
    public function view(?string $view = null, $data = [], array $mergeData = []): View|ViewFactory;

    /**
     * Get or set configuration parameters.
     *
     * When only a name is provided, returns the configuration value.
     * When both name and value are provided, sets the configuration.
     */
    public function config(mixed $name = null, mixed $value = null): mixed;

    /**
     * Configuration array shipped to the front-end widget.
     */
    public function getClientConfig(array $overrides = []): array;

    /**
     * Render the embeddable widget view.
     */
    public function widget(): string;

    /**
     * Resolve a vendor asset URL, honoring the Vite hot file in dev.
     */
    public function asset(string $path): string;

    /**
     * Transform an assistant message's raw text before it is serialized
     * into the channel's outbound payload. Hosts override to convert
     * markdown to sanitized HTML, swap emoji, etc. Channels that render
     * HTML (e.g. the bundled web widget) call this hook on every text
     * message they emit; transports that prefer plaintext (Slack, etc.)
     * should bypass it. Default implementation is a passthrough.
     */
    public function renderAssistantText(string $text): string;

    /**
     * Augment the user's outgoing prompt with per-turn metadata before
     * the controller hands it to the dispatcher. The augmented string
     * is what the LLM sees AND what the Agent SDK persists, so the
     * annotation survives in the conversation history. Hosts typically
     * append a structured comment with page/URL context so the LLM can
     * track where the user was at each turn.
     *
     * @param  array<string, mixed>  $context  the resolved AgentContext payload
     */
    public function renderUserPrompt(string $message, array $context): string;

    /**
     * Strip any host-injected annotations from a stored user message
     * before it is rendered into the chat UI on history resume. The
     * inverse of renderUserPrompt(): whatever was appended at write
     * time should be removed at read time so the user sees what they
     * actually typed.
     */
    public function renderUserText(string $text): string;
}
