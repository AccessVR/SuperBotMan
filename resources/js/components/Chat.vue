<template>
    <div
        class="flex flex-col bg-gray-100 border-gray-200 p-0 h-dvh sbm-popup-chrome"
    >
        <ChatHeader 
            @back="onBack"
            @close="emitMessage('chat.close')"
        />
        <ChatBody
            ref="body"
        >
            <ChatPage 
                v-if="$store.state.page === 'home'"
                id="home"
            >
                <template #heading>
                    <div class="-mt-px pt-8 pb-4 -mb-px" :style="{ backgroundColor: $store.state.config.mainColor }">
                        <h1 class="text-lg font-bold text-white text-center mb-2">
                            Start a conversation
                        </h1>
                        <p class="text-gray-200 text-sm text-center">
                            What channel do you prefer?
                        </p>
                    </div>
                </template>
                <div class="p-4">
                    <ChatPageButton
                        v-for="page in $store.state.config.pages"
                        :key="page.id"
                        @click="onPageButtonClick(page.id)"
                    >
                        <template 
                            v-if="page.avatar"
                            #icon
                        >
                            <div class="w-10 h-10 relative">
                                <img class="rounded-full w-10 h-10" :src="page.avatar" />
                                <div class="rounded-full w-3 h-3 border border-2 border-white bg-green-500 absolute bottom-0 right-0"></div>
                            </div>
                        </template>
                        <template 
                            v-else
                            #icon-svg
                        >
                            <span class="icon" v-html="page.icon"></span>
                        </template>
                        <template #title>
                            {{ page.buttonTitle || page.title }}
                        </template>
                        <template #description>
                            {{ page.buttonDescription || page.description }}
                        </template>
                    </ChatPageButton>
                    <ConversationList
                        @resume="onResumeConversation"
                    />
                    <!--
                    <ChatPageButton
                        @click="$store.state.page = 'ai'"
                    >
                        <template #icon-svg>
                            <span class="icon" v-html="$store.state.config.icons.bot"></span>
                        </template>
                        <template #title>
                            AI Answers
                        </template>
                        <template #description>
                            Instant answers from your data
                        </template>
                    </ChatPageButton>
                    <ChatPageButton
                        @click="$store.state.page = 'search'"
                    >
                        <template #icon-svg>
                            <span class="icon" v-html="$store.state.config.icons.search"></span>
                        </template>
                        <template #title>
                            Search
                        </template>
                        <template #description>
                            Search your data
                        </template>
                    </ChatPageButton>
                    <ChatPageButton
                        @click="$store.state.page = 'email'"
                    >
                        <template #icon-svg>
                            <span class="icon" v-html="$store.state.config.icons.email"></span>
                        </template>
                        <template #title>
                            Email
                        </template>
                        <template #description>
                            We respond within 48 hours
                        </template>
                    </ChatPageButton>
                    <ChatPageButton
                        @click="$store.state.page = 'chat'"
                    >
                        <template #icon>
                            <div class="w-10 h-10 relative">
                                <img class="rounded-full w-10 h-10" src="https://placehold.co/100x100" />
                                <div class="rounded-full w-3 h-3 border border-2 border-white bg-green-500 absolute bottom-0 right-0"></div>
                            </div>
                        </template>
                        <template #title>
                            Chat
                        </template>
                        <template #description>
                            Message with someone now
                        </template>
                    </ChatPageButton>
                    -->
                </div>
            </ChatPage>

            <template
                v-for="page in $store.state.config.pages"
            >
                <ChatPage
                    :key="page.id"
                    :id="page.id"
                    v-if="$store.state.page === page.id"
                    :title="page.title"
                >
                    <SupportForm
                        v-if="page.type === 'form'"
                        :page="page"
                    />
                    <ChatMessages
                        v-else
                        :pageId="page.id"
                        @message="onMessage"
                    />
                </ChatPage>
            </template>

            <!--
            <ChatPage 
                v-if="$store.state.page === 'messages'"
                title="Start a conversation" 
                description="What channel do you prefer?"
            >
                <ChatMessages />
            </ChatPage>
            <ChatPage 
                v-if="$store.state.page === 'ai'"
                title="AI Answers" 
            >
                <ChatMessages />
            </ChatPage>
            <ChatPage 
                v-if="$store.state.page === 'email'"
                title="Send an email" 
            >
                <ChatMessages />
            </ChatPage>
            <ChatPage 
                v-if="$store.state.page === 'search'"
                title="Search" 
            >
                <ChatMessages />
            </ChatPage>
            <ChatPage 
                v-if="$store.state.page === 'chat'"
                title="Chat" 
            >
                <ChatMessages />
            </ChatPage>
            -->
        </ChatBody>
        <ChatFooter>
            <ChatInput
                v-if="$store.state.showChatInput"
                ref="chatInput"
                @submit="onChatInputSubmit"
            />
        </ChatFooter>
    </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { useStore } from 'vuex'
