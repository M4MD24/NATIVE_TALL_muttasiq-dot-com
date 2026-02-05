module.exports = {
    root: true,
    env: { browser: true, node: true, es2023: true },
    parserOptions: { ecmaVersion: 'latest', sourceType: 'module' },
    plugins: ['unused-imports'],
    extends: ['eslint:recommended'],
    rules: {
        'no-empty': ['error', { allowEmptyCatch: true }],

        // Let the plugin delete unused IMPORTS only
        'unused-imports/no-unused-imports': 'error',
        'unused-imports/no-unused-vars': 'off',

        // Use core rule for vars/params/catch; ignore anything starting with "_"
        'no-unused-vars': [
            'error',
            {
                vars: 'all',
                args: 'after-used',
                varsIgnorePattern: '^_',
                argsIgnorePattern: '^_',
                caughtErrors: 'all',
                caughtErrorsIgnorePattern: '^_',
                ignoreRestSiblings: true,
            },
        ],
    },
    ignorePatterns: ['node_modules/', 'vendor/', 'public/', 'storage/'],
};
