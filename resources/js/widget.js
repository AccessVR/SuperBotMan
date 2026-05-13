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

    // Read the iframe-side persisted state so we can boot in the same
    // docked / popup mode the user left things in. Same localStorage
    // key the chat iframe uses (same origin), so docked / open flags
    // survive a reload.
    const STORAGE_KEY = `super-botman:state:${config.userId || 'anon'}`
    let persistedState = {}
    try {
        const raw = window.localStorage?.getItem(STORAGE_KEY)
        persistedState = raw ? (JSON.parse(raw) || {}) : {}
    } catch (e) {
        persistedState = {}
    }

    // Merge-write so we don't trample the slice chat.js owns
    // (page / context / conversationId).
    const persistWidgetState = () => {
        try {
            const raw = window.localStorage?.getItem(STORAGE_KEY)
            const current = raw ? (JSON.parse(raw) || {}) : {}
            window.localStorage?.setItem(STORAGE_KEY, JSON.stringify({
                ...current,
                open,
                docked: mode === 'docked',
            }))
        } catch (e) {
            // Storage full / disabled / private mode — silently ignore.
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

    const dockedMargin = isMobile ? 0 : 16
    const applyDockedPosition = () => {
        beacon.style.display = 'none'
        chat.style.position = 'fixed'
        chat.style.top = dockedMargin + 'px'
        chat.style.right = dockedMargin + 'px'
        chat.style.bottom = dockedMargin + 'px'
        chat.style.width = dockedWidth + 'px'
        chat.style.height = `calc(100% - ${dockedMargin * 2}px)`
        chat.style.display = 'block'
        document.body.style.transition = 'padding-right 0.3s ease'
        document.body.style.paddingRight = (dockedWidth + dockedMargin * 2) + 'px'
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
                document.body.style.paddingRight = (dockedWidth + dockedMargin * 2) + 'px'
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
            persistWidgetState()
        },
        close () {
            open = false
            onToggle()
            persistWidgetState()
        },
        toggle () {
            open = !open
            onToggle()
            persistWidgetState()
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
            persistWidgetState()
        },
        undock () {
            mode = 'popup'
            open = true
            applyPopupPosition()
            callChatMethod('super-botman.chat.docked', { docked: false })
            persistWidgetState()
        },
        context (data) {
            callChatMethod('super-botman.chat.context', data)
        },
        startConversation (text, options = {}) {
            // Make sure the panel is visible before we hand control to
            // the iframe; the user can't act on a preloaded prompt they
            // can't see.
            if (!open) {
                open = true
                onToggle()
                persistWidgetState()
            }
            callChatMethod('super-botman.chat.startConversation', {
                text: typeof text === 'string' ? text : '',
                pageId: options.pageId || null,
            })
        },
    }

    const initClient = () => {
        window.superbotmanChatWidget = superbotmanChatWidget

        // Honor an explicit close from a prior session before anything
        // else. dock() opens the panel as a side effect of its work, so
        // if persistedState.docked got stuck at true while open is false
        // (a stale combination that shouldn't happen but has in practice
        // on mobile), the docked branch would overrule the user's choice
        // to close. Closed-state-wins guarantees that doesn't happen.
        if (persistedState.open === false) {
            // intentionally closed — leave the beacon visible and the
            // chat panel hidden, regardless of any other persisted flag.
        } else if (persistedState.docked) {
            superbotmanChatWidget.dock()
        } else if (persistedState.open === true) {
            superbotmanChatWidget.open()
        } else if (config.openByDefault) {
            // openByDefault is a first-visit fallback only — once the
            // user interacts, the branches above take over.
            superbotmanChatWidget.open()
        }

        // Signal to the host page that the widget's controller API is
        // attached and ready to receive context updates. Hosts listen
        // for this so they can push the initial page URL even on a
        // fresh load, before any in-app navigation has fired.
        window.dispatchEvent(new CustomEvent('super-botman:ready'))
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
