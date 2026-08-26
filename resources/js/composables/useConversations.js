import { ref, computed } from 'vue'
import { client } from '../utils'

// The conversation-history client shared by the widget's home-screen
// list (ConversationList.vue) and the console sidebar (Console.vue).
// Pass a pinned `pageId` to lock the endpoint to one page; without it
// the endpoint follows the store's current page (widget behavior).
export function useConversations(store, { pageId = null } = {}) {
    const conversations = ref([])

    // Per-page conversations endpoint, falling back to the widget-level
    // default. The host app supplies this via the registered agent's
    // channel — there's no hardcoded "/botman/conversations" anymore.
    const conversationsEndpoint = computed(() => {
        const id = pageId || store.state.page
        const page = store.state.config.pages?.find(p => p.id === id)
        return page?.conversationsEndpoint
            || store.state.config.conversationsEndpoint
            || null
    })

    const fetchConversations = async () => {
        if (!conversationsEndpoint.value) {
            conversations.value = []
            return
        }
        try {
            const response = await client().get(conversationsEndpoint.value)
            conversations.value = response.data
        } catch (e) {
            console.error('Failed to fetch conversations', e)
        }
    }

    const resumeConversation = async (id) => {
        if (!conversationsEndpoint.value) return null
        try {
            const response = await client().get(`${conversationsEndpoint.value}/${id}`)
            return response.data
        } catch (e) {
            console.error('Failed to load conversation', e)
            return null
        }
    }

    const deleteConversation = async (id) => {
        if (!conversationsEndpoint.value) return false
        // Deleting is immediate and (from the user's seat) irreversible —
        // one plain confirm beats a vanished conversation.
        if (!window.confirm('Delete this conversation? It will disappear from your history.')) return false
        try {
            await client().delete(`${conversationsEndpoint.value}/${id}`)
            conversations.value = conversations.value.filter(c => c.id !== id)
            return true
        } catch (e) {
            console.error('Failed to delete conversation', e)
            return false
        }
    }

    const formatTime = (isoString) => {
        if (!isoString) return ''
        const date = new Date(isoString)
        const now = new Date()
        const diffMs = now - date
        const diffMins = Math.floor(diffMs / 60000)

        if (diffMins < 1) return 'just now'
        if (diffMins < 60) return `${diffMins}m ago`

        const diffHours = Math.floor(diffMins / 60)
        if (diffHours < 24) return `${diffHours}h ago`

        const diffDays = Math.floor(diffHours / 24)
        if (diffDays < 7) return `${diffDays}d ago`

        return date.toLocaleDateString()
    }

    return {
        conversations,
        fetchConversations,
        resumeConversation,
        deleteConversation,
        formatTime,
    }
}
