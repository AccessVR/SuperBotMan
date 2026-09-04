import { createApp } from 'vue'
import { createStore } from 'vuex'
import Beacon from './components/Beacon.vue'
import { parentOrigin } from './utils'
import { createThemeApplier, resolveBootDark, followThemeMessages } from './theme'

const applyTheme = createThemeApplier(window.superbotmanWidget)
applyTheme(resolveBootDark(window.superbotmanWidget))
followThemeMessages(applyTheme, parentOrigin())

const app = createApp(Beacon)

const store = createStore({
    state: {
        config: window.superbotmanWidget,
        open: false,
    },
    mutations: {
        //
    }
})

app.use(store)

app.mount('#beacon')
