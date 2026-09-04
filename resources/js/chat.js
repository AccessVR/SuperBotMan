import { createApp } from 'vue'
import { createStore } from 'vuex'
import Chat from './components/Chat.vue'
import { client, parentOrigin } from './utils'
import { connectEcho } from './echo'
import { seedDefaultPages, chatStoreOptions } from './store'
import { createThemeApplier, resolveBootDark, followThemeMessages } from './theme'

const app = createApp(Chat)

const config = window.superbotmanWidget

const applyTheme = createThemeApplier(config)
applyTheme(resolveBootDark(config))
followThemeMessages(applyTheme, parentOrigin())

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

connectEcho(config)
seedDefaultPages(config)

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

// Seed only the widget's persisted slice — the same storage key also
// carries the `open` flag widget.js writes, which must NOT seed the
// store (the parent window drives open state via postMessage).
const store = createStore(chatStoreOptions(config, {
    docked: persisted.docked,
    context: persisted.context,
    page: persisted.page,
    conversationId: persisted.conversationId,
}))

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
