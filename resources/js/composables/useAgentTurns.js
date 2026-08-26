import { emitMessage, api, client } from '../utils'

// The conversation transport shared by the widget (Chat.vue) and the
// full-screen console (Console.vue): sending, the sync and queued reply
// paths, live activity narration, turn polling, and history restore.
//
// Holds per-document closure state (active channel, poller, in-flight
// turn flag) — instantiate it ONCE per document. Two instances in the
// same document would double-poll and double-append queued replies.
export function useAgentTurns(store, options = {}) {

    // Where restore lands when a conversation can't be loaded: the
    // widget falls back to its home screen; the console (which has no
    // home) pins the fallback to its own page.
    const fallbackPage = options.fallbackPage
        || (() => store.state.config.defaultPage || 'home')

    const writeToMessages = (message, pageId = null) => {
        store.commit('messages', { message, pageId })
    }

    const sayAsBot = (message) => {
        writeToMessages({
          ...message,
          ...{
            type: 'text',
            from: 'chatbot',
          }
        })
    }

    const whisper = (message) => {
        say(message, false)
    }

    // --- Live agent-activity narration -------------------------------------
    // When the host configures an `activity` broadcast channel and the page
    // has Echo available, the widget subscribes per conversation and shows
    // what the agent is doing ("Listing experiences…") beside the typing
    // indicator. Everything degrades silently to the plain indicator when
    // Echo or the config is absent.

    const conversationsEndpointFor = (pageId) => {
        const page = store.state.config.pages.find(p => p.id === pageId)
        return page?.conversationsEndpoint || store.state.config.conversationsEndpoint
    }

    // Mint the conversation before its first message so the activity channel
    // can be subscribed from the very first turn. Failures fall back to the
    // legacy flow (the server creates the conversation on first dispatch).
    const ensureConversationPrepared = async (pageId) => {
        const existing = store.state.conversationId[pageId]
        if (existing) {
            return existing
        }
        const endpoint = conversationsEndpointFor(pageId)
        if (!endpoint || !store.state.config.activity || !window.Echo) {
            return null
        }
        try {
            const response = await client().post(`${endpoint}/prepare`)
            const conversationId = response.data?.conversationId
            if (conversationId) {
                store.commit('conversationId', { pageId, conversationId })
            }
            return conversationId || null
        } catch (e) {
            return null
        }
    }

    let activityChannelName = null

    // --- Queued turns -------------------------------------------------------
    // With server-side `dispatch: queue`, the chat POST acks immediately
    // (turnQueued) and the reply arrives as a broadcast on the same
    // per-conversation channel as activity narration — or, without Echo,
    // via polling the conversation history.

    const TURN_COMPLETED_EVENT = '.super-botman.turn.completed'
    const TURN_FAILED_EVENT = '.super-botman.turn.failed'

    let turnPoller = null

    // True between a turnQueued ack and its reply landing (by broadcast or
    // poll) — whichever path wins claims the turn, so the loser can't
    // append the reply a second time.
    let awaitingTurn = false

    const stopTurnPolling = () => {
        if (turnPoller) {
            clearInterval(turnPoller.interval)
            clearTimeout(turnPoller.deadline)
            turnPoller = null
        }
    }

    const finishTurn = (pageId) => {
        awaitingTurn = false
        stopTurnPolling()
        store.commit('loading', false)
    }

    const handleTurnCompleted = (pageId, messages) => {
        if (!awaitingTurn) {
            return
        }
        let timeout = 0
        for (const message of messages || []) {
            processServerMessage(message, pageId, () => timeout += 0)
        }
        finishTurn(pageId)
    }

    const canListenForTurns = () =>
        !!(store.state.config.activity?.channel && window.Echo)

    // The queued reply has to land somewhere even when broadcasting fails:
    // poll the conversation history until an assistant message newer than
    // the send appears. Primary delivery without websockets (3s), slow
    // safety net alongside them (15s) — the first turn event stops it.
    const startTurnPolling = (pageId, conversationId, intervalMs = 3000) => {
        stopTurnPolling()
        const endpoint = conversationsEndpointFor(pageId)
        if (!endpoint) {
            return
        }
        // History rows are {role, content}; assistant rows whose content is
        // JSON are tool-call bookkeeping, not prose — skip those.
        const proseReplies = (messages) => (messages || []).filter(m => {
            if (m.role !== 'assistant' || typeof m.content !== 'string') {
                return false
            }
            const trimmed = m.content.trim()
            return trimmed !== '' && trimmed[0] !== '[' && trimmed[0] !== '{'
        })
        const baseline = (store.state.messages[pageId] || []).filter(m => m.from === 'chatbot').length
        turnPoller = {
            interval: setInterval(async () => {
                try {
                    const response = await client().get(`${endpoint}/${conversationId}`)
                    const replies = proseReplies(response.data?.messages)
                    if (awaitingTurn && replies.length > baseline) {
                        writeToMessages({ type: 'text', text: replies[replies.length - 1].content, from: 'chatbot' }, pageId)
                        finishTurn(pageId)
                    }
                } catch (e) {
                    // transient poll failures are fine; the deadline bounds us
                }
            }, intervalMs),
            deadline: setTimeout(() => {
                finishTurn(pageId)
                store.commit('error', 'The reply is taking longer than expected — reopen this conversation in a moment.')
            }, 5 * 60 * 1000),
        }
    }

    const listenForActivity = (conversationId) => {
        const activity = store.state.config.activity
        if (!activity?.channel || !window.Echo || !conversationId) {
            return
        }
        const channelName = activity.channel.replace('{conversationId}', conversationId)
        if (channelName === activityChannelName) {
            return
        }
        if (activityChannelName) {
            window.Echo.leave(activityChannelName)
        }
        activityChannelName = channelName
        const channel = window.Echo.private(channelName)
        // No loading guard here: the first tool event can arrive inside the
        // 300ms delay before `loading` flips true. Display is gated by the
        // loading branch in ChatMessage, and loading(false) clears the label.
        if (activity.event) {
            channel.listen(activity.event, (event) => {
                store.commit('activity', event.label || null)
            })
        }
        channel.listen(activity.turnCompletedEvent || TURN_COMPLETED_EVENT, (event) => {
            handleTurnCompleted(store.state.page, event.messages)
        })
        channel.listen(activity.turnFailedEvent || TURN_FAILED_EVENT, (event) => {
            if (!awaitingTurn) {
                return
            }
            finishTurn(store.state.page)
            store.commit('error', event.message || true)
        })
    }

    // One server message → one widget effect. Shared by the sync HTTP
    // response path and the queued-turn broadcast path so both speak the
    // same wire contract.
    const processServerMessage = (message, pageId, addTimeout) => {
        if (message.type === 'client_action') {
            if (message.action === 'setConversationId') {
                store.commit('conversationId', {
                    pageId,
                    conversationId: message.payload.id,
                })
                return
            }
            if (message.action === 'turnQueued') {
                awaitingTurn = true
                store.commit('conversationId', {
                    pageId,
                    conversationId: message.payload.id,
                })
                // The reply arrives by broadcast — with history polling as
                // primary delivery when no channel is available, and as a
                // slow safety net when there is one (a worker whose
                // broadcast fails would otherwise leave the dot forever).
                if (canListenForTurns()) {
                    listenForActivity(message.payload.id)
                    startTurnPolling(pageId, message.payload.id, 15000)
                } else {
                    startTurnPolling(pageId, message.payload.id)
                }
                return
            }
            emitMessage('chat.clientAction', {
                action: message.action,
                payload: message.payload,
            })
            return
        }
        writeToMessages({
            ...message,
            ...{
                from: 'chatbot',
                timeout: addTimeout(message.timeout || 0),
            }
        }, pageId)
    }

    const responseWasQueued = (responseData) =>
        (responseData?.messages || []).some(m => m.type === 'client_action' && m.action === 'turnQueued')

    const say = async (message, showMessage = true) => {
        const pageId = store.state.page
        store.commit('error', false)
        const waitBeforeChangingLoadingState = setTimeout(() => store.commit('loading', true), 300)
        listenForActivity(await ensureConversationPrepared(pageId))
        let timeout = 0
        api({
            ...{
                chatServer: store.getters.chatServer(pageId),
            },
            ...message,
            ...{
                perMessageCallback: (serverMessage) => {
                    processServerMessage(serverMessage, pageId, (t) => timeout += t)
                },
                callback: (response) => {
                    clearTimeout(waitBeforeChangingLoadingState)
                    if (responseWasQueued(response)) {
                        // The turn is running on the server; loading clears
                        // when the completed/failed event (or polling) lands.
                        store.commit('loading', true)
                    } else {
                        store.commit('loading', false)
                    }
                    if (message.callback) {
                        message.callback(response)
                    }
                },
                errorHandler: (error) => {
                    clearTimeout(waitBeforeChangingLoadingState)
                    store.commit('loading', false)
                    store.commit('error', error)
                    if (message.errorHandler) {
                        message.errorHandler(error)
                    }
                    if (message.from === 'visitor' && showMessage) {
                        store.state.input = message
                    }
                }
            }
        })
        if (showMessage) {
            writeToMessages({
                ...message,
                ...{
                    from: 'visitor',
                }
            }, pageId)
        }
    }

    const restoreConversation = async (pageId, conversationId) => {
        const page = store.state.config.pages.find(p => p.id === pageId)
        const endpoint = page?.conversationsEndpoint || store.state.config.conversationsEndpoint
        if (!endpoint) {
            store.commit('page', fallbackPage())
            return
        }

        try {
            const response = await client().get(`${endpoint}/${conversationId}`)
            onResumeConversation(response.data)
        } catch (e) {
            // The persisted thread is gone (deleted, expired, or permissions
            // changed) — clear the stale pointer and fall back.
            console.error('Failed to restore conversation', e)
            store.commit('clearConversation', pageId)
            store.commit('page', fallbackPage())
        }
    }

    const onResumeConversation = (conversation) => {
        const pageId = conversation.page_id || store.state.config.pages[0]?.id || 'chat'
        const page = store.state.config.pages.find(p => p.id === pageId)

        if (!page) {
            console.error('No matching page for conversation', conversation)
            return
        }

        store.commit('clearConversation', pageId)

        conversation.messages.forEach(msg => {
            writeToMessages({
                text: msg.content,
                from: msg.role === 'user' ? 'visitor' : 'chatbot',
                time: msg.created_at ? new Date(msg.created_at).getTime() : null,
            }, pageId)
        })

        store.commit('conversationId', {
            pageId,
            conversationId: conversation.id,
        })
        store.commit('page', pageId)
    }

    return {
        say,
        sayAsBot,
        whisper,
        writeToMessages,
        restoreConversation,
        onResumeConversation,
    }
}
