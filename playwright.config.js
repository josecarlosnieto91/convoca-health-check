// playwright.config.js — configuración del runner E2E
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './e2e',
  timeout: 30000,
  expect: { timeout: 10000 },
  retries: 1, // la demo puede tener latencia; un retry suave evita falsos negativos
  workers: 2,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: process.env.E2E_BASE || 'https://demo.getconvoca.app',
    viewport: { width: 1280, height: 900 },
    trace: 'retain-on-failure',
  },
});
