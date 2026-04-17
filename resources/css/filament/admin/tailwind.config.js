import preset from '../../../../vendor/filament/filament/tailwind.config.preset';

/** @type {import('tailwindcss').Config} */
export default {
    presets: [preset],
    content: [
        './app/Filament/**/*.php',
        './resources/views/filament/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
        './packages/mde/*/src/**/*.php',
        './packages/mde/*/resources/views/**/*.blade.php',
        './resources/css/filament/admin/**/*.css',
    ],
};
