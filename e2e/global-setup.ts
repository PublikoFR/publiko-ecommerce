import { execSync, execFileSync } from 'child_process';
import { existsSync, readFileSync, writeFileSync, mkdirSync } from 'fs';
import * as path from 'path';
import * as http from 'http';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..');
const STATE_FILE = path.join(__dirname, '.e2e-state.json');
const COMPOSE_FILE = path.join(ROOT, 'docker-compose.e2e.yml');

function findMainRepo(): string {
  const output = execSync('git worktree list --porcelain', { cwd: ROOT, encoding: 'utf-8' });
  const firstWorktreeLine = output.split('\n').find(line => line.startsWith('worktree '));
  if (!firstWorktreeLine) throw new Error('Cannot determine main repo path from git worktrees');
  return firstWorktreeLine.replace('worktree ', '').trim();
}

function readAppKey(mainRepo: string): string {
  const envFile = path.join(mainRepo, '.env');
  if (!existsSync(envFile)) throw new Error(`.env not found in main repo: ${mainRepo}`);
  const content = readFileSync(envFile, 'utf-8');
  const match = content.match(/^APP_KEY=(.+)$/m);
  if (!match?.[1]) throw new Error('APP_KEY not found in main repo .env');
  return match[1].trim();
}

async function waitForApp(url: string, timeoutMs = 120_000): Promise<void> {
  const deadline = Date.now() + timeoutMs;
  process.stdout.write(`Waiting for ${url} `);
  while (Date.now() < deadline) {
    const ready = await new Promise<boolean>(resolve => {
      const req = http.get(url, res => {
        res.resume();
        // Any HTTP response means the server is up; 5xx before migrations is expected
        resolve(!!res.statusCode);
      });
      req.on('error', () => resolve(false));
      req.setTimeout(3000, () => { req.destroy(); resolve(false); });
    });
    if (ready) {
      process.stdout.write(' ready\n');
      return;
    }
    process.stdout.write('.');
    await new Promise(r => setTimeout(r, 2000));
  }
  throw new Error(`App not ready after ${timeoutMs}ms`);
}

async function waitForDb(projectName: string, env: Record<string, string>, timeoutMs = 60_000): Promise<void> {
  const deadline = Date.now() + timeoutMs;
  process.stdout.write('Waiting for MySQL ');
  while (Date.now() < deadline) {
    try {
      execFileSync('docker', [
        'compose', '-f', COMPOSE_FILE, '-p', projectName,
        'exec', '-T', 'app',
        'php', '-r', "new PDO('mysql:host=mysql;port=3306;dbname=pko_e2e', 'pko_e2e', 'pko_e2e_secret');",
      ], { stdio: 'pipe', env: { ...process.env, ...env }, cwd: ROOT });
      process.stdout.write(' ready\n');
      return;
    } catch {
      process.stdout.write('.');
      await new Promise(r => setTimeout(r, 2000));
    }
  }
  throw new Error(`MySQL not ready after ${timeoutMs}ms`);
}

function compose(projectName: string, env: Record<string, string>, ...args: string[]): void {
  execFileSync('docker', ['compose', '-f', COMPOSE_FILE, '-p', projectName, ...args], {
    stdio: 'inherit',
    env: { ...process.env, ...env },
    cwd: ROOT,
  });
}

function execInApp(projectName: string, env: Record<string, string>, ...command: string[]): void {
  execFileSync('docker', [
    'compose', '-f', COMPOSE_FILE, '-p', projectName,
    'exec', '-T', '-u', 'sail', 'app', ...command,
  ], {
    stdio: 'inherit',
    env: { ...process.env, ...env },
    cwd: ROOT,
  });
}

function execInAppRoot(projectName: string, env: Record<string, string>, ...command: string[]): void {
  execFileSync('docker', [
    'compose', '-f', COMPOSE_FILE, '-p', projectName,
    'exec', '-T', '-u', 'root', 'app', ...command,
  ], {
    stdio: 'inherit',
    env: { ...process.env, ...env },
    cwd: ROOT,
  });
}

export default async function globalSetup(): Promise<void> {
  // 1. Resolve config
  const e2ePort = process.env.E2E_PORT ?? '18080';
  const mainRepo = process.env.E2E_MAIN_REPO ?? findMainRepo();
  const appKey = process.env.E2E_APP_KEY ?? readAppKey(mainRepo);

  // COMPOSE_PROJECT_NAME: derived from the port for uniqueness
  const projectName = `pko-e2e-${e2ePort}`;

  console.log(`\n→ E2E setup [project=${projectName}, port=${e2ePort}]`);
  console.log(`  main repo : ${mainRepo}`);

  // Verify vendor and build are present in main repo
  if (!existsSync(path.join(mainRepo, 'vendor'))) {
    throw new Error(`vendor/ not found in main repo ${mainRepo}. Run "make composer CMD='install'" first.`);
  }
  if (!existsSync(path.join(mainRepo, 'public/build'))) {
    console.warn('⚠ public/build not found in main repo — assets may be missing. Run "npm run build" first.');
  }

  // Ensure the Docker image exists — build if necessary
  try {
    execSync('docker image inspect ecom-laravel-app', { stdio: 'pipe' });
  } catch {
    console.log('  Image ecom-laravel-app not found — building...');
    execSync('docker compose build app', { stdio: 'inherit', cwd: ROOT });
  }

  const composeEnv = {
    E2E_PORT: e2ePort,
    E2E_MAIN_REPO: mainRepo,
    E2E_APP_KEY: appKey,
  };

  // 2. Start stack (idempotent: down first to clean any stale state)
  console.log('  Starting E2E stack...');
  try {
    compose(projectName, composeEnv, 'down', '--volumes', '--remove-orphans');
  } catch { /* ignore: might not exist yet */ }

  compose(projectName, composeEnv, 'up', '-d', '--wait');

  // Persist state early so global-teardown can clean up even if setup fails later
  mkdirSync(__dirname, { recursive: true });
  writeFileSync(STATE_FILE, JSON.stringify({ projectName, port: e2ePort, composeEnv }, null, 2));

  // 3. Init storage + bootstrap/cache BEFORE first HTTP hit (named volumes start empty, owned by root)
  console.log('  Initializing storage directories...');
  execInAppRoot(projectName, composeEnv,
    'bash', '-c',
    'mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache/data storage/logs storage/app/public' +
    ' && chown -R sail:sail storage bootstrap/cache' +
    ' && chmod -R ug+rwX storage bootstrap/cache',
  );

  // 4. Wait for app HTTP + MySQL user ready (storage must exist first to avoid boot 500)
  await waitForApp(`http://localhost:${e2ePort}`);
  await waitForDb(projectName, composeEnv);

  // 5. Migrate + seed (idempotent: fresh wipe each run)
  console.log('  Running migrate:fresh --seed...');
  execInApp(projectName, composeEnv, 'php', 'artisan', 'migrate:fresh', '--seed', '--force', '--no-interaction');

  // 6. Shield — run as root so it can write app/Policies/ (worktree mount), then chown back to sail
  console.log('  Generating Shield policies...');
  execInAppRoot(projectName, composeEnv,
    'bash', '-c',
    'php artisan shield:generate --all --panel=admin --no-interaction',
  );
  execInApp(projectName, composeEnv, 'php', 'artisan', 'shield:super-admin', '--user=1', '--panel=admin');
  execInApp(projectName, composeEnv, 'php', 'artisan', 'optimize:clear');

  console.log(`  E2E stack ready → http://localhost:${e2ePort}\n`);
}
