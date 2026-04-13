/**
 * Relance ship.mjs après un délai quand des fichiers sources changent.
 */
import chokidar from 'chokidar';
import { spawn } from 'child_process';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
let timer;
let running = false;

function ship() {
  if (running) return;
  running = true;
  const child = spawn(process.execPath, ['scripts/ship.mjs'], {
    cwd: root,
    stdio: 'inherit',
  });
  child.on('close', () => {
    running = false;
  });
}

function schedule() {
  clearTimeout(timer);
  timer = setTimeout(ship, 5000);
}

chokidar
  .watch('.', {
    cwd: root,
    ignoreInitial: true,
    ignored: [
      /(^|[\\/])node_modules([\\/]|$)/,
      /(^|[\\/])vendor([\\/]|$)/,
      /(^|[\\/])var([\\/]|$)/,
      /(^|[\\/])public[\\/]build([\\/]|$)/,
      /(^|[\\/])\.git([\\/]|$)/,
    ],
  })
  .on('all', schedule);

console.log('[ship:watch] Surveillance — Ctrl+C pour arrêter.');
