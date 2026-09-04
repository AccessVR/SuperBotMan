<template>
    <div class="w-full px-3 pt-2 pb-3">
        <p
            v-if="showDisclaimer"
            class="text-xs text-sbm-ink text-center mb-2"
        >
            {{ page.disclaimer }}
        </p>
        <slot />
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useStore } from 'vuex'

const store = useStore()

const page = computed(() =>
    store.state.config.pages.find(p => p.id === store.state.page)
)

const showDisclaimer = computed(() => {
    if (!store.state.showChatInput) return false
    if (!page.value?.disclaimer) return false
    const messages = store.state.messages[store.state.page] || []
    return messages.length === 0
})
</script>
