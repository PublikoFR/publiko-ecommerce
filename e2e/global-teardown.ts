import { execFileSync } from 'child_process';
import { existsSync, readFileSync, rmSync } from 'fs';
import * as path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..');
const STATE_FILE = path.join(__dirname, '.e2e-state.json');
const COMPOSE_FILE = path.join(ROOT, 'docker-compose.e2e.yml');

export default async function globalTeardown(): Promise<void> {
  if (!existsSync(STATE_FILE)) {
    console.log('→ E2E teardown: no state file found, nothing to do.');
    return;
  }

  const { projectName, composeEnv } = JSON.parse(readFileSync(STATE_FILE, 'utf-8')) as {
    projectName: string;
    port: string;
    composeEnv: Record<string, string>;
  };

  console.log(`\n→ E2E teardown [project=${projectName}]`);

  try {
    execFileSync('docker', ['compose', '-f', COMPOSE_FILE, '-p', projectName, 'down', '--volumes', '--remove-orphans'], {
      stdio: 'inherit',
      env: { ...process.env, ...composeEnv },
      cwd: ROOT,
    });
    console.log('  Stack destroyed (containers + volumes).\n');
  } catch (err) {
    console.error('  Warning: teardown failed:', err);
  }

  rmSync(STATE_FILE, { force: true });
}
