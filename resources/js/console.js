import { createApp } from 'vue'
import { createStore } from 'vuex'
import Console from './components/Console.vue'
import { connectEcho } from './echo'
import { seedDefaultPages, chatStoreOptions } from './store'

const config = window.superbotmanWidget

connectEcho(config)
seedDefaultPages(config)

// No localStorage persistence: the docked widget owns the
// `super-botman:state:{userId}` slice; console continuity rides the URL
// hash instead. `open: true` because there is no beacon here — the page
// IS the chat, and ChatBody's open-watch scroll expects it.
const store = createStore(chatStoreOptions(config, { open: true }))

// Top-level page: emitMessage() posts to window.parent, which here is
// the window itself, so bridge messages self-deliver. Act on the client
// actions that matter outside an iframe (navigation) and ignore widget
// chrome messages (chat.init / chat.esc / chat.close / chat.dock).
window.addEventListener('message', (event) => {
    if (event.source !== window) return
    if (event.data?.method !== 'super-botman.chat.clientAction') return
    const { action, payload } = event.data.params || {}
    if (action === 'navigate' && payload?.url) {
        window.location.assign(payload.url)
    } else if (action === 'openUrl' && payload?.url) {
        window.open(payload.url, '_blank')
    }
})

createApp(Console).use(store).mount('#console')