import { emitMessage, api, client, parentOrigin } from '../utils'
import ChatHeader from './ChatHeader.vue'
import ChatBody from './ChatBody.vue'
import ChatFooter from './ChatFooter.vue'
import ChatMessages from './ChatMessages.vue'
import SupportForm from './SupportForm.vue'
import ChatInput from './ChatInput.vue'
import ChatPage from './ChatPage.vue'
import ChatPageButton from './ChatPageButton.vue'
import ConversationList from './ConversationList.vue'

const store = useStore()
const body = ref(null)
const chatInput = ref(null)

const pickChatPageId = () => {
    const pages = store.state.config.pages || []
    const def = store.state.config.defaultPage
    if (def && def !== 'home' && pages.some(p => p.id === def)) {
        return def
    }
    return pages.find(p => p.id !== 'home')?.id || pages[0]?.id || 'chat'
}

const writeToMessages = (message, pageId = null) => {
    store.commit('messages', { message, pageId })
}

const sayAsBot = (message) => {
    writeToMessages({
      ...message,
      ...{
        type: 'text',
        from: 'chatbot',
      }  
    })
}

const whisper = (message) => {
    say(message, false)
}

// --- Live agent-activity narration -------------------------------------
// When the host configures an `activity` broadcast channel and the page
// has Echo available, the widget subscribes per conversation and shows
// what the agent is doing ("Listing experiences…") beside the typing
// indicator. Everything degrades silently to the plain indicator when
// Echo or the config is absent.

const conversationsEndpointFor = (pageId) => {
    const page = store.state.config.pages.find(p => p.id === pageId)
    return page?.conversationsEndpoint || store.state.config.conversationsEndpoint
}

// Mint the conversation before its first message so the activity channel
// can be subscribed from the very first turn. Failures fall back to the
// legacy flow (the server creates the conversation on first dispatch).
const ensureConversationPrepared = async (pageId) => {
    const existing = store.state.conversationId[pageId]
    if (existing) {
        return existing
    }
    const endpoint = conversationsEndpointFor(pageId)
    if (!endpoint || !store.state.config.activity || !window.Echo) {
        return null
    }
    try {
        const response = await client().post(`${endpoint}/prepare`)
        const conversationId = response.data?.conversationId
        if (conversationId) {
            store.commit('conversationId', { pageId, conversationId })
        }
        return conversationId || null
    } catch (e) {
        return null
    }
}

let activityChannelName = null

const listenForActivity = (conversationId) => {
    const activity = store.state.config.activity
    if (!activity?.channel || !activity?.event || !window.Echo || !conversationId) {
        return
    }
    const channelName = activity.channel.replace('{conversationId}', conversationId)
    if (channelName === activityChannelName) {
        return
    }
    if (activityChannelName) {
        window.Echo.leave(activityChannelName)
    }
    activityChannelName = channelName
    // No loading guard here: the first tool event can arrive inside the
    // 300ms delay before `loading` flips true. Display is gated by the
    // loading branch in ChatMessage, and loading(false) clears the label.
    window.Echo.private(channelName).listen(activity.event, (event) => {
        store.commit('activity', event.label || null)
    })
}

const say = async (message, showMessage = true) => {
    const pageId = store.state.page
    store.commit('error', false)
    const waitBeforeChangingLoadingState = setTimeout(() => store.commit('loading', true), 300)
    listenForActivity(await ensureConversationPrepared(pageId))
    let timeout = 0
    api({
        ...{
            chatServer: store.getters.chatServer(pageId),
        },
        ...message,
        ...{
            perMessageCallback: (message) => {
                if (message.type === 'client_action') {
                    if (message.action === 'setConversationId') {
                        store.commit('conversationId', {
                            pageId,
                            conversationId: message.payload.id,
                        })
                        return
                    }
                    emitMessage('chat.clientAction', {
                        action: message.action,
                        payload: message.payload,
                    })
                    return
                }
                writeToMessages({
                    ...message,
                    ...{
                        from: 'chatbot',
                        timeout: timeout += (message.timeout || 0),
                    }
                }, pageId)
            },
            callback: (response) => {
                clearTimeout(waitBeforeChangingLoadingState)
                store.commit('loading', false)
                if (message.callback) {
                    message.callback(response)
                }
            },
            errorHandler: (error) => {
                clearTimeout(waitBeforeChangingLoadingState)
                store.commit('loading', false)
                store.commit('error', error)
                if (message.errorHandler) {
                    message.errorHandler(error)
                }
                if (message.from === 'visitor' && showMessage) {
                    store.state.input = message
                }
            }
        }
    })
    if (showMessage) {
        writeToMessages({
            ...message,
            ...{
                from: 'visitor',
            }
        }, pageId)
    }
}

