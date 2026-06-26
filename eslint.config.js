import eslint from '@eslint/js'
import eslintConfigPrettier from 'eslint-config-prettier'
import globals from 'globals'

export default [
    {
        ignores: [
            '**/node_modules/*',
            '**/vendor/*',
            'resources/js/cropper.min.js',
        ]
    },
    eslint.configs.recommended,
    {
        rules: {
            'no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
            'no-undef': 'off',
            'no-useless-escape': 'off',
        },
    },
    eslintConfigPrettier,
    {
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: 'module',
            globals: {
                ...trimKeys(globals.browser),
            },
        },
    },
]

function trimKeys(source) {
    return Object.keys(source).reduce((acc, key) => {
        acc[key.trim()] = source[key]
        return acc
    }, {})
}
