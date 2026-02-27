import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

const withOpacity = (cssVariable) => `rgb(var(${cssVariable}) / <alpha-value>)`;

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            colors: {
                background: withOpacity('--background'),
                foreground: withOpacity('--foreground'),
                card: {
                    DEFAULT: withOpacity('--card'),
                    foreground: withOpacity('--card-foreground'),
                },
                popover: {
                    DEFAULT: withOpacity('--popover'),
                    foreground: withOpacity('--popover-foreground'),
                },
                primary: {
                    DEFAULT: withOpacity('--primary'),
                    foreground: withOpacity('--primary-foreground'),
                },
                secondary: {
                    DEFAULT: withOpacity('--secondary'),
                    foreground: withOpacity('--secondary-foreground'),
                },
                muted: {
                    DEFAULT: withOpacity('--muted'),
                    foreground: withOpacity('--muted-foreground'),
                },
                accent: {
                    DEFAULT: withOpacity('--accent'),
                    foreground: withOpacity('--accent-foreground'),
                },
                destructive: {
                    DEFAULT: withOpacity('--destructive'),
                    foreground: withOpacity('--destructive-foreground'),
                },
                success: {
                    DEFAULT: withOpacity('--success'),
                    foreground: withOpacity('--success-foreground'),
                },
                warning: {
                    DEFAULT: withOpacity('--warning'),
                    foreground: withOpacity('--warning-foreground'),
                },
                border: withOpacity('--border'),
                input: {
                    DEFAULT: withOpacity('--input'),
                    background: withOpacity('--input-background'),
                },
                ring: withOpacity('--ring'),
            },
            fontFamily: {
                sans: ['var(--font-sans)'],
                mono: ['var(--font-mono)'],
            },
            borderRadius: {
                sm: 'calc(var(--radius) - 4px)',
                md: 'calc(var(--radius) - 2px)',
                lg: 'var(--radius)',
                xl: 'calc(var(--radius) + 4px)',
            },
        },
    },

    plugins: [forms, typography],
};
