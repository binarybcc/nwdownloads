/**
 * Vitest Setup File
 *
 * Runs before all tests to set up the testing environment
 */

// Mock global objects that might be needed
global.Chart = class MockChart {
  constructor() {}
  destroy() {}
};

// Add any global test utilities here
