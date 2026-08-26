import { v4 as uuidv4 } from 'uuid'

// Ensure the config carries at least one conversational page; entries
// mounted without explicit pages get a default derived from top-level
// config.
export const seedDefaultPages = (config) => {
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
}

// The Vuex options shared by every SuperBotMan document (widget iframe,
// full-screen console). `initial` seeds the slice of state an entry
// hydrates from persistence (the widget) or fixes structurally (the
// console); defaults match the widget's original literals.
export const chatStoreOptions = (config, initial = {}) => ({
    state: {
        config,
        open: initial.open ?? false,
        docked: initial.docked ?? false,
        context: initial.context || {},
        title: null,
        page: initial.page || null,
        messages: config.pages.reduce((messages, page) => { messages[page.id] = []; return messages }, {}),
        conversation: null,
        conversationId: initial.conversationId || {},
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
