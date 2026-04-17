import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import colors from 'tailwindcss/colors';

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
        './packages/mde/*/src/**/*.php',
        './packages/mde/*/resources/views/**/*.blade.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: colors.blue,
                neutral: colors.slate,
                success: colors.emerald,
                warning: colors.amber,
                danger: colors.rose,
            },
            maxWidth: {
                'screen-2xl': '1440px',
            },
        },
    },
    plugins: [forms],
};
