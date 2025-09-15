export default [
    {
        files: [
            "resources/js/**/*.js"
        ],
        languageOptions: {
            ecmaVersion: 2021,
            sourceType: "module",
        },
        rules: {
            "no-unused-vars": ["warn", { argsIgnorePattern: "^_" }],
            "no-debugger": "warn",
            "semi": ["error", "always"],
            "quotes": ["error", "single"],
            "eqeqeq": ["error", "always"],
            "curly": ["error", "all"],
            "no-eval": "error",
            "prefer-const": "error",
            "no-var": "error",
            "block-scoped-var": "error",
            "no-console": ["warn", { allow: ["warn", "error"] }]
        },
    },
];
