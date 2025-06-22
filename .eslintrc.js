module.exports = {
  extends: ['eslint:recommended', 'wordpress'],
  env: {
    browser: true,
    es6: true,
  },
  rules: {
    // 必要に応じて上書き
    'no-console': 'off',
    'quotes': ['error', 'single'],
  },
};
