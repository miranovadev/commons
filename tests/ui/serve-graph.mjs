// Synthetic local browser review; no API calls or production data.
import { build } from 'esbuild';
import { createServer } from 'node:http';
import { mkdir, writeFile } from 'node:fs/promises';
const result = await build({ entryPoints: ['tests/ui/graph-preview.tsx'], bundle: true, write: false,
  outdir: 'preview', jsx: 'automatic', define: { 'process.env.NODE_ENV': '"development"' } });
const assets = Object.fromEntries(result.outputFiles.map(file => [file.path.endsWith('.css') ? '/preview.css' : '/preview.js', file.text]));
const html = '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><title>Commons graph review</title><link rel="stylesheet" href="preview.css"></head><body><div id="root"></div><script src="preview.js"></script></body></html>';
if (process.argv[2] === '--export') {
  const directory = process.argv[3];
  await mkdir(directory, { recursive: true });
  await writeFile(`${directory}/index.html`, html);
  await Promise.all(Object.entries(assets).map(([name, content]) => writeFile(`${directory}${name}`, content)));
  process.exit(0);
}
createServer((req, res) => {
  const path = new URL(req.url, 'http://localhost').pathname;
  res.setHeader('Content-Type', path.endsWith('.css') ? 'text/css' : path.endsWith('.js') ? 'text/javascript' : 'text/html');
  res.end(assets[path] || html);
}).listen(4175, '0.0.0.0', () => console.log('Graph review: http://localhost:4175'));
