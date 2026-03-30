import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            colors: {
                dark: '#000000',
                light: '#ffffff',
                accent: '#133EB4',
            },
            fontFamily: {
                sans: [
                    'Inter',
                    ...defaultTheme.fontFamily.sans
                ],
                mono: [
                    'Space Mono',
                    ...defaultTheme.fontFamily.mono
                ],
            },
        },
    },

    plugins: [forms],
};
