import defaultTheme from 'tailwindcss/defaultTheme';

export default {
  darkMode: 'class',
  content: ['./resources/**/*.{js,jsx,ts,tsx,blade.php}'],
  theme: {
    extend: {
      colors: {
        // MSC Brand Colors - Using consistent values
        'msc-blue': {
          DEFAULT: '#1925c3',
          500: '#1925c3',
          600: '#141c9a',
          700: '#0f1470',
        },
        'msc-red': {
          DEFAULT: '#c71719',
          500: '#c71719',
          600: '#9e1214',
          700: '#750d0f',
        },
        // Glass theme specific colors
        glass: {
          'light': 'rgba(255, 255, 255, 0.07)',
          'medium': 'rgba(255, 255, 255, 0.11)',
          'heavy': 'rgba(255, 255, 255, 0.15)',
          'frost': 'rgba(255, 255, 255, 0.03)',
        },
        // Dark gradient colors
        dark: {
          'start': '#0a0f1c',
          'middle': '#121829',
          'end': '#1a1f2e',
        }
      },
      backgroundImage: {
        'msc-gradient': 'linear-gradient(135deg, #1925c3 0%, #c71719 100%)',
        'dark-gradient': 'linear-gradient(to bottom right, #0a0f1c, #121829, #1a1f2e)',
        'glass-gradient': 'linear-gradient(to bottom, rgba(255, 255, 255, 0.06), transparent)',
      },
      fontFamily: {
        sans: ['"Cerebri Sans"', 'Inter', ...defaultTheme.fontFamily.sans]
      },
      backdropBlur: {
        '3xl': '64px',
        '4xl': '96px',
      },
      backdropSaturate: {
        '200': '2',
      },
      boxShadow: {
        'glass': '0 25px 50px -12px rgba(0, 0, 0, 0.30)',
        'glass-glow': '0 0 50px rgba(25, 37, 195, 0.3)',
        'glass-danger': '0 0 30px rgba(199, 23, 25, 0.4)',
        'glass-soft': '0 10px 15px -3px rgba(0, 0, 0, 0.20)',
      },
      keyframes: {
        "fade-in": {
          "0%": { opacity: "0" },
          "100%": { opacity: "1" },
        },
        "scale-in": {
          "0%": { transform: "scale(0.95)", opacity: "0" },
          "100%": { transform: "scale(1)", opacity: "1" },
        },
        "accordion-down": {
          from: { height: "0" },
          to: { height: "var(--radix-accordion-content-height)" },
        },
        "accordion-up": {
          from: { height: "var(--radix-accordion-content-height)" },
          to: { height: "0" },
        },
        "slide-in-left": {
          from: { opacity: "0", transform: "translateX(-10px)" },
          to: { opacity: "1", transform: "translateX(0)" },
        },
        "float": {
          "0%, 100%": { transform: "translateY(0px)" },
          "50%": { transform: "translateY(-10px)" },
        },
        "pulse-glow": {
          "0%, 100%": { 
            boxShadow: "0 0 20px rgba(25, 37, 195, 0.3)",
          },
          "50%": { 
            boxShadow: "0 0 40px rgba(25, 37, 195, 0.5)",
          },
        },
        "blob": {
          "0%": {
            transform: "translate(0px, 0px) scale(1)",
          },
          "33%": {
            transform: "translate(30px, -50px) scale(1.1)",
          },
          "66%": {
            transform: "translate(-20px, 20px) scale(0.9)",
          },
          "100%": {
            transform: "translate(0px, 0px) scale(1)",
          },
        },
        "shimmer": {
          "0%": {
            transform: "translateX(-100%)",
          },
          "100%": {
            transform: "translateX(100%)",
          },
        },
      },
      animation: {
        "fade-in": "fade-in 0.3s ease-out",
        "scale-in": "scale-in 0.3s ease-out",
        "accordion-down": "accordion-down 0.2s ease-out",
        "accordion-up": "accordion-up 0.2s ease-out",
        "slide-in-left": "slide-in-left 0.3s ease-out",
        "float": "float 3s ease-in-out infinite",
        "pulse-glow": "pulse-glow 2s ease-in-out infinite",
        "blob": "blob 7s infinite",
        "shimmer": "shimmer 2s linear infinite",
      },
      opacity: {
        '3': '0.03',
        '7': '0.07',
        '11': '0.11',
        '15': '0.15',
        '18': '0.18',
        '22': '0.22',
        '55': '0.55',
        '85': '0.85',
      },
      borderRadius: {
        'xl': '1rem',
        '2xl': '1.5rem',
        '3xl': '2rem',
      }
    }
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/typography'),
    require('tailwindcss-animate')
  ]
};