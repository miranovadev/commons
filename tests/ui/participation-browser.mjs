// Exercise the real Participant page against the isolated cognitive fixture.
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';

export async function checkParticipation(browser) {
  const server = spawn(process.execPath, ['tests/ui/serve.mjs'], { stdio: ['ignore', 'pipe', 'inherit'] });
  try {
    await new Promise((resolve, reject) => {
      server.stdout.on('data', data => { if (data.toString().includes('Workspace review:')) resolve(); });
      server.on('error', reject);
      server.on('exit', code => reject(new Error('Participation preview exited: ' + code)));
    });
    for (const viewport of [{ width: 390, height: 844 }, { width: 1440, height: 960 }]) {
      const page = await browser.newPage({ viewport });
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      await page.goto('http://127.0.0.1:4173/frame?phase=mediate');
      const first = page.locator('[data-role=read] article').first();
      await first.getByText('Proposition details').click();
      assert.ok(await first.locator('details').evaluate(element => element.open));
      await page.locator('[data-role=write]').scrollIntoViewIfNeeded();
      const counterpart = page.locator('[data-role=read] article').nth(1);
      await counterpart.getByRole('button', { name: 'Reconcile', exact: true }).click();
      await page.getByRole('dialog').waitFor();
      await page.getByRole('textbox', { name: /Statement/ }).fill('Both proposals preserve the reasoning behind a decision.');
      assert.ok(await page.getByRole('button', { name: 'Apply contribution' }).isEnabled());
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
      assert.doesNotMatch(await page.locator('body').innerText(), /Field State|Chirality|Closure preview|Potential Charge/i);
      assert.deepEqual(errors, []);
      await page.close();
    }
  } finally {
    server.kill();
  }
}
