module.exports = {
  coverageReporters: [
    "lcov",
    "text"
  ],
  preset: '@vue/cli-plugin-unit-jest/presets/typescript-and-babel',
  verbose: true,
  moduleFileExtensions: ['js', 'ts', 'json', 'vue'], // Include .ts as a recognized extension
  testMatch: [
    '**/tests/unit/**/*.spec.[jt]s?(x)', // Adjust to find .ts and .tsx files
  ],
}