import { createApp } from 'vue'
import { createStore } from 'vuex'
import Beacon from './components/Beacon.vue'

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
