import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/*
 * Weklo Design System — Tailwind theme.
 * Palette / type / radius / shadow tokens are mirrored from the imported
 * design system (see /design-system/tokens/*.css — source of truth).
 * All storefront (front-office) UI MUST consume these tokens via Tailwind
 * classes (primary = forest, accent = lime) or the CSS custom properties in
 * resources/css/app.css. Do not hardcode brand colors.
 */

/* Forest (brand primary) */
const forest = {
    50: '#eef5f3',
    100: '#d6e7e3',
    200: '#abccc5',
    300: '#79aaa1',
    400: '#43847a',
    500: '#136356',
    600: '#00453e', // BRAND — Vert foncé
    700: '#003a34',
    800: '#002e29',
    900: '#00211e',
    950: '#001512',
};

/* Lime (brand accent) */
const lime = {
    50: '#f6faea',
    100: '#ecf4cf',
    200: '#dbe9a3',
    300: '#c8dd72',
    400: '#b8d24c',
    500: '#aac932', // BRAND — Vert clair
    600: '#8aa922',
    700: '#6a841d',
    800: '#50641b',
    900: '#3c4b19',
};

/* Green-tinted neutrals */
const neutral = {
    0: '#ffffff',
    50: '#f6f8f7',
    100: '#eef1f0',
    200: '#e0e4e2',
    300: '#c5ccc9',
    400: '#9aa3a0',
    500: '#76817d',
    600: '#586460',
    700: '#3f4a46',
    800: '#283330',
    900: '#16201d',
    950: '#0c110f',
};

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/lunarphp/stripe-payments/resources/views/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
        './app/Livewire/**/*.php',
        './app/View/Components/**/*.php',
        './packages/pko/*/src/**/*.php',
        './packages/pko/*/resources/views/**/*.blade.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                // Body / UI — Hanken Grotesk. Display / headings — Forno Waffle.
                sans: ['Hanken Grotesk', 'Inter', ...defaultTheme.fontFamily.sans],
                display: ['Forno Waffle', 'Hanken Grotesk', ...defaultTheme.fontFamily.sans],
                mono: ['IBM Plex Mono', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                primary: forest,
                forest,
                accent: lime,
                lime,
                neutral,
                success: {
                    50: '#e9f7ee',
                    100: '#d9f0e0',
                    500: '#2f9e57',
                    600: '#27894a',
                    700: '#1f6e3c',
                },
                warning: {
                    50: '#fdf6e6',
                    100: '#fbeccb',
                    500: '#e8a317',
                    600: '#c98a10',
                    700: '#a36f08',
                },
                danger: {
                    50: '#fdeaea',
                    100: '#f9dcdc',
                    500: '#d64545',
                    600: '#c23a3a',
                    700: '#9c2a2a',
                },
                info: {
                    50: '#eaf4fa',
                    100: '#d6e9f3',
                    500: '#2f80b8',
                    600: '#276b9c',
                    700: '#1d5781',
                },
            },
            borderRadius: {
                md: '10px',
                lg: '14px',
                xl: '20px',
                '2xl': '28px',
                '3xl': '40px',
            },
            boxShadow: {
                xs: '0 1px 2px rgba(0, 33, 30, 0.06)',
                sm: '0 1px 3px rgba(0, 33, 30, 0.08), 0 1px 2px rgba(0, 33, 30, 0.04)',
                DEFAULT: '0 4px 12px rgba(0, 33, 30, 0.08), 0 2px 4px rgba(0, 33, 30, 0.04)',
                md: '0 4px 12px rgba(0, 33, 30, 0.08), 0 2px 4px rgba(0, 33, 30, 0.04)',
                lg: '0 12px 28px rgba(0, 33, 30, 0.10), 0 4px 8px rgba(0, 33, 30, 0.05)',
                xl: '0 24px 48px rgba(0, 33, 30, 0.14), 0 8px 16px rgba(0, 33, 30, 0.06)',
                accent: '0 8px 22px rgba(170, 201, 50, 0.34)',
                brand: '0 8px 22px rgba(0, 69, 62, 0.22)',
            },
            maxWidth: {
                'screen-2xl': '1440px',
            },
        },
    },
    plugins: [forms],
};
