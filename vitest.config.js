import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    globals: true,
    environment: 'jsdom',
    coverage: {
      provider: 'v8',
      reporter: ['text', 'html', 'lcov'],
      exclude: [
        'node_modules/',
        'tests/',
        'vendor/',
        'coverage/',
        '**/*.config.js'
      ]
    },
    include: ['tests/**/*.test.js'],
    setupFiles: ['tests/setup.js']
  }
});
