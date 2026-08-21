import { createApp } from 'vue'
import { createStore } from 'vuex'
import Chat from './components/Chat.vue'
import { v4 as uuidv4 } from 'uuid'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { client } from './utils'

const app = createApp(Chat)

const config = window.superbotmanWidget

// Offsite embeds: keep the visitor's identity across page loads. The
// frame GET mints a fresh token (and a fresh visitor id) every time —
// only this iframe can know whether the visitor has been here before,
// so if a previous token is stored, exchange it for a fresh one that
// keeps the ORIGINAL visitor id, and the conversation history follows.
const EMBED_TOKEN_KEY = 'super-botman:embed-token'
if (config.embedded && config.embedToken) {
    try {
        const stored = window.localStorage?.getItem(EMBED_TOKEN_KEY)
        if (stored && stored !== config.embedToken && config.tokenExchangeEndpoint) {
            client().post(config.tokenExchangeEndpoint, { token: stored }).then(response => {
                if (response.data?.token) {
                    config.embedToken = response.data.token
                    window.localStorage?.setItem(EMBED_TOKEN_KEY, response.data.token)
                }
            }).catch(() => {
                // Stored token unusable (revoked key, org disabled) —
                // fall forward to the fresh identity.
                window.localStorage?.setItem(EMBED_TOKEN_KEY, config.embedToken)
            })
        } else {
            window.localStorage?.setItem(EMBED_TOKEN_KEY, config.embedToken)
        }
    } catch (e) {
        // Storage disabled — the visitor is simply new every visit.
    }
}

// The widget lives in its own iframe, so the host page's Echo instance
// (if any) is out of reach — build our own connection when the host
// supplies one via config.activity.echo (public key + host only; auth
// rides the same-origin /broadcasting/auth with session cookies).
const echoConfig = config.activity?.echo
if (echoConfig?.key && !window.Echo) {
    window.Pusher = Pusher
    window.Echo = new Echo({
        broadcaster: echoConfig.broadcaster || 'reverb',
        key: echoConfig.key,
        wsHost: echoConfig.host,
        wsPort: echoConfig.port ?? 443,
        wssPort: echoConfig.port ?? 443,
        forceTLS: (echoConfig.scheme ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    })
}

// add default page
if (!config.pages?.length) {
    config.pages = [
        {
            id: 'chat',
            title: 'Chat',
            buttonTitle: 'Chat with our bot',
            buttonDescription: 'Available to help 24/7',
            icon: config.icons.bot,
            introMessage: config.introMessage,
            chatServer: config.chatServer,
        }
    ]
}

// Hydrate prior conversation state from localStorage so navigating
// between host-app pages (which can re-mount the widget iframe and
// wipe the in-memory store) doesn't lose the active conversation
// thread. We persist the small slice of state needed to resume the
// LLM conversation; messages themselves are re-fetched from the
// server on mount when a thread id is restored, so they can't go
// stale.
//
// This key lives on the APP origin (we are the iframe) and holds only
// the chat slice; widget.js keeps open/docked on the embedding page's
// origin. Embedded installs are cookieless, so config.userId is a
// fresh session id every load — key on a stable name instead (the
// browser partitions our storage per embedding site anyway).
const STORAGE_KEY = 'super-botman:state:' + (config.embedded ? 'embed' : (config.userId || 'anon'))

const loadPersisted = () => {
    try {
        const raw = window.localStorage?.getItem(STORAGE_KEY)
        return raw ? (JSON.parse(raw) || {}) : {}
    } catch (e) {
        return {}
    }
}

const persisted = loadPersisted()

// Thread the restored conversationId into the widget context so the
// FIRST outbound message after a reload still resumes correctly,
// even before the user opens the panel and the message history
// finishes loading.
if (persisted.context?.conversationId) {
    window.superbotmanWidget.context = {
        ...(window.superbotmanWidget.context || {}),
        conversationId: persisted.context.conversationId,
    }
}

const store = createStore({
    state: {
        config,
        open: false,
        docked: persisted.docked ?? false,
        context: persisted.context || {},
        title: null,
        page: persisted.page || null,
        messages: config.pages.reduce((messages, page) => { messages[page.id] = []; return messages }, {}),
        conversation: null,
        conversationId: persisted.conversationId || {},
        conversations: {},
        input: {
            text: '',
            attachment: null,
        },
        showChatInput: false,
        loading: false,
        waiting: false,
        error: false,
        // Live agent-activity label ("Listing experiences…") broadcast by
        // the host while a reply is being generated; shown beside the
        // typing indicator. Null when idle or when Echo is unavailable.
        activity: null,
    },
    getters: {
        chatServer: (state) => (pageId) => {
            return state.config.pages.find(page => page.id === pageId)?.chatServer || state.config.chatServer
        },
        showBackButton(state) {
            return state.page !== 'home'
        },
    },
    mutations: {
        resetInput(state) {
            state.input.text = ''
            state.input.attachment = null
        },
        page(state, pageId) {
            state.page = pageId
            if (pageId !== 'home') {
                state.context = { ...state.context, pageId }
                window.superbotmanWidget.context = { ...window.superbotmanWidget.context, pageId }
            }
        },
        messages(state, { message, pageId }) {
            let data = typeof message === 'string' ? { text: message } : message
            if (typeof data.id === "undefined") {
                data.id = uuidv4()
            }
            if (typeof data.time === "undefined") {
                data.time = new Date().getTime()
            }
            state.messages[pageId || state.page].push(data)
        },
        loading(state, value) {
            state.loading = value
            if (!value) {
                state.activity = null
            }
        },
        activity(state, value) {
            state.activity = value
        },
        waiting(state, value) {
            state.waiting = value
        },
        error(state, value) {
            state.error = value
        },
        docked(state, value) {
            state.docked = value
        },
        context(state, data) {
            // MERGE incoming context fields, don't replace. Host apps
            // typically push partial updates from a page-navigation
            // hook (e.g. {url, path, resourceName, resourceId}) and
            // expect previously-threaded values like conversationId to
            // survive. Wholesale replace drops the active thread on
            // every host navigation.
            state.context = { ...state.context, ...data }
            window.superbotmanWidget.context = { ...window.superbotmanWidget.context, ...data }
        },
        conversationId(state, { pageId, conversationId }) {
            state.conversationId[pageId] = conversationId
            state.context = { ...state.context, conversationId }
            window.superbotmanWidget.context = { ...window.superbotmanWidget.context, conversationId }
        },
        conversations(state, { pageId, conversations }) {
            state.conversations[pageId] = conversations
        },
        clearConversation(state, pageId) {
            state.messages[pageId] = []
            state.conversationId[pageId] = null
            const { conversationId, ...rest } = state.context
            state.context = rest
            window.superbotmanWidget.context = { ...rest }
        },
    },
})

// Persist a slim slice of state on every mutation so the next page
// load (or browser-tab reopen) can rehydrate the active thread.
store.subscribe((mutation, state) => {
    try {
        window.localStorage?.setItem(STORAGE_KEY, JSON.stringify({
            page: state.page,
            context: state.context,
            conversationId: state.conversationId,
            docked: state.docked,
        }))
    } catch (e) {
        // Storage full / disabled / private mode — silently ignore.
    }
})

app.use(store)

app.mount('#chat')
