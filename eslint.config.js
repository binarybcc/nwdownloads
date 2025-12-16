import js from '@eslint/js';

export default [
  js.configs.recommended,
  {
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: 'script',
      globals: {
        // Browser globals
        window: 'readonly',
        document: 'readonly',
        console: 'readonly',
        alert: 'readonly',

        // Browser APIs - Timing
        setTimeout: 'readonly',
        setInterval: 'readonly',
        clearTimeout: 'readonly',
        clearInterval: 'readonly',

        // Browser APIs - Networking & Data
        fetch: 'readonly',
        Promise: 'readonly',
        URLSearchParams: 'readonly',
        FormData: 'readonly',

        // Browser APIs - Storage
        localStorage: 'readonly',
        sessionStorage: 'readonly',

        // Browser APIs - DOM
        Element: 'readonly',
        HTMLElement: 'readonly',
        Event: 'readonly',
        CustomEvent: 'readonly',
        MutationObserver: 'readonly',
        requestAnimationFrame: 'readonly',
        event: 'readonly',

        // Browser APIs - Files & URLs
        Blob: 'readonly',
        URL: 'readonly',

        // External Libraries - Charts & Visualization
        Chart: 'readonly',

        // External Libraries - Data Processing
        XLSX: 'readonly',

        // External Libraries - UI Components
        flatpickr: 'readonly',
        html2canvas: 'readonly',

        // Node.js / Module System
        module: 'writable',

        // Custom Application Globals (defined in one file, used in others)
        CircDashboard: 'writable',
        SubscriberTablePanel: 'writable',
        getStateIconPath: 'readonly',
        getStateAbbr: 'readonly',
        getStateIconImg: 'readonly',
        RevenueOpportunityTable: 'writable',
        exportSubscriberList: 'readonly',
        dashboardData: 'writable',
        currentBusinessUnit: 'writable',
        currentSnapshotDate: 'writable',
        createChartContextMenu: 'readonly',
        initializeChartContextMenus: 'readonly',
        cleanupChartContextMenus: 'readonly',
        displayLongestVacationsOverall: 'readonly',
        displayLongestVacationsForUnit: 'readonly',
      },
    },
    rules: {
      'no-var': 'warn',
      'prefer-const': 'warn',
      'no-unused-vars': 'warn',
      'no-console': 'off',
      'no-undef': 'error',
      'no-redeclare': ['error', { builtinGlobals: false }],
    },
  },
  {
    ignores: [
      'node_modules/**',
      'vendor/**',
      'backup-*/**',
      'archive/**',
      'web/assets/output.css',
    ],
  },
];
