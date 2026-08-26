import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

// The widget lives in its own iframe (and the console in its own tab),
// so the host page's Echo instance (if any) is out of reach — build our
// own connection when the host supplies one via config.activity.echo
// (public key + host only; auth rides the same-origin /broadcasting/auth
// with session cookies).
export const connectEcho = (config) => {
    const echoConfig = config.activity?.echo
    if (echoConfig?.key && !window.Echo) {
        window.Pusher = Pusher
        window.Echo = new Echo({
            broadcaster: echoConfig.broadcaster || 'reverb',
            key: echoConfig.key,
            wsHost: echoConfig.host,
            wsPort: echoConfig.port ?? 443,
            wssPort: echoConfig.port ?? 443,
            forceTLS: (echoConfig.scheme ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
        })
    }
}
