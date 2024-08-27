module.exports = {
  transform: {
    '^.+\\.js$': 'babel-jest', // Transform JavaScript files using Babel
    '^.+\\.vue$': 'vue-jest',  // Transform Vue files using vue-jest
  },
  testEnvironment: 'jsdom', // Set the environment if you're testing browser-like features
  moduleFileExtensions: ['js', 'json', 'vue'], // Extensions Jest will look for
};