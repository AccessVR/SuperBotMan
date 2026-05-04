<?php

namespace OrchestrateXR\SuperBotMan;

/**
 * Tiny adapter the LaravelAiDispatcher passes to the agent's
 * forUser() / continue() methods. The Laravel AI SDK's
 * RememberConversation middleware reads $participant->id as a
 * property when storing agent_conversations.user_id; some host
 * apps (OrchestrateXR specifically) use a non-standard primary
 * key column on their User model (e.g. `Id`), so a raw User
 * passed straight to the SDK ends up with `->id` === null and
 * the foreign key column gets stored as null.
 *
 * Wrapping with this class normalizes the property the SDK
 * reads while preserving the visible auth identifier value.
 */
final readonly class ConversationParticipant
{
    public function __construct(public string|int $id) {}
}
