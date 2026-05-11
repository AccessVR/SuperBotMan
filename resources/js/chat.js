import { createApp } from 'vue'
import { createStore } from 'vuex'
import Chat from './components/Chat.vue'
import { v4 as uuidv4 } from 'uuid'

const app = createApp(Chat)

const config = window.superbotmanWidget

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
const STORAGE_KEY = `super-botman:state:${config.userId || 'anon'}`

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
// Merge with whatever's already in storage so we don't trample the
// `open` flag widget.js writes from the parent window.
store.subscribe((mutation, state) => {
    try {
        const raw = window.localStorage?.getItem(STORAGE_KEY)
        const current = raw ? (JSON.parse(raw) || {}) : {}
        window.localStorage?.setItem(STORAGE_KEY, JSON.stringify({
            ...current,
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
