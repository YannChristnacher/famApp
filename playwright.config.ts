import { defineConfig, devices } from '@playwright/test'

const port = Number(process.env.E2E_PORT ?? 8000)
const baseURL = `http://127.0.0.1:${port}`

/**
 * End-to-end tests of the key journeys. Run `scripts/prepare-e2e.sh` first
 * (SQLite database, seeded admin, built assets), then `npm run test:e2e`.
 */
export default defineConfig({
  testDir: 'tests/e2e',
  fullyParallel: false,
  workers: 1,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
  use: {
    baseURL,
    locale: 'fr-FR',
    trace: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        // Optional: an already installed browser when Playwright's own cannot be downloaded.
        launchOptions: process.env.PLAYWRIGHT_EXECUTABLE_PATH
          ? { executablePath: process.env.PLAYWRIGHT_EXECUTABLE_PATH }
          : {},
      },
    },
  ],
  webServer: {
    // APP_ENV=e2e makes Laravel read the .env.e2e written by scripts/prepare-e2e.sh.
    command: `php artisan serve --host=127.0.0.1 --port=${port}`,
    env: { APP_ENV: 'e2e' },
    url: `${baseURL}/up`,
    reuseExistingServer: !process.env.CI,
    timeout: 60_000,
  },
})
