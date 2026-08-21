import axios from 'axios'

export const client = () => {
    // XXX: the goal here is to make the Axios client overridable; I don't think this is the best way to do it
    const instance = window.axios || axios

    // Offsite embeds authenticate with a server-minted visitor token
    // instead of session cookies (which cross-site iframes may not
    // have). The token doubles as the CSRF credential server-side.
    const embedToken = window.superbotmanWidget?.embedToken
    if (embedToken) {
        instance.defaults.headers.common['X-Embed-Chat-Token'] = embedToken
    }

    return instance
}

// The origin of the window that hosts our iframes. Same-origin installs
// leave parentOrigin unset and fall back to our own origin; embedded
// installs get it injected server-side from the validated frame request.
export const parentOrigin = () => {
    return window.superbotmanWidget?.parentOrigin || window.location.origin
}

export const emitMessage = (method, params = {}) => {
    window.parent.postMessage({
        method: 'super-botman.' + method,
        params
    }, parentOrigin())
}

// Only endpoints the server put into the widget config are POSTable.
// api() is reachable from a postMessage handler, so a caller-supplied
// URL must never be trusted — a hostile frame could otherwise make the
// widget POST the visitor's cookies to an arbitrary host.
const resolveChatServer = (requested) => {
    const config = window.superbotmanWidget || {}
    const allowed = [config.chatServer, ...(config.pages || []).map(page => page.chatServer)].filter(Boolean)

    return allowed.includes(requested) ? requested : config.chatServer
}

export const api = ({chatServer = window.superbotmanWidget.chatServer, text, interactive = false, attachment = null, perMessageCallback, callback, errorHandler}) => {
    let data = new FormData()

    const postData = {
        userId: window.superbotmanWidget.userId,
        message: text,
        attachment: attachment,
        interactive: interactive ? '1' : '0',
        context: JSON.stringify(window.superbotmanWidget.context || {}),
    }

    Object.keys(postData).forEach(key => data.append(key, postData[key]))

    client().post(resolveChatServer(chatServer), data).then(response => {
        const messages = response.data.messages || [];

        if (perMessageCallback) {
            messages.forEach(message => perMessageCallback(message))
        }

        if (callback) {
            callback(response.data);
        }
    }).catch(errorHandler)
}

export const MessageTypes = {
    ClientAction: 'client_action',
    TypingIndicator: 'typing_indicator',
    Text: 'text',
    List: 'list',
    Image: 'image',
    Audio: 'audio',
    Video: 'video',
    File: 'file',
}
