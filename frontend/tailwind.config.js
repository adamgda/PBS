/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./src/**/*.{html,ts}",
  ],
  theme: {
    extend: {
      colors: {
        pbs: {
          // Głęboki granat — główny kolor marki (tła, sidebar, przyciski primary)
          primary: '#1e3a5f',
          // Rozszerzona paleta granatu (shades) do gradientów i akcentów
          navy: {
            50: '#f0f5fb',
            100: '#dbe6f2',
            200: '#b7cde4',
            300: '#8baecf',
            400: '#5f8bb5',
            500: '#3b6a96',
            600: '#2c5077',
            700: '#1e3a5f',
            800: '#172d49',
            900: '#102238',
            950: '#0a1626',
          },
          secondary: '#3b82f6',
          accent: '#f59e0b',
          danger: '#ef4444',
          success: '#22c55e',
          warning: '#f59e0b',
          info: '#3b82f6',
          // Tło "surface" aplikacji (subtelny, ciepły szary)
          surface: '#f6f8fb',
        },
      },
      fontFamily: {
        sans: [
          'Inter',
          '-apple-system',
          'BlinkMacSystemFont',
          'Segoe UI',
          'Roboto',
          'sans-serif',
        ],
        display: [
          'Plus Jakarta Sans',
          'Inter',
          '-apple-system',
          'BlinkMacSystemFont',
          'Segoe UI',
          'Roboto',
          'sans-serif',
        ],
      },
      boxShadow: {
        card: '0 1px 2px 0 rgb(16 34 56 / 0.04), 0 1px 3px 0 rgb(16 34 56 / 0.06)',
        'card-hover': '0 4px 6px -1px rgb(16 34 56 / 0.07), 0 2px 4px -1px rgb(16 34 56 / 0.05)',
        elevated: '0 10px 15px -3px rgb(16 34 56 / 0.08), 0 4px 6px -2px rgb(16 34 56 / 0.05)',
        'glow-primary': '0 0 0 3px rgb(59 130 246 / 0.18)',
      },
      borderRadius: {
        xl2: '1.25rem',
      },
      keyframes: {
        'fade-in': {
          '0%': { opacity: '0', transform: 'translateY(4px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        'scale-in': {
          '0%': { opacity: '0', transform: 'scale(0.96)' },
          '100%': { opacity: '1', transform: 'scale(1)' },
        },
        'slide-in-right': {
          '0%': { opacity: '0', transform: 'translateX(16px)' },
          '100%': { opacity: '1', transform: 'translateX(0)' },
        },
      },
      animation: {
        'fade-in': 'fade-in 0.3s ease-out both',
        'scale-in': 'scale-in 0.18s ease-out both',
        'slide-in-right': 'slide-in-right 0.25s ease-out both',
      },
    },
  },
  plugins: [],
}