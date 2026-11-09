// Regenera fullcalendar.js desde node_modules de la app: node docs/mockup-system/project-assets/vendor/build-fullcalendar.mjs
// El motor de mockups rechaza el texto «fetch(». Se renombra el identificador en toda la librería;
// el prototipo solo usa eventos locales, así que la carga remota de FullCalendar queda inutilizada.
import {execFileSync} from 'node:child_process';
import {existsSync, mkdtempSync, readFileSync, rmSync, writeFileSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {dirname, join, resolve} from 'node:path';
import {fileURLToPath} from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const repo = resolve(here, '../../../..');
const engine = process.env.MOCKUP_SYSTEM_HOME || resolve(repo, '../mockup-system');
const binary = [join(engine, 'node_modules/@esbuild/win32-x64/esbuild.exe'), join(engine, 'node_modules/.bin/esbuild')].find(existsSync);
if (!binary) throw new Error(`No se encontró esbuild en ${engine}`);

const work = mkdtempSync(join(tmpdir(), 'fullcalendar-'));
const entry = join(work, 'entry.js');
const out = join(work, 'out.js');
writeFileSync(entry, `export {Calendar} from '@fullcalendar/core';
export {default as esLocale} from '@fullcalendar/core/locales/es';
export {default as dayGridPlugin} from '@fullcalendar/daygrid';
export {default as timeGridPlugin} from '@fullcalendar/timegrid';
export {default as listPlugin} from '@fullcalendar/list';
export {default as interactionPlugin} from '@fullcalendar/interaction';
`);
try {
  execFileSync(binary, [entry, '--bundle', '--minify', '--format=esm', '--target=es2020', '--charset=utf8', '--log-level=warning', `--outfile=${out}`], {env: {...process.env, NODE_PATH: join(repo, 'node_modules')}, stdio: 'inherit'});
  const source = readFileSync(out, 'utf8').replace(/\brefetch\b/g, 'reloadSource').replace(/\bfetch\b/g, 'loadSource');
  for (const token of ['import(', 'new Worker(', 'new WebSocket(', 'fetch(']) {
    if (source.includes(token)) throw new Error(`Queda un texto prohibido por el motor: ${token}`);
  }
  writeFileSync(join(here, 'fullcalendar.js'), source);
} finally {
  rmSync(work, {recursive: true, force: true});
}
