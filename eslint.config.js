import js from '@eslint/js';
import reactHooks from 'eslint-plugin-react-hooks';
import reactRefresh from 'eslint-plugin-react-refresh';
import globals from 'globals';
import tseslint from 'typescript-eslint';

export default tseslint.config(
    {
        ignores: [
            'node_modules/**',
            'vendor/**',
            'public/**',
            'bootstrap/**',
            'storage/**',
            'coverage/**',
            'dist/**',
            'build/**',
            '**/*.min.js',
            // Componentes generados por shadcn/ui: se actualizan con su CLI.
            'resources/js/components/ui/**',
        ],
    },
    js.configs.recommended,
    ...tseslint.configs.recommended,
    {
        files: ['resources/js/**/*.{ts,tsx}'],
        languageOptions: {
            ecmaVersion: 2022,
            globals: globals.browser,
        },
        plugins: {
            'react-hooks': reactHooks,
            'react-refresh': reactRefresh,
        },
        rules: {
            ...reactHooks.configs.recommended.rules,
            // Las páginas de Inertia exportan también `layout`.
            'react-refresh/only-export-components': ['warn', { allowConstantExport: true, allowExportNames: ['layout'] }],
            '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_', varsIgnorePattern: '^_' }],
            '@typescript-eslint/consistent-type-imports': 'error',
        },
    },
    {
        files: ['*.config.{js,ts}'],
        languageOptions: {
            globals: globals.node,
        },
    },
);
