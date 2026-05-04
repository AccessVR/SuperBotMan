<template>
    <div ref="container" class="flex-grow overflow-y-auto">
        <slot />
    </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { useStore } from 'vuex'

const store = useStore()
const container = ref(null)

const scrollToBottom = () => {
    const el = container.value
    if (el) {
        el.scrollTop = el.scrollHeight
    }
}

const scrollToNewMessage = () => {
    const el = container.value
    if (!el) return

    const messages = el.querySelectorAll('[data-message-id]')
    if (!messages.length) return

    const lastMessage = messages[messages.length - 1]
    const containerRect = el.getBoundingClientRect()
    const messageRect = lastMessage.getBoundingClientRect()

    // Scroll so the top of the new message is within 30px of the container top
    const offset = messageRect.top - containerRect.top + el.scrollTop - 30
    el.scrollTo({ top: offset, behavior: 'smooth' })
}

// Watch for new messages via MutationObserver on the container
onMounted(() => {
    const el = container.value
    if (!el) return

    const observer = new MutationObserver(() => {
        nextTick(scrollToNewMessage)
    })

    observer.observe(el, { childList: true, subtree: true })
})

// Scroll to bottom when the widget opens with existing messages
store.watch(
    (state) => state.open,
    (isOpen) => {
        if (isOpen) {
            nextTick(scrollToBottom)
        }
    }
)

defineExpose({ scrollToBottom, scrollToNewMessage })
</script>
