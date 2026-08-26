<?php

namespace OrchestrateXR\SuperBotMan\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A queued agent turn finished: carries the same wire-format messages[]
 * the sync HTTP response would have returned, delivered over the
 * per-conversation private channel the widget already subscribes to for
 * activity narration. ShouldBroadcastNow — the worker that ran the turn
 * delivers the reply itself rather than queueing another hop.
 */
class AgentTurnCompleted implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    /**
     * @param  array<int, array<string, mixed>>  $messages
     */
    public function __construct(
        public string $conversationId,
        public array $messages,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(TurnChannel::name($this->conversationId));
    }

    public function broadcastAs(): string
    {
        return 'super-botman.turn.completed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversationId' => $this->conversationId,
            'messages' => $this->messages,
        ];
    }
}
