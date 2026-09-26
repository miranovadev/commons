import { test } from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';
import { createRequire } from 'node:module';

const result = await build({
  stdin: { contents: `
    import React from 'react';
    import { renderToStaticMarkup } from 'react-dom/server';
    import { MantineProvider } from '@mantine/core';
    import { ConnectionWakeSettings } from './resources/js/components/ConnectionWakeSettings';
    export { wakeFormValues, wakeConfigurationFor, validateWakeValues } from './resources/js/connections';
    export const render = values => renderToStaticMarkup(<MantineProvider env="test"><ConnectionWakeSettings values={values} onChange={() => {}} /></MantineProvider>);
  `, resolveDir: process.cwd(), loader: 'tsx' },
  bundle: true, platform: 'node', format: 'cjs', write: false, jsx: 'automatic',
});
const module = { exports: {} };
new Function('require', 'module', 'exports', result.outputFiles[0].text)(createRequire(import.meta.url), module, module.exports);
const { wakeFormValues, wakeConfigurationFor, validateWakeValues, render } = module.exports;

test('legacy attachments get Phase, 600 seconds and zero fixed offset', () => {
  const values = wakeFormValues({ trigger: { mode: 'interval', interval_minutes: 2 } });
  assert.equal(values.termination, 'phase');
  assert.equal(values.timeout_seconds, 600);
  assert.equal(values.offset_mode, 'seconds');
  assert.equal(values.offset_seconds, 0);
  assert.equal(wakeConfigurationFor(values).trigger.interval_minutes, 2);
  assert.deepEqual(validateWakeValues(values), {});
});

test('wake configuration round-trips both offset modes and every termination', () => {
  for (const termination of ['phase', 'cycle', 'rest']) for (const offset_mode of ['seconds', 'random']) {
    const values = { ...wakeFormValues(), trigger_mode: 'interval', termination, offset_mode, random_from: 30, random_to: 40, offset_seconds: 10 };
    assert.deepEqual(wakeFormValues(wakeConfigurationFor(values)), values);
  }
});

test('fixed and random controls are conditional and show whole-job semantics', () => {
  const fixed = render({ ...wakeFormValues(), trigger_mode: 'interval' });
  assert.match(fixed, /Offset seconds/);
  assert.doesNotMatch(fixed, /From \(seconds\)/);
  assert.match(fixed, /End wake job after/);
  assert.match(fixed, /Timeout \(seconds\)/);
  const random = render({ ...wakeFormValues(), trigger_mode: 'interval', offset_mode: 'random', termination: 'rest' });
  assert.match(random, /From \(seconds\)/);
  assert.match(random, /To \(seconds\)/);
  assert.doesNotMatch(random, /Offset seconds/);
  assert.match(random, /does not force Rest/);
  assert.match(random, /independently/);
  assert.doesNotMatch(render(wakeFormValues()), /Offset seconds/);
});

test('validation rejects invalid integers, reversed ranges and unsupported durations', () => {
  for (const field of ['offset_seconds', 'random_from', 'random_to']) for (const value of [-1, 61, 1.5, NaN]) {
    assert.ok(validateWakeValues({ ...wakeFormValues(), [field]: value })[field]);
  }
  for (const value of [0, -1, 1801, 1.5, NaN]) assert.ok(validateWakeValues({ ...wakeFormValues(), timeout_seconds: value }).timeout_seconds);
  assert.ok(validateWakeValues({ ...wakeFormValues(), offset_mode: 'random', random_from: 40, random_to: 30 }).random_to);
  assert.deepEqual(validateWakeValues({ ...wakeFormValues(), offset_mode: 'random', random_from: 30, random_to: 30 }), {});
});
