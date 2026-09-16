import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { stripTypeScriptTypes, createRequire } from 'node:module';
import vm from 'node:vm';
import { webcrypto } from 'node:crypto';
import { setTimeout as wait } from 'node:timers/promises';

const require = createRequire(import.meta.url);
const pusherSource = readFileSync(require.resolve('pusher-js/dist/web/pusher.js'), 'utf8');
const echoSource = stripTypeScriptTypes(readFileSync('resources/js/realtime/echo.ts', 'utf8'))
  .replace(/^import .* from .*;\n/gm, '')
  .replace('export function getEcho', 'function getEcho')
  .replaceAll('import.meta.env', 'env');

async function connectionAttempt(env, protocol) {
  const urls = [];
  const timers = new Set();
  const context = vm.createContext({
    env, JSON, crypto: webcrypto, navigator: { onLine: true },
    location: { protocol, hostname: 'commons.example' },
    document: { body: {}, location: { protocol } },
    addEventListener() {}, removeEventListener() {},
    setTimeout(callback, delay) { const id = setTimeout(callback, delay); timers.add(id); return id; },
    clearTimeout, setInterval, clearInterval,
    WebSocket: class {
      constructor(url) { urls.push(url); }
      close() {}
    },
  });
  context.window = context;
  context.self = context;
  vm.runInContext(pusherSource, context);
  // Keep the real browser Pusher strategy; replace only Echo's thin wrapper.
  context.Echo = class {
    constructor(options) { this.pusher = new context.Pusher(options.key, { ...options, cluster: 'reverb' }); }
    disconnect() { this.pusher.disconnect(); }
  };
  const echo = vm.runInContext(`${echoSource}\ngetEcho();`, context);
  assert.equal(vm.runInContext('getEcho()', context), echo, 'repeated consumers share the transport');
  try {
    await wait(30);
    return urls;
  } finally {
    echo?.disconnect();
    for (const timer of timers) clearTimeout(timer);
  }
}

for (const scheme of ['https', 'wss', undefined]) {
  test(`secure Reverb opens a TLS WebSocket with scheme ${scheme ?? 'inferred from page'}`, async () => {
    const urls = await connectionAttempt({ VITE_REVERB_APP_KEY: 'test-key',
      VITE_REVERB_HOST: 'reverb.example', VITE_REVERB_SCHEME: scheme }, 'https:');
    assert.equal(urls.length, 1);
    assert.match(urls[0], /^wss:\/\/reverb\.example:443\/app\/test-key\?/);
  });
}

test('local HTTP Reverb retains its non-TLS custom port', async () => {
  const urls = await connectionAttempt({ VITE_REVERB_APP_KEY: 'local-key',
    VITE_REVERB_HOST: 'localhost', VITE_REVERB_PORT: '8080', VITE_REVERB_SCHEME: 'http' }, 'http:');
  assert.equal(urls.length, 1);
  assert.match(urls[0], /^ws:\/\/localhost:8080\/app\/local-key\?/);
});

test('a missing public key leaves realtime disabled', async () => {
  assert.deepEqual(await connectionAttempt({}, 'https:'), []);
});

test('the shared transport is lazy and repeated consumers do not construct another Echo', () => {
  let constructions = 0;
  const context = vm.createContext({ env: { VITE_REVERB_APP_KEY: 'key' }, Pusher: {},
    window: { location: { protocol: 'https:', hostname: 'commons.example' } },
    Echo: class { constructor() { constructions++; } },
  });
  vm.runInContext(echoSource, context);
  assert.equal(constructions, 0);
  const first = vm.runInContext('getEcho()', context);
  assert.equal(vm.runInContext('getEcho()', context), first);
  assert.equal(constructions, 1);
});

test('shared private-channel authorization retains cookies, CSRF headers, and denial handling', async () => {
  const requests = []; let denied = false;
  const context = vm.createContext({ env: { VITE_REVERB_APP_KEY: 'key' }, Pusher: {},
    window: { location: { protocol: 'https:', hostname: 'commons.example' } },
    document: { cookie: 'XSRF-TOKEN=csrf%3Dtoken' },
    Echo: class { constructor(options) { this.options = options; } },
    fetch: async (url, options) => {
      requests.push({ url, options });
      return { ok: !(denied && url === '/broadcasting/auth'), json: async () => ({ auth: 'signed' }) };
    },
  });
  const echo = vm.runInContext(`${echoSource}\ngetEcho();`, context);
  const authorize = () => new Promise(resolve => echo.options.authorizer({ name: 'private-participant.a' })
    .authorize('1.2', (error, result) => resolve({ error, result })));
  const allowed = await authorize();
  assert.equal(allowed.error, null); assert.equal(allowed.result.auth, 'signed');
  assert.deepEqual(requests.map(request => request.url), ['/api/csrf-cookie', '/broadcasting/auth']);
  assert.ok(requests.every(request => request.options.credentials === 'include'));
  assert.equal(requests[1].options.headers['X-XSRF-TOKEN'], 'csrf=token');
  assert.deepEqual(JSON.parse(requests[1].options.body), { socket_id: '1.2', channel_name: 'private-participant.a' });
  denied = true;
  const failure = await authorize();
  assert.equal(failure.error.message, 'Channel authorization was denied.');
  assert.equal(failure.result, null);
});
