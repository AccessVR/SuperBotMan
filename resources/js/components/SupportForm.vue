<template>
    <div class="flex-1 flex flex-col p-4">
        <div
            v-if="status === 'success'"
            class="flex-1 flex flex-col items-center justify-center px-6 text-center"
        >
            <div
                class="rounded-full w-16 h-16 mb-6 flex items-center justify-center"
                :style="{ backgroundColor: $store.state.config.mainColor }"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
            </div>
            <h2 class="text-xl font-bold text-gray-900 mb-2">
                Request received
            </h2>
            <p class="text-sm text-gray-600 mb-6">
                {{ confirmation }}
            </p>
            <button
                type="button"
                class="text-sm font-semibold underline"
                :style="{ color: $store.state.config.mainColor }"
                @click="reset"
            >
                File another request
            </button>
        </div>

        <form
            v-else
            class="flex flex-col gap-4"
            @submit.prevent="submit"
        >
            <p class="text-sm text-gray-600">
                Tell us what you need help with and our support team will reply by email.
            </p>

            <template v-if="requiresContact">
                <div class="flex flex-col gap-1">
                    <label for="sbm-support-email" class="text-xs font-semibold text-gray-700">
                        Your email
                    </label>
                    <input
                        id="sbm-support-email"
                        v-model.trim="email"
                        type="email"
                        autocomplete="email"
                        maxlength="255"
                        class="w-full px-3 py-2 bg-white rounded-2xl shadow-sm border-none outline-none focus:outline-none focus:ring-0 text-sm placeholder:text-gray-400"
                        placeholder="you@example.com"
                        :disabled="submitting"
                    />
                    <p v-if="fieldError('email')" class="text-xs text-red-500">
                        {{ fieldError('email') }}
                    </p>
                </div>

                <div class="flex flex-col gap-1">
                    <label for="sbm-support-name" class="text-xs font-semibold text-gray-700">
                        Your name <span class="font-normal text-gray-400">(optional)</span>
                    </label>
                    <input
                        id="sbm-support-name"
                        v-model.trim="name"
                        type="text"
                        autocomplete="name"
                        maxlength="255"
                        class="w-full px-3 py-2 bg-white rounded-2xl shadow-sm border-none outline-none focus:outline-none focus:ring-0 text-sm placeholder:text-gray-400"
                        :disabled="submitting"
                    />
                    <p v-if="fieldError('name')" class="text-xs text-red-500">
                        {{ fieldError('name') }}
                    </p>
                </div>
            </template>

            <div class="flex flex-col gap-1">
                <label for="sbm-support-subject" class="text-xs font-semibold text-gray-700">
                    Subject
                </label>
                <input
                    id="sbm-support-subject"
                    v-model.trim="subject"
                    type="text"
                    maxlength="200"
                    class="w-full px-3 py-2 bg-white rounded-2xl shadow-sm border-none outline-none focus:outline-none focus:ring-0 text-sm placeholder:text-gray-400"
                    placeholder="A short summary"
                    :disabled="submitting"
                />
                <p v-if="fieldError('subject')" class="text-xs text-red-500">
                    {{ fieldError('subject') }}
                </p>
            </div>

            <div class="flex flex-col gap-1">
                <label for="sbm-support-body" class="text-xs font-semibold text-gray-700">
                    How can we help?
                </label>
                <textarea
                    id="sbm-support-body"
                    v-model.trim="body"
                    rows="5"
                    maxlength="8000"
                    class="w-full px-3 py-2 bg-white rounded-2xl shadow-sm border-none outline-none focus:outline-none focus:ring-0 resize-none text-sm placeholder:text-gray-400"
                    placeholder="Describe what you were doing and what went wrong"
                    :disabled="submitting"
                ></textarea>
                <p v-if="fieldError('body')" class="text-xs text-red-500">
                    {{ fieldError('body') }}
                </p>
            </div>

            <div class="flex flex-col gap-1">
                <input
                    ref="fileInput"
                    type="file"
                    multiple
                    class="hidden"
                    :disabled="submitting"
                    @change="onFilesChange"
                />
                <button
                    type="button"
                    class="self-start px-3 py-2 rounded-full text-white text-xs font-semibold transition-opacity disabled:opacity-40 disabled:cursor-not-allowed"
                    :style="{ backgroundColor: $store.state.config.mainColor }"
                    :disabled="submitting"
                    @click="fileInput?.click()"
                >
                    Attach Files
                </button>
                <p v-if="fieldError('attachments') || fieldError('attachments.0')" class="text-xs text-red-500">
                    {{ fieldError('attachments') || fieldError('attachments.0') }}
                </p>
                <ul v-if="files.length" class="flex flex-col gap-1 mt-1 list-none">
                    <li
                        v-for="(file, i) in files"
                        :key="i"
                        class="text-xs text-gray-600 truncate"
                    >
                        {{ file.name }}
                    </li>
                </ul>
            </div>

            <p v-if="formError" class="text-xs text-red-500">
                {{ formError }}
            </p>

            <button
                type="submit"
                class="w-full py-2.5 rounded-full text-white text-sm font-semibold transition-opacity disabled:opacity-40 disabled:cursor-not-allowed"
                :style="{ backgroundColor: $store.state.config.mainColor }"
                :disabled="submitting"
            >
                {{ submitting ? 'Sending…' : 'Submit request' }}
            </button>
        </form>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { client } from '../utils'

