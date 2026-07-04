import { defineConfig } from '@playwright/test';

const e2ePort = process.env.E2E_PORT ?? '18080';

export default defineConfig({
  testDir: './e2e/tests',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: process.env.CI ? 'github' : 'list',
  timeout: 30_000,

  use: {
    baseURL: `http://localhost:${e2ePort}`,
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    trace: 'retain-on-failure',
    // Pas de Traefik en E2E : accès direct via port
    ignoreHTTPSErrors: true,
  },

  projects: [
    {
      name: 'chromium',
      use: { browserName: 'chromium' },
    },
  ],

  globalSetup: './e2e/global-setup.ts',
  globalTeardown: './e2e/global-teardown.ts',

  // Pas de webServer : le Docker E2E est géré par global-setup
});
