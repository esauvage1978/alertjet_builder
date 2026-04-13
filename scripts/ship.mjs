/**
 * Build (Doctrine + Encore) puis commit + push si des changements existent.
 */
import { execSync } from 'child_process';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');

function run(cmd) {
  execSync(cmd, { stdio: 'inherit', cwd: root, shell: true });
}

function runCapture(cmd) {
  return execSync(cmd, { encoding: 'utf8', cwd: root, shell: true });
}

run('php bin/console doctrine:schema:update --force');
run('npm run build');

const status = runCapture('git status --porcelain');
if (!status.trim()) {
  console.log('[ship] Rien à committer.');
  process.exit(0);
}

run('git add -A');
for (const f of ['.env', '.env.local', '.env.production.local']) {
  try {
    execSync(`git reset HEAD -- ${f}`, { cwd: root, stdio: 'ignore' });
  } catch {
    /* ignore */
  }
}

const statusAfter = runCapture('git status --porcelain');
if (!statusAfter.trim()) {
  console.log('[ship] Rien à committer (hors fichiers sensibles exclus).');
  process.exit(0);
}

const msg = `chore: auto ${new Date().toISOString()}`;
run(`git commit -m ${JSON.stringify(msg)}`);
run('git push origin HEAD');
