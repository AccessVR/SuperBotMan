<?php

namespace OrchestrateXR\SuperBotMan\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A queued agent turn died. The widget turns this into its error bubble
 * (with the user's message restored for retry) instead of waiting forever
 * on the typing indicator. Carries no internals — the exception is
 * report()ed server-side.
 */
class AgentTurnFailed implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public string $conversationId,
        public string $message = 'Something went wrong while working on that.',
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(TurnChannel::name($this->conversationId));
    }

    public function broadcastAs(): string
    {
        return 'super-botman.turn.failed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversationId' => $this->conversationId,
            'message' => $this->message,
        ];
    }
}
