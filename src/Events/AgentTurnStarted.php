<?php

namespace OrchestrateXR\SuperBotMan\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A worker picked up a queued agent turn: tells the widget the agent is
 * on the case before the first real tool event arrives. Deliberately
 * broadcast under the host's configured activity-narration event name,
 * so the widget's existing label listener shows it with no extra
 * client-side wiring — the label renders beside the typing indicator
 * exactly like tool narration ("Thinking…").
 */
class AgentTurnStarted implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public string $conversationId,
        public string $label,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(TurnChannel::name($this->conversationId));
    }

    public function broadcastAs(): string
    {
        return ltrim((string) config('super-botman.activity.event', '.super-botman.activity'), '.');
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'label' => $this->label,
        ];
    }
}
