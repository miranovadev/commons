// Local, synthetic UI review only. No API calls or production credentials.
import { build } from 'esbuild';
import { createServer } from 'node:http';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const bundle = await build({ absWorkingDir: root, entryPoints: ['tests/ui/workspace-preview.tsx'], bundle: true, write: false,
  outdir: 'preview', jsx: 'automatic', sourcemap: 'inline', define: { 'process.env.NODE_ENV': '"development"' },
  plugins: [{ name: 'synthetic-api', setup(build) {
    build.onLoad({ filter: /services\/commonsApi\.ts$/ }, () => ({ contents: `
      import { fixture } from '${root}/tests/ui/fixture';
      export const useGetParticipantQuery = () => ({ data: fixture.participant });
      export const useGetParticipationQuery = () => ({ data: fixture.participation });
      export const useContributeMutation = () => [input => ({ unwrap: async () => {
        const output = document.querySelector('[data-last-action]');
        if (output) output.textContent = JSON.stringify(input.contribution);
        throw new Error('Preview only: contribution recorded, no data changed.');
      }}), { isLoading: false, reset() {} }];
      export const useEnterCycleMutation = () => [() => {}, { reset() {} }];
    `, loader: 'tsx' }));
  }}] });
const assets = Object.fromEntries(bundle.outputFiles.map(file => [file.path.endsWith('.css') ? '/preview.css' : '/preview.js', file.text]));
const index = await readFile(resolve(root, 'tests/ui/index.html'));
if (process.argv[2] === '--export') {
  const directory = process.argv[3];
  await mkdir(directory, { recursive: true });
  await writeFile(resolve(directory, 'index.html'), index.toString().replace("'/frame?'", "'frame.html?'"));
  await writeFile(resolve(directory, 'frame.html'), '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="preview.css"></head><body><div id="root"></div><script src="preview.js"></script></body></html>');
  await Promise.all(Object.entries(assets).map(([name, content]) => writeFile(resolve(directory, name.slice(1)), content)));
  console.log(directory);
  process.exit(0);
}
createServer((request, response) => {
  const path = new URL(request.url, 'http://localhost').pathname;
  if (assets[path]) { response.setHeader('Content-Type', path.endsWith('.css') ? 'text/css' : 'text/javascript'); response.end(assets[path]); }
  else if (path === '/frame') { response.setHeader('Content-Type', 'text/html'); response.end('<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/preview.css"></head><body><div id="root"></div><script type="module" src="/preview.js"></script></body></html>'); }
  else { response.setHeader('Content-Type', 'text/html'); response.end(index); }
}).listen(4173, '0.0.0.0', () => console.log('Workspace review: http://localhost:4173'));
