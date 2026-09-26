// Synthetic browser acceptance; does not access any Commons API or participant.
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { mkdir } from 'node:fs/promises';
import { chromium } from 'playwright';
const directory = 'test-results/inspector';
await mkdir(directory, { recursive: true });
const server = spawn(process.execPath, ['tests/ui/serve-graph.mjs'], { stdio: ['ignore', 'pipe', 'inherit'] });
await new Promise((resolve, reject) => {
  server.stdout.on('data', data => { if (data.toString().includes('Graph review:')) resolve(); });
  server.on('error', reject);
  server.on('exit', code => reject(new Error(`Preview exited: ${code}`)));
});
let browser;
try {
  browser = await chromium.launch();
  for (const viewport of [{ width: 1440, height: 960 }, { width: 390, height: 844 }]) {
    const page = await browser.newPage({ viewport });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const layout = viewport.width < 768 ? 'mobile' : 'desktop';
    await page.goto('http://127.0.0.1:4175');
    const canvas = page.locator('.substrate-graph-canvas');
    const workspace = page.locator('.graph-workspace');
    const inspector = page.getByRole('region', { name: 'Selected Proposition inspector' });
    const origin = page.locator('[data-proposition-id="origin"]');
    await page.getByRole('button', { name: 'Collapse graph workspace' }).click();
    await origin.click();
    assert.equal(await workspace.getAttribute('data-state'), 'collapsed');
    await inspector.waitFor();
    const box = await inspector.boundingBox();
    assert.ok(box.x >= 0 && box.y >= 0 && box.x + box.width <= viewport.width && box.y + box.height <= viewport.height);
    for (const theme of ['dark', 'light']) {
      if (theme === 'light') await page.getByRole('button', { name: 'Toggle light / dark' }).click();
      for (const tab of ['Proposition', 'Field State', 'Closure']) {
        await page.getByRole('tab', { name: tab, exact: true }).click();
        await page.screenshot({ path: `${directory}/${layout}-${theme}-${tab.replaceAll(' ', '-').toLowerCase()}.png` });
      }
    }
    const before = await canvas.getAttribute('style');
    await page.getByRole('button', { name: 'Deselect proposition' }).click();
    assert.equal(await inspector.count(), 0);
    assert.equal(await canvas.getAttribute('style'), before);
    assert.equal(await workspace.getAttribute('data-state'), 'collapsed');
    await page.getByRole('button', { name: 'Open graph workspace' }).click();
    await page.getByRole('button', { name: 'Show Origin', exact: true }).click();
    assert.equal(await page.locator('.proposition-card[aria-pressed="true"]').count(), 0);
    await origin.click();
    await page.getByRole('button', { name: 'Next proposition', exact: true }).click();
    assert.equal(await page.getByRole('tab', { name: 'Closure', exact: true }).count(), 0);
    await page.getByRole('button', { name: 'Previous proposition', exact: true }).click();
    await page.getByRole('radio', { name: 'Topology', exact: true }).check();
    assert.equal(await origin.getByRole('img', { name: 'Committal', exact: true }).count(), 1);
    await page.screenshot({ path: `${directory}/${layout}-topology.png` });
    assert.deepEqual(errors, []);
    await page.close();
  }
} finally {
  await browser?.close();
  server.kill();
}
