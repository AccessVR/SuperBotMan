<template>
    <button 
        class="block w-full rounded-full text-white p-4 focus:outline outline-4 outline-offset-2 outline-blue-500"
        :style="style"
        @click.prevent="toggleChat"
        @keydown.escape.prevent="emitMessage('beacon.esc')"
    > 
        <span v-if="$store.state.open" class="icon" v-html="config.icons.open"></span>
        <span v-if="!$store.state.open" class="icon" v-html="config.icons.closed"></span>
        <span class="sr-only">Click to Chat</span>
    </button>
</template>

<script setup>
import { ref, computed } from 'vue'
import { emitMessage } from '../utils'

let config = ref(window.superbotmanWidget)

let style = computed(() => {
    return {
        backgroundColor: config.value.beaconColor,
        width: config.value.beaconSize + 'px',
        height: config.value.beaconSize + 'px',
    }
})

let toggleChat = () => {
    emitMessage('beacon.click')
}

window.addEventListener('message', (event) => {
    if (event.data?.method === 'super-botman.widget.toggle') {
        $store.state.open = event.data.params.open
    }
})
</script>

<style scoped>
:hover {
    background-color: none !important;
}
:active {
    scale: 0.95;
}
</style>