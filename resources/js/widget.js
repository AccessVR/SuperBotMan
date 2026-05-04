!function() {

    const isMobile = window.screen.width < 640

    let config = window.superbotmanWidget

    let chatWidth = new String(isMobile ? config.mobileWidth : config.desktopWidth)
    if (chatWidth.indexOf('%') === -1) {
        chatWidth += 'px'
    }

    let chatHeight = new String(isMobile ? config.mobileHeight : config.desktopHeight)
    if (chatHeight.indexOf('%') === -1) {
        chatHeight += 'px'
    }

    let frameEndpoint = config.frameEndpoint
    if (isMobile) {
        if (frameEndpoint.indexOf('?') === -1) {
            frameEndpoint += '?mobile=true'
        } else {
            frameEndpoint += '&mobile=true'
        }
    }

    let beaconEndpoint = config.beaconEndpoint
    if (isMobile) {
        if (beaconEndpoint.indexOf('?') === -1) {
            beaconEndpoint += '?mobile=true'
        } else {
            beaconEndpoint += '&mobile=true'
        }
    }

    let open = false
    let mode = 'popup'
    let dockedWidth = 375

    let chat = document.createElement('iframe')
    chat.src = frameEndpoint
    chat.style.position = 'fixed'
    chat.style.bottom = isMobile ? '0' : '120px'
    chat.style.right = isMobile ? '0' : '40px'
    chat.style.zIndex = '1200'
    chat.style.width = chatWidth
    chat.style.height = chatHeight
    chat.style.border = 'none'
    chat.style.display = 'none'

    let beacon = document.createElement('iframe')
    beacon.src = beaconEndpoint
    beacon.style.position = 'fixed'
    beacon.style.bottom = isMobile ? '20px' : '40px'
    beacon.style.right = isMobile ? '20px' : '40px'
    beacon.style.zIndex = '1000'
    beacon.style.width = (config.beaconSize + 15) + 'px'
    beacon.style.height = (config.beaconSize + 15) + 'px'
    beacon.style.border = 'none'

    const applyPopupPosition = () => {
        beacon.style.display = ''
        chat.style.position = 'fixed'
        chat.style.bottom = isMobile ? '0' : '120px'
        chat.style.right = isMobile ? '0' : '40px'
        chat.style.top = ''
        chat.style.width = chatWidth
        chat.style.height = chatHeight
        chat.style.display = open ? 'block' : 'none'
        document.body.style.transition = ''
        document.body.style.paddingRight = ''
    }

    const applyDockedPosition = () => {
        beacon.style.display = 'none'
        chat.style.position = 'fixed'
        chat.style.top = '0'
        chat.style.right = '0'
        chat.style.bottom = '0'
        chat.style.width = dockedWidth + 'px'
        chat.style.height = '100%'
        chat.style.display = 'block'
        document.body.style.transition = 'padding-right 0.3s ease'
        document.body.style.paddingRight = dockedWidth + 'px'
        open = true
    }

    const callChatMethod = (method, params) => {
        let message = {
            method,
            params
        }
        chat.contentWindow.postMessage(message)
        beacon.contentWindow.postMessage(message)
    }

    const callBeaconMethod = (method, params) => {
        let message = {
            method,
            params
        }
        beacon.contentWindow.postMessage(message)
    }

    const relayMessageEvent = (event) => {
        if (event.data.method?.indexOf('super-botman.chat.') !== -1) {
            callBeaconMethod(event.data.method, event.data.params)
        }
        if (event.data.method?.indexOf('super-botman.beacon.') !== -1) {
            callChatMethod(event.data.method, event.data.params)
        }
    }

    const onToggle = () => {
        if (mode === 'docked') {
            if (open) {
                chat.style.display = 'block'
                document.body.style.paddingRight = dockedWidth + 'px'
            } else {
                // Closing from docked mode: undock and close
                mode = 'popup'
                document.body.style.paddingRight = ''
                applyPopupPosition()
                callChatMethod('super-botman.chat.docked', { docked: false })
            }
        } else {
            chat.style.display = open ? 'block' : 'none'
        }
        relayMessageEvent({
            data: {
                method: 'super-botman.widget.toggle',
                params: { open }
            }
        })
    }

    const superbotmanChatWidget = {
        open () {
            open = true
            onToggle()
        },
        close () {
            open = false
            onToggle()
        },
        toggle () {
            open = !open
            onToggle()
        },
        say (message) {
            callChatMethod('super-botman.chat.say', typeof message !== 'object' ? { text: message } : message)
        },
        writeToMessages (message) {
            callChatMethod('super-botman.chat.writeToMessages',  typeof message !== 'object' ? { text: message } : message)
        },
        whisper (message) {
            callChatMethod('super-botman.chat.whisper',  typeof message !== 'object' ? { text: message } : message)
        },
        sayAsBot (message) {
            callChatMethod('super-botman.chat.sayAsBot',  typeof message !== 'object' ? { text: message } : message)
        },
        page (id) {
            callChatMethod('super-botman.chat.page', {
                id
            })
        },
        api (text, interactive = false, attachment = null) {
            callChatMethod('super-botman.chat.api', typeof text === 'object' ? text : {
                text,
                interactive,
                attachment
            })
        },
        dock (width) {
            dockedWidth = width || 375
            mode = 'docked'
            applyDockedPosition()
            callChatMethod('super-botman.chat.docked', { docked: true })
        },
        undock () {
            mode = 'popup'
            open = true
            applyPopupPosition()
            callChatMethod('super-botman.chat.docked', { docked: false })
        },
        context (data) {
            callChatMethod('super-botman.chat.context', data)
        },
    }

    const initClient = () => {
        window.superbotmanChatWidget = superbotmanChatWidget

        if (config.openByDefault) {
            superbotmanChatWidget.open()
        }
    }

    window.addEventListener('message', (event) => {
        relayMessageEvent(event)
        if (event.data?.method === 'super-botman.chat.init') {
            initClient()
        }
        if (event.data?.method === 'super-botman.beacon.click') {
            superbotmanChatWidget.toggle()
        }
        if (event.data?.method === 'super-botman.chat.close') {
            superbotmanChatWidget.close()
        }
        if (event.data?.method === 'super-botman.chat.dock') {
            superbotmanChatWidget.dock()
        }
        if (event.data?.method === 'super-botman.chat.undock') {
            superbotmanChatWidget.undock()
        }
        if (event.data?.method === 'super-botman.chat.esc') {
            superbotmanChatWidget.close()
        }
        if (event.data?.method === 'super-botman.beacon.esc') {
            superbotmanChatWidget.close()
        }
        if (event.data?.method === 'super-botman.chat.api.response') {
            console.log(event.data.params)
        }
        if (event.data?.method === 'super-botman.chat.api.error') {
            console.log(event.data.params)
        }
    })

    document.addEventListener('DOMContentLoaded', () => {
        document.body.appendChild(chat)
        document.body.appendChild(beacon)
    })

}()