const props = defineProps({
    page: {
        type: Object,
        required: true,
    },
})

// Client-side mirror of the server's rules — the server is authoritative
// and re-validates; these just give immediate feedback.
const maxFiles = 5
const maxFileBytes = 10 * 1024 * 1024
const minSubject = 4
const minBody = 10

const requiresContact = computed(() => !!props.page?.requires_contact)

const subject = ref('')
const body = ref('')
const email = ref('')
const name = ref('')
const files = ref([])
const fileInput = ref(null)
const status = ref('idle')
const errors = ref({})
const formError = ref('')
const confirmation = ref('')

const submitting = computed(() => status.value === 'submitting')

const fieldError = (key) => errors.value[key]?.[0] || null

const onFilesChange = (event) => {
    files.value = Array.from(event.target.files || [])
}

const validate = () => {
    const next = {}
    if (subject.value.length < minSubject) {
        next.subject = ['Please add a short subject.']
    }
    if (body.value.length < minBody) {
        next.body = ['Please give us a little more detail.']
    }
    if (requiresContact.value && !email.value) {
        next.email = ['Please add an email so we can reply to you.']
    }
    if (files.value.length > maxFiles) {
        next.attachments = [`You can attach up to ${maxFiles} files.`]
    } else if (files.value.some(f => f.size > maxFileBytes)) {
        next['attachments.0'] = ['Each file must be 10 MB or smaller.']
    }
    errors.value = next
    return Object.keys(next).length === 0
}

const submit = async () => {
    if (submitting.value) {
        return
    }

    formError.value = ''
    if (!validate()) {
        return
    }

    status.value = 'submitting'

    const data = new FormData()
    data.append('subject', subject.value)
    data.append('body', body.value)
    if (email.value) {
        data.append('email', email.value)
    }
    if (name.value) {
        data.append('name', name.value)
    }
    files.value.forEach(file => data.append('attachments[]', file))

    try {
        const response = await client().post(props.page.formEndpoint, data, {
            headers: { Accept: 'application/json' },
        })
        confirmation.value = response.data?.message
            || 'Thanks — your request has been filed. Our support team will reply by email.'
        status.value = 'success'
    } catch (error) {
        const response = error.response
        if (response?.status === 422) {
            errors.value = response.data?.errors || {}
            status.value = 'idle'
            return
        }
        formError.value = 'Something went wrong sending your request. Please try again.'
        status.value = 'error'
    }
}

const reset = () => {
    subject.value = ''
    body.value = ''
    email.value = ''
    name.value = ''
    files.value = []
    errors.value = {}
    formError.value = ''
    confirmation.value = ''
    status.value = 'idle'
    if (fileInput.value) {
        fileInput.value.value = ''
    }
}
</script>
