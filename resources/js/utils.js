import axios from 'axios'

export const client = () => {
    // XXX: the goal here is to make the Axios client overridable; I don't think this is the best way to do it
    return window.axios || axios
}

export const emitMessage = (method, params = {}) => {
    window.parent.postMessage({
        method: 'super-botman.' + method,
        params
    })
}

export const api = ({server = window.superbotmanWidget.chatServer, text, interactive = false, attachment = null, perMessageCallback, callback, errorHandler}) => {
    let data = new FormData()

    const postData = {
        userId: window.superbotmanWidget.userId,
        message: text,
        attachment: attachment,
        interactive: interactive ? '1' : '0',
        context: JSON.stringify(window.superbotmanWidget.context || {}),
    }

    Object.keys(postData).forEach(key => data.append(key, postData[key]))

    client().post(server, data).then(response => {
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