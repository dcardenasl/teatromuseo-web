const js = require('@eslint/js');

module.exports = [
    js.configs.recommended,
    {
        files: ['src/js/**/*.js'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: {
                Date: 'readonly',
                DOMParser: 'readonly',
                FormData: 'readonly',
                IntersectionObserver: 'readonly',
                JSON: 'readonly',
                Math: 'readonly',
                Number: 'readonly',
                URLSearchParams: 'readonly',
                clearInterval: 'readonly',
                clearTimeout: 'readonly',
                console: 'readonly',
                document: 'readonly',
                fetch: 'readonly',
                history: 'readonly',
                navigator: 'readonly',
                parseInt: 'readonly',
                setInterval: 'readonly',
                setTimeout: 'readonly',
                window: 'readonly',
            },
        },
        rules: {
            'no-shadow': 'error',
        },
    },
    {
        // Ignore generated output — linting the source is sufficient
        ignores: ['public/assets/js/site.js'],
    },
];
