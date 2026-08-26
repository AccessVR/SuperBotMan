<?php

namespace OrchestrateXR\SuperBotMan\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use OrchestrateXR\SuperBotMan\AgentContext;
use OrchestrateXR\SuperBotMan\ClientActionBag;
use OrchestrateXR\SuperBotMan\Contracts\AgentDispatcher;
use OrchestrateXR\SuperBotMan\Events\AgentTurnCompleted;
use OrchestrateXR\SuperBotMan\Events\AgentTurnFailed;
use OrchestrateXR\SuperBotMan\Events\AgentTurnStarted;
use OrchestrateXR\SuperBotMan\Facades\SuperBotMan;
use OrchestrateXR\SuperBotMan\Support\ConversationTitler;
use OrchestrateXR\SuperBotMan\Support\WebMessages;

/**
 * One queued agent turn: the model round-trips and tool calls that the
 * sync dispatch path runs inside the HTTP request, moved onto a worker
 * so a turn is never subject to HTTP execution ceilings, proxy
 * timeouts, or the user's tab staying open. The reply (and any failure)
 * is delivered over the conversation's private broadcast channel.
 */
class RunAgentTurn implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Openers narrated into the chat the moment a worker picks the turn
     * up, so the user sees life before the first real tool event. The
     * widget appends the ellipsis itself — no trailing punctuation here.
     *
     * @var array<int, string>
     */
    public const OPENERS = [
        'Thinking',
        'On it',
        'Working on it',
        'Looking into it',
        'Give me a moment',
        'Gathering my thoughts',
        'Let me see',
        'Considering the request',
        'Putting a plan together',
        'One moment',
    ];

    /** Generous — a multi-write authoring turn holds a worker for a while. */
    public int $timeout = 300;

    /**
     * Attempts exist so WithoutOverlapping releases can re-queue behind an
     * in-flight turn; an actual exception never retries (the turn is not
     * idempotent) — the failure broadcasts to the widget instead.
     */
    public int $tries = 25;

    public int $maxExceptions = 1;

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $turnContext  session-derived state captured
     *                                             at dispatch (see SuperBotManConfigurator::captureQueuedTurnContext)
     */
    public function __construct(
        public string $agentClass,
        public string $prompt,
        public Authenticatable $user,
        public string $conversationId,
        public array $context = [],
        public string $userMessage = '',
        public array $turnContext = [],
    ) {}

    /**
     * One turn at a time per conversation; a second send waits its turn.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('super-botman-turn:'.$this->conversationId))
                ->releaseAfter(15)
                ->expireAfter($this->timeout + 60),
        ];
    }

    public function handle(AgentDispatcher $dispatcher): void
    {
        try {
            broadcast(new AgentTurnStarted(
                $this->conversationId,
                self::OPENERS[array_rand(self::OPENERS)],
            ));
        } catch (\Throwable $e) {
            // Narration is best-effort; never fail the turn over it.
            report($e);
        }

        // The request-scoped singletons the agent and its tools read;
        // rebound per job because a worker process serves many turns.
        $bag = new ClientActionBag;
        app()->instance(ClientActionBag::class, $bag);
        app()->instance(AgentContext::class, new AgentContext($this->context));

        // Tools authorize against the auth guard and whatever global
        // state the host's middleware derives from the session — neither
        // exists on a worker, so authenticate the turn's user and let the
        // host restore the rest (tenant/team id, ...). Without this every
        // permission check sees a guest and refuses.
        Auth::setUser($this->user);
        SuperBotMan::prepareQueuedTurn($this->user, $this->turnContext);

        try {
            $result = $dispatcher->dispatch(
                agentClass: $this->agentClass,
                prompt: $this->prompt,
                user: $this->user,
                conversationId: $this->conversationId,
            );

            broadcast(new AgentTurnCompleted(
                $result->conversationId ?: $this->conversationId,
                WebMessages::for($result, $bag, $this->userMessage),
            ));

            // Directly, not backfillAfterResponse(): app()->terminating is
            // process-end in a worker, which may be a long way off.
            ConversationTitler::backfill($result->conversationId ?: $this->conversationId, $this->userMessage);
        } catch (\Throwable $e) {
            report($e);

            try {
                broadcast(new AgentTurnFailed($this->conversationId));
            } catch (\Throwable $broadcastFailure) {
                // The widget can't be told (broadcaster down); the reply is
                // already lost — don't let the job fail twice over it.
                report($broadcastFailure);
            }
        } finally {
            SuperBotMan::cleanupQueuedTurn();
            // Drop every resolved guard so a long-lived worker can't leak
            // this turn's identity into its next job.
            Auth::forgetGuards();
            app()->forgetInstance(ClientActionBag::class);
            app()->forgetInstance(AgentContext::class);
        }
    }
}
