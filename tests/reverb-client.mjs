// Transport smoke-test client; no credentials beyond the public test app key.
import Pusher from 'pusher-js';
// Node sockets omit Origin; supply the same header a browser sends.
const WebSocket = Pusher.Runtime.getWebSocketAPI();
Pusher.Runtime.createWebSocket = url => new WebSocket(url, [], { headers: { Origin: 'http://localhost' } });
const pusher = new Pusher('commons-test-key', {
  cluster: '', wsHost: '127.0.0.1', wsPort: Number(process.argv[2]),
  forceTLS: false, enabledTransports: ['ws'], disableStats: true,
});
pusher.connection.bind('error', error => console.error(JSON.stringify(error)));
const timer = setTimeout(() => { console.error('Timed out waiting for Reverb'); pusher.disconnect(); process.exit(1); }, 15000);
const channel = pusher.subscribe('transport-test');
channel.bind('pusher:subscription_succeeded', () => console.log('SUBSCRIBED'));
channel.bind('invalidation', event => {
  console.log(`RECEIVED ${JSON.stringify(event)}`);
  clearTimeout(timer); pusher.disconnect(); process.exit(0);
});
