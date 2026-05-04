<?php

namespace OrchestrateXR\SuperBotMan\Facades;

use OrchestrateXR\SuperBotMan\ClientActionBag;

/**
 * Convenience facade for tools and agents to emit client-side
 * side-effects without resolving the bag from the container directly.
 *
 * Example (inside a Tool::handle()):
 *
 *   ClientActions::emit('navigate', ['url' => '/dashboard']);
 *
 * The LLM sees only the string return value; the side-effect rides
 * back to the widget via the channel's outbound() serialization.
 */
class ClientActions
{
    public static function emit(string $name, array $payload = []): void
    {
        app(ClientActionBag::class)->push($name, $payload);
    }
}
