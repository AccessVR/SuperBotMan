<?php

namespace OrchestrateXR\SuperBotMan\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OrchestrateXR\SuperBotMan\AgentContext;
use OrchestrateXR\SuperBotMan\AgentRegistration;
use OrchestrateXR\SuperBotMan\ClientActionBag;
use OrchestrateXR\SuperBotMan\Contracts\AgentDispatcher;
use OrchestrateXR\SuperBotMan\Contracts\Channel;
use OrchestrateXR\SuperBotMan\Facades\SuperBotMan;
use OrchestrateXR\SuperBotMan\Support\ConversationTitler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Single thin invokable that handles every registered agent endpoint.
 * The route's _super_botman_slug default tells us which agent. The
 * channel's middleware (auth/csrf/etc.) has already run by the time
 * we get here.
 */
class SuperBotManController
{
    public function __invoke(Request $request, AgentDispatcher $dispatcher): Response
    {
        $registration = $this->resolveRegistration($request);
        $channel = $this->resolveChannel($registration);

        $inbound = $channel->inbound($request);

        $this->authorizeConversation($inbound->conversationId, $inbound->agentUser);

        $bag = new ClientActionBag;
        $context = new AgentContext($this->buildContext($request, $inbound, $registration));

        app()->instance(ClientActionBag::class, $bag);
        app()->instance(AgentContext::class, $context);

        // Hand the host a chance to inject per-turn metadata (page URL,
        // resource id, etc.) into the prompt before it's persisted +
        // sent to the LLM. The annotation survives in conversation
        // history so the agent can answer questions about *previous*
        // pages, not just the current one.
        $prompt = SuperBotMan::renderUserPrompt($inbound->message, $context->all());

        $result = $dispatcher->dispatch(
            agentClass: $registration->agentClass,
            prompt: $prompt,
            user: $inbound->agentUser,
            conversationId: $inbound->conversationId,
        );

        // Conversations minted by the prepare endpoint start untitled;
        // generate the title from the first message after the response is
        // sent so it never delays the reply. No-op for conversations the
        // SDK created (and titled) itself.
        ConversationTitler::backfillAfterResponse($result->conversationId, $inbound->message);

        return $channel->outbound($result, $bag, $inbound);
    }

    protected function resolveRegistration(Request $request): AgentRegistration
    {
        $slug = (string) $request->route('_super_botman_slug');
        $registration = SuperBotMan::registry()->bySlug($slug);

        if (! $registration) {
            throw new HttpException(404, "No agent registered for slug '{$slug}'.");
        }

        return $registration;
    }

    protected function resolveChannel(AgentRegistration $registration): Channel
    {
        return app($registration->channelClass);
    }

    /**
     * Confirm the resuming user owns the conversation they're trying
     * to continue. The Laravel AI SDK does NOT enforce ownership on
     * Agent::continue($id, as: $user) — without this gate, supplying
     * any conversation id resumes that conversation for whoever is
     * authenticated.
     */
    protected function authorizeConversation(?string $conversationId, Authenticatable $user): void
    {
        if (! $conversationId) {
            return;
        }

        $table = config('super-botman.agent_conversations_table', 'agent_conversations');
        $userColumn = config('super-botman.agent_conversations_user_column', 'user_id');

        $owns = DB::table($table)
            ->where('id', $conversationId)
            ->where($userColumn, $user->getAuthIdentifier())
            ->exists();

        if (! $owns) {
            throw new HttpException(403, 'You do not own this conversation.');
        }
    }

    protected function buildContext(Request $request, $inbound, AgentRegistration $registration): array
    {
        $base = $inbound->context ?? [];

        if ($registration->contextResolver) {
            $resolved = ($registration->contextResolver)($request);
            if (is_array($resolved)) {
                $base = array_merge($base, $resolved);
            }
        }

        return $base;
    }
}
