// Transport smoke-test client; no credentials beyond the public test app key.
import Pusher from 'pusher-js';
const pusher = new Pusher('commons-test-key', {
  cluster: '', wsHost: '127.0.0.1', wsPort: Number(process.argv[2]),
  forceTLS: false, enabledTransports: ['ws'], disableStats: true,
});
const timer = setTimeout(() => { console.error('Timed out waiting for Reverb'); pusher.disconnect(); process.exit(1); }, 15000);
const channel = pusher.subscribe('transport-test');
channel.bind('pusher:subscription_succeeded', () => console.log('SUBSCRIBED'));
channel.bind('invalidation', event => {
  console.log(`RECEIVED ${JSON.stringify(event)}`);
  clearTimeout(timer); pusher.disconnect(); process.exit(0);
});