const restoreConversation = async (pageId, conversationId) => {
    const page = store.state.config.pages.find(p => p.id === pageId)
    const endpoint = page?.conversationsEndpoint || store.state.config.conversationsEndpoint
    if (!endpoint) {
        store.commit('page', store.state.config.defaultPage || 'home')
        return
    }

    try {
        const response = await client().get(`${endpoint}/${conversationId}`)
        onResumeConversation(response.data)
    } catch (e) {
        // The persisted thread is gone (deleted, expired, or permissions
        // changed) — clear the stale pointer and fall back to home.
        console.error('Failed to restore conversation', e)
        store.commit('clearConversation', pageId)
        store.commit('page', store.state.config.defaultPage || 'home')
    }
}

onMounted(() => {
    emitMessage('chat.init')

    // If we hydrated a non-home page with an active thread id, restore
    // its history from the server so the user lands back where they
    // left off after a host-page reload.
    const hydratedPage = store.state.page
    const hydratedConversationId = hydratedPage ? store.state.conversationId[hydratedPage] : null

    if (hydratedPage && hydratedPage !== 'home' && hydratedConversationId) {
        restoreConversation(hydratedPage, hydratedConversationId)
        return
    }

    store.commit('page', store.state.config.defaultPage || 'home')
})

const onBack = () => {
    emitMessage('chat.back')
    store.commit('page', 'home')
}

const onPageButtonClick = (pageId) => {
    store.commit('clearConversation', pageId)
    store.commit('page', pageId)
}

const onChatInputSubmit = (message) => {
    if (!store.state.loading && !store.state.waiting) {
        say({ ...store.state.input })
        store.commit('resetInput')
    }
}

const onMessage = () => {
    // Scrolling is handled by ChatBody's MutationObserver
}

const onResumeConversation = (conversation) => {
    const pageId = conversation.page_id || store.state.config.pages[0]?.id || 'chat'
    const page = store.state.config.pages.find(p => p.id === pageId)

    if (!page) {
        console.error('No matching page for conversation', conversation)
        return
    }

    store.commit('clearConversation', pageId)

    conversation.messages.forEach(msg => {
        writeToMessages({
            text: msg.content,
            from: msg.role === 'user' ? 'visitor' : 'chatbot',
            time: msg.created_at ? new Date(msg.created_at).getTime() : null,
        }, pageId)
    })

    store.commit('conversationId', {
        pageId,
        conversationId: conversation.id,
    })
    store.commit('page', pageId)
}

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        emitMessage('chat.esc')
    }
})

window.addEventListener('message', (event) => {
    // Only the window embedding us may drive the chat. For same-origin
    // installs that's our own origin; embedded installs get the
    // validated parent origin injected server-side.
    if (event.origin !== parentOrigin()) {
        return
    }
    if (event.data?.method === 'super-botman.widget.toggle') {
        store.state.open = event.data.params.open
    }
    if (event.data?.method === 'super-botman.chat.api') {
        // Never honor a caller-supplied endpoint: a message sender
        // choosing the URL would make this iframe POST the visitor's
        // credentials wherever it likes. api() routes to configured
        // endpoints only.
        const { server, chatServer, ...params } = event.data.params || {}
        api({ ...params, ...{
            callback: (data) => {
                emitMessage('chat.api.response', data)
            },
            errorHandler: (error) => {
                emitMessage('chat.api.error', {
                    message: error.message,
                    status: error.response.status,
                    headers: error.response.headers,
                    data: error.response.data,
                })
            }
        }})
    }
    if (event.data?.method === 'super-botman.chat.sayAsBot') {
        sayAsBot(event.data.params)
    }
    if (event.data?.method === 'super-botman.chat.whisper') {
        whisper(event.data.params)
    }
    if (event.data?.method === 'super-botman.chat.say') {
        say(event.data.params)
    }
    if (event.data?.method === 'super-botman.chat.page') {
        store.commit('page', event.data.params.id)
    }
    if (event.data?.method === 'super-botman.chat.writeToMessages') {
        writeToMessages(event.data.params)
    }
    if (event.data?.method === 'super-botman.chat.docked') {
        store.commit('docked', event.data.params.docked)
    }
    if (event.data?.method === 'super-botman.chat.context') {
        store.commit('context', event.data.params)
    }
    if (event.data?.method === 'super-botman.chat.startConversation') {
        const text = event.data.params?.text || ''
        const pageId = event.data.params?.pageId || pickChatPageId()
        store.commit('clearConversation', pageId)
        store.commit('page', pageId)
        store.state.input.text = text
        nextTick(() => chatInput.value?.focus())
    }
})
</script>