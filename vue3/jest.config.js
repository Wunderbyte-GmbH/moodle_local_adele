module.exports = {
  coverageReporters: [
    'lcov',
    'text',
  ],
  preset: '@vue/cli-plugin-unit-jest',
  verbose: true,
  moduleFileExtensions: ['js', 'ts', 'json', 'vue'],
  testMatch: [
    '**/tests/unit/**/*.spec.[jt]s?(x)',
  ],
  transform: {
    '^.+\\.vue$': '@vue/vue3-jest',  // Vue 3 component transformer
    '^.+\\.js$': 'babel-jest',        // JavaScript transformer
    '^.+\\.ts$': 'ts-jest',           // TypeScript transformer
  },
  watchPathIgnorePatterns: [
    '<rootDir>/node_modules/',
  ],
  reporters: [
    'default',
    ['jest-html-reporter', {
      pageTitle: 'Test Report',
      outputPath: 'test-report.html',
      includeFailureMsg: true,
      includeSuiteFailure: true,
    }],
  ],
  coverageProvider: "v8",
  testEnvironment: 'jsdom',           // Use jsdom for DOM simulation
  moduleNameMapper: {
    '^@vue/test-utils$': '<rootDir>/node_modules/@vue/test-utils',
  },
};
