import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            // The widget's neutral palette, expressed as CSS custom
            // properties so a host app can retheme it (and flip it dark)
            // without rebuilding. Defaults live in resources/css/common.css;
            // hosts override per theme via the `palette` config key.
            colors: {
                'sbm-page': 'var(--sbm-page)',
                'sbm-surface': 'var(--sbm-surface)',
                'sbm-surface-soft': 'var(--sbm-surface-soft)',
                'sbm-surface-strong': 'var(--sbm-surface-strong)',
                'sbm-ink': 'var(--sbm-ink)',
                'sbm-ink-soft': 'var(--sbm-ink-soft)',
                'sbm-ink-muted': 'var(--sbm-ink-muted)',
                'sbm-edge': 'var(--sbm-edge)',
                'sbm-main': 'var(--sbm-main)',
                'sbm-on-main': 'var(--sbm-on-main)',
            },
        },
    },

    plugins: [forms, typography],
};
