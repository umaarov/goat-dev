module.exports = {
    root: true,
    env: {
        browser: true,
        es2021: true,
        node: true,
    },
    parserOptions: {
        ecmaVersion: 2021,
        sourceType: 'module',
    },
    extends: [
        'eslint:recommended',
        'plugin:import/errors',
        'plugin:import/warnings',
        'plugin:import/typescript',
    ],
    plugins: [
        'import',
    ],
    rules: {
        'no-unused-vars': ['warn', {argsIgnorePattern: '^_'}],
        'no-eval': 'error',
        'no-implied-eval': 'error',
        'no-undef': 'error',
        'no-console': ['warn', {allow: ['warn', 'error']}],
        'no-debugger': 'warn',
        'consistent-return': 'error',
        'eqeqeq': ['error', 'always'],
        'curly': 'error',

        'no-new': 'warn',
        'no-loop-func': 'error',
        'prefer-const': 'warn',
        'no-var': 'error',

        'import/no-unresolved': 'error',
        'import/named': 'error',
        'import/namespace': 'error',
        'import/default': 'error',
        'import/export': 'error',
        'semi': ['error', 'always'],
        'quotes': ['error', 'single', {avoidEscape: true}],
        'indent': ['error', 2],
        'brace-style': ['error', '1tbs'],
    },
    overrides: [
        {
            files: ['*.ts', '*.tsx'],
            parser: '@typescript-eslint/parser',
            plugins: ['@typescript-eslint'],
            extends: ['plugin:@typescript-eslint/recommended'],
            rules: {
                '@typescript-eslint/no-unused-vars': ['warn', {argsIgnorePattern: '^_'}],
            },
        },
    ],
};
