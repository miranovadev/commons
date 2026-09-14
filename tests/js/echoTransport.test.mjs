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
  .replace('export function createEcho', 'function createEcho')
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
  const echo = vm.runInContext(`${echoSource}\ncreateEcho();`, context);
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
