import defaultTheme from 'tailwindcss/defaultTheme'

export default {
  content: [
    './resources/views/**/*.blade.php',
    './resources/js/**/*.js',
  ],
  safelist: ['bg-accent', 'text-accent'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', ...defaultTheme.fontFamily.sans],
        mono: ['"Space Mono"', ...defaultTheme.fontFamily.mono],
      },
      colors: {
        base: '#000',
        accent: '#133EB4',
        bg: '#fff',
      },
      fontSize: {
        'huge': ['40px', '40px'],
        'large': ['30px', '30px'],
        'small': ['14px', '14px'],
        'tiny': ['12px', '12px'],
      },
      utilities: {
        '.backface-hidden': {
          'backface-visibility': 'hidden',
        },
        '.rotate-y-180': {
          transform: 'rotateY(180deg)',
        }
      }
    },
  },
  plugins: [],
}
