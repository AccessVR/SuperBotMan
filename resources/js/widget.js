!function() {

    let config = window.superbotmanWidget

    // Which layout the panel uses. This tracks the VIEWPORT, not
    // window.screen: a desktop window dragged narrow is as cramped as a
    // phone, and screen.width never changes when it is. Live rather
    // than a boot-time snapshot — see onBreakpointChange.
    const mobileQuery = window.matchMedia('(max-width: 639px)')
    let isMobile = mobileQuery.matches

    // On an external site the widget runs on a different origin than the
    // app serving the iframes, so every postMessage in either direction
    // is addressed and validated against the app's origin — derived from
    // the frame endpoint, which is absolute for embedded installs and
    // relative (resolving to our own origin) for same-origin installs.
    const appOrigin = new URL(config.frameEndpoint, window.location.href).origin

    const embedded = !!config.embedded

    // Host pages (embedded conversation player, iframe, Unity app) hide
    // the launcher beacon by adding this class to <body>. widget.js runs
    // in the host document, so this is a same-document class check — no
    // cross-frame access needed.
    const hideBeaconClass = config.hideBeaconClass || '--hide-beacon'
    const beaconForcedHidden = () => document.body.classList.contains(hideBeaconClass)

    // A configured size is either a bare pixel count or a CSS length
    // such as '100%'.
    const cssSize = (value) => {
        const size = String(value)

        return size.indexOf('%') === -1 ? size + 'px' : size
    }

    const chatWidth = () => cssSize(isMobile ? config.mobileWidth : config.desktopWidth)
    const chatHeight = () => cssSize(isMobile ? config.mobileHeight : config.desktopHeight)

    const appendQuery = (endpoint, pairs) => {
        const query = Object.entries(pairs)
            .map(([key, value]) => key + '=' + encodeURIComponent(value))
            .join('&')

        return endpoint + (endpoint.indexOf('?') === -1 ? '?' : '&') + query
    }

    // Theme. 'light' / 'dark' pin the widget; 'class' follows a dark
    // class on the host <html> (the Tailwind class convention — the
    // class name itself is configurable); 'media' follows the OS
    // preference. The resolved theme rides the frame URLs at boot (so a
    // frame never paints in the wrong theme) and is re-relayed as a
    // message whenever the host flips.
    const themeMode = config.theme || 'light'
    const themeDarkClass = config.themeDarkClass || 'dark'
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)')

    const resolveDark = () => {
        if (themeMode === 'dark') {
            return true
        }
        if (themeMode === 'class') {
            return document.documentElement.classList.contains(themeDarkClass)
        }
        if (themeMode === 'media') {
            return prefersDark.matches
        }

        return false
    }

    let themeDark = resolveDark()

    let frameEndpoint = config.frameEndpoint
    let beaconEndpoint = config.beaconEndpoint

    // An advisory hint for hosts that render the frames differently on
    // mobile. Fixed at boot: the frames are never reloaded, so it
    // reflects the viewport the widget started in, not the current one.
    if (isMobile) {
        frameEndpoint = appendQuery(frameEndpoint, { mobile: 'true' })
        beaconEndpoint = appendQuery(beaconEndpoint, { mobile: 'true' })
    }

    frameEndpoint = appendQuery(frameEndpoint, { theme: themeDark ? 'dark' : 'light' })
    beaconEndpoint = appendQuery(beaconEndpoint, { theme: themeDark ? 'dark' : 'light' })

    // Tell the frames who is embedding them. The server validates this
    // against the organization's allowed domains and echoes it back into
    // the frame config as parentOrigin — the postMessage target for
    // iframe→host messages. Lying here is self-defeating: messages
    // addressed to a wrong origin are dropped by the browser.
    if (embedded) {
        frameEndpoint = appendQuery(frameEndpoint, { parent: window.location.origin })
        beaconEndpoint = appendQuery(beaconEndpoint, { parent: window.location.origin })
    }

    // Boot-time open/docked state. This key lives on the embedding
    // page's origin and belongs to widget.js alone; the chat iframe
    // keeps its own slice (page / context / conversation) on the app
    // origin. The two stores are only the same place for same-origin
    // installs, so neither side may assume it can read the other's.
    const STORAGE_KEY = 'super-botman:widget'
    let persistedState = {}
    try {
        const raw = window.localStorage?.getItem(STORAGE_KEY)
        persistedState = raw ? (JSON.parse(raw) || {}) : {}
    } catch (e) {
        persistedState = {}
    }

    const persistWidgetState = () => {
        try {
            window.localStorage?.setItem(STORAGE_KEY, JSON.stringify({
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
    chat.style.width = chatWidth()
    chat.style.height = chatHeight()
    chat.style.border = 'none'
    chat.style.display = 'none'

    let beacon = document.createElement('iframe')
    beacon.src = beaconEndpoint
    beacon.style.position = 'fixed'
    // Offsets are reduced by ~7.5px from the visual target because the
    // beacon iframe is beaconSize+15 with the circle centered, so the
    // visible badge sits half the 15px padding farther from the corner
    // than the iframe edge. 33/13 lands the circle at ~40/20px.
    beacon.style.bottom = isMobile ? '13px' : '33px'
    beacon.style.right = isMobile ? '13px' : '33px'
    beacon.style.zIndex = '1000'
    beacon.style.width = (config.beaconSize + 15) + 'px'
    beacon.style.height = (config.beaconSize + 15) + 'px'
    beacon.style.border = 'none'

    const applyPopupPosition = () => {
        beacon.style.display = beaconForcedHidden() ? 'none' : ''
        chat.style.position = 'fixed'
        chat.style.bottom = isMobile ? '0' : '120px'
        chat.style.right = isMobile ? '0' : '40px'
        chat.style.top = ''
        chat.style.width = chatWidth()
        chat.style.height = chatHeight()
        chat.style.display = open ? 'block' : 'none'
        document.body.style.transition = ''
        document.body.style.paddingRight = ''
    }

    let dockedMargin = isMobile ? 0 : 16

    // Fixed host chrome (the account bar, a maintenance banner) publishes its
    // height as a CSS variable on <html>. A docked panel reads it so it tucks
    // below that chrome instead of hiding under it.
    const topChromeHeight = () => {
        const styles = getComputedStyle(document.documentElement)
        const px = (name) => parseFloat(styles.getPropertyValue(name)) || 0

        return px('--account-bar-h') + px('--maintenance-banner-h')
    }

    const applyDockedPosition = () => {
        beacon.style.display = 'none'
        const topMargin = dockedMargin + topChromeHeight()
        chat.style.position = 'fixed'
        chat.style.top = topMargin + 'px'
        chat.style.right = dockedMargin + 'px'
        chat.style.bottom = dockedMargin + 'px'
        chat.style.width = dockedWidth + 'px'
        chat.style.height = `calc(100% - ${topMargin + dockedMargin}px)`
        chat.style.display = 'block'
        document.body.style.transition = 'padding-right 0.3s ease'
        document.body.style.paddingRight = (dockedWidth + dockedMargin * 2) + 'px'
        open = true
    }

    // Reconcile the beacon's visibility with the host body class. The
    // force-hide always wins; otherwise popup mode shows the beacon and
    // docked mode keeps it hidden (its own logic already did that).
    const refreshBeaconVisibility = () => {
        if (beaconForcedHidden()) {
            beacon.style.display = 'none'
        } else if (mode !== 'docked') {
            beacon.style.display = ''
        }
    }

    // Re-reconcile everything that depends on host body classes: the beacon's
    // visibility, and — while docked — the panel's offset below host chrome, so
    // it re-tucks if the account bar or a maintenance banner appears or leaves.
    const reconcileHostChrome = () => {
        refreshBeaconVisibility()

        if (mode === 'docked') {
            applyDockedPosition()
        }
    }

    // The widget outlives any single viewport size: a window dragged
    // across the breakpoint, a tablet rotated, a phone's URL bar
    // collapsing. Without this the panel keeps whichever geometry it
    // booted with — a desktop-sized popup on a phone-width viewport,
    // or a full-bleed panel swallowing a widened window.
    const onBreakpointChange = () => {
        isMobile = mobileQuery.matches
        dockedMargin = isMobile ? 0 : 16
        beacon.style.bottom = isMobile ? '13px' : '33px'
        beacon.style.right = isMobile ? '13px' : '33px'

        if (mode === 'docked') {
            applyDockedPosition()

            return
        }

        applyPopupPosition()
    }

    const callChatMethod = (method, params) => {
        let message = {
            method,
            params
        }
        chat.contentWindow?.postMessage(message, appOrigin)
        beacon.contentWindow?.postMessage(message, appOrigin)
    }

    const callBeaconMethod = (method, params) => {
        let message = {
            method,
            params
        }
        beacon.contentWindow?.postMessage(message, appOrigin)
    }

    const relayMessageEvent = (event) => {
        const method = typeof event.data?.method === 'string' ? event.data.method : ''

        if (method.indexOf('super-botman.chat.') === 0) {
            callBeaconMethod(method, event.data.params)
        }
        if (method.indexOf('super-botman.beacon.') === 0) {
            callChatMethod(method, event.data.params)
        }
        if (method.indexOf('super-botman.widget.') === 0) {
            callChatMethod(method, event.data.params)
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

    // Push the current theme into both frames. Also called on demand so a
    // frame that (re)initializes after a flip still converges.
    const publishTheme = () => {
        callChatMethod('super-botman.widget.theme', { dark: themeDark })
    }

    const refreshTheme = () => {
        const dark = resolveDark()

        if (dark !== themeDark) {
            themeDark = dark
            publishTheme()
        }
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
            // Docking reflows the embedding page (body padding); on a
            // site we don't own that's not ours to do.
            if (embedded) {
                superbotmanChatWidget.open()
                return
            }
            dockedWidth = width || 375
            mode = 'docked'
            applyDockedPosition()
            callChatMethod('super-botman.chat.docked', { docked: true })
            persistWidgetState()
        },
        undock () {
            if (embedded) {
                return
            }
            mode = 'popup'
            open = true
            applyPopupPosition()
            callChatMethod('super-botman.chat.docked', { docked: false })
            persistWidgetState()
        },
        context (data) {
            callChatMethod('super-botman.chat.context', data)
        },
        // Drive the theme directly ('dark' / 'light'), for hosts whose
        // theme lives somewhere none of the config modes can see.
        theme (value) {
            themeDark = value === 'dark'
            publishTheme()
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

        // Pages that force-hide the beacon (the experience player, the
        // editor's nested preview) must not restore an open panel either:
        // with no badge there is no way to close or reopen it. The
        // persisted state is left untouched for the next ordinary page.
        if (beaconForcedHidden()) {
            // leave the panel closed on this page
        } else if (persistedState.docked && !embedded) {
            // Restoring docked from a prior session takes priority over
            // openByDefault — dock() opens the panel as part of its work.
            superbotmanChatWidget.dock()
        } else if (persistedState.open === true) {
            // If the user explicitly opened or closed the widget in a
            // prior session, honor that. openByDefault is a first-visit
            // fallback, not an every-reload override.
            superbotmanChatWidget.open()
        } else if (persistedState.open === false) {
            // intentionally closed — leave as-is
        } else if (config.openByDefault) {
            superbotmanChatWidget.open()
        }

        // Signal to the host page that the widget's controller API is
        // attached and ready to receive context updates. Hosts listen
        // for this so they can push the initial page URL even on a
        // fresh load, before any in-app navigation has fired.
        window.dispatchEvent(new CustomEvent('super-botman:ready'))
    }

    window.addEventListener('message', (event) => {
        // Only our two iframes get to drive the widget. Anything else on
        // the page — other frames, extensions, an opener — is ignored.
        if (event.origin !== appOrigin) {
            return
        }
        if (event.source !== chat.contentWindow && event.source !== beacon.contentWindow) {
            return
        }
        relayMessageEvent(event)
        if (event.data?.method === 'super-botman.chat.init') {
            initClient()
            // A frame booted (or re-booted) with the theme its URL carried;
            // re-publish in case the host flipped since that URL was built.
            publishTheme()
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

    const mount = () => {
        document.body.appendChild(chat)
        document.body.appendChild(beacon)

        reconcileHostChrome()

        mobileQuery.addEventListener('change', onBreakpointChange)

        // The host can add/remove classes at runtime (e.g. opening an embedded
        // player, or showing the account bar / maintenance banner), so watch
        // <body> and re-reconcile the beacon and a docked panel's top offset
        // when its class attribute changes.
        new MutationObserver(reconcileHostChrome).observe(document.body, {
            attributes: true,
            attributeFilter: ['class'],
        })

        // Follow the host's theme source live: the dark class on <html>
        // (a theme switcher toggling it), or the OS preference.
        if (themeMode === 'class') {
            new MutationObserver(refreshTheme).observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['class'],
            })
        } else if (themeMode === 'media') {
            prefersDark.addEventListener('change', refreshTheme)
        }
    }

    // An async loader (the embed snippet) usually executes after
    // DOMContentLoaded has already fired, so waiting for the event
    // would mean never mounting.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mount)
    } else {
        mount()
    }

}()
