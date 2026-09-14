import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/** Each observing page owns its connection, channels, and cleanup. */
export function createEcho() {
  const key = import.meta.env.VITE_REVERB_APP_KEY;
  if (!key) return null;

  const scheme = (import.meta.env.VITE_REVERB_SCHEME || window.location.protocol.replace(':', '')).toLowerCase();
  const forceTLS = scheme === 'https' || scheme === 'wss';

  try {
    return new Echo<'reverb'>({
      broadcaster: 'reverb',
      Pusher,
      key,
      wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
      wsPort: Number(import.meta.env.VITE_REVERB_PORT || 80),
      wssPort: Number(import.meta.env.VITE_REVERB_PORT || 443),
      forceTLS,
      // Pusher's ws transport handles both schemes; forceTLS selects wss.
      enabledTransports: ['ws'],
      authorizer: (channel) => ({
        authorize: async (socketId, callback) => {
          try {
            const csrf = await fetch('/api/csrf-cookie', { credentials: 'include', headers: { Accept: 'application/json' } });
            if (!csrf.ok) throw new Error('Could not initialise channel authorization.');
            const token = document.cookie.split('; ').find(cookie => cookie.startsWith('XSRF-TOKEN='))?.slice(11);
            const response = await fetch('/broadcasting/auth', {
              method: 'POST', credentials: 'include',
              headers: { Accept: 'application/json', 'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest', ...(token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {}) },
              body: JSON.stringify({ socket_id: socketId, channel_name: channel.name }),
            });
            if (!response.ok) throw new Error('Channel authorization was denied.');
            callback(null, await response.json());
          } catch (error) { callback(error instanceof Error ? error : new Error('Channel authorization failed.'), null); }
        },
      }),
    });
  } catch { return null; }
}
