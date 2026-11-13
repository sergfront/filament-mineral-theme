import { readFile, readdir, mkdir, writeFile } from 'node:fs/promises';
import { transform } from 'esbuild';
const files = (await readdir('resources/css/palettes')).filter(f => f.endsWith('.css')).sort();
let css = (await Promise.all(files.map(f => readFile(`resources/css/palettes/${f}`, 'utf8')))).join('\n');
css += await readFile('resources/css/theme.css', 'utf8');
try { css += await readFile('resources/css/pro.css', 'utf8'); } catch (e) { if (e.code !== 'ENOENT') throw e; }
await mkdir('resources/dist', { recursive: true });
await writeFile('resources/dist/mineral.css', (await transform(css, { loader: 'css', minify: true, target: ['safari16.4', 'chrome111', 'firefox121'] })).code);
console.log(`Built ${files.length} palettes.`);
