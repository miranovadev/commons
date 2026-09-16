import { test } from 'node:test';
import assert from 'node:assert/strict';
import { setTimeout as wait } from 'node:timers/promises';
import { mergeProposition, notificationQueue } from '../../resources/js/realtime/notifications.ts';

const node = id => ({ id, statement: id, register: 'activity', tier: 'expressive' });

test('duplicate graph notifications replace the existing node without duplicating it', () => {
  const draft = [node('origin')];
  mergeProposition(draft, node('child'));
  mergeProposition(draft, { ...node('child'), statement: 'authoritative update' });
  assert.deepEqual(draft.map(item => item.id), ['origin', 'child']);
  assert.equal(draft[1].statement, 'authoritative update');
});

test('a mutation burst fetches each resource once and preserves an event arriving during its fetch', async () => {
  const calls = [];
  let release;
  const queue = notificationQueue(async ids => {
    calls.push(ids);
    if (calls.length === 1) await new Promise(resolve => { release = resolve; });
  }, 1);
  queue.add('a'); queue.add('a'); queue.add('b');
  await wait(20);
  assert.deepEqual(calls, [['a', 'b']]);
  queue.add('a'); release();
  await wait(20);
  assert.deepEqual(calls, [['a', 'b'], ['a']]);
  queue.dispose();
});

test('HTTP failure does not poison the queue, and leaving a page cancels pending work', async () => {
  const calls = [];
  const queue = notificationQueue(async ids => { calls.push(ids); throw new Error('offline'); }, 1);
  queue.add('first'); await wait(20);
  queue.add('second'); await wait(20);
  queue.add('cancelled'); queue.dispose(); await wait(20);
  assert.deepEqual(calls, [['first'], ['second']]);
});

test('a late historical detail response removes a pruned node instead of resurrecting it', () => {
  const draft = [node('origin'), node('retired')];
  mergeProposition(draft, { ...node('retired'), active: false });
  mergeProposition(draft, { ...node('retired'), active: false });
  assert.deepEqual(draft.map(item => item.id), ['origin']);
});
