/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./src/**/*.{html,ts}",
  ],
  theme: {
    extend: {
      colors: {
        pbs: {
          primary: '#1e3a5f',
          secondary: '#3b82f6',
          accent: '#f59e0b',
          danger: '#ef4444',
          success: '#22c55e',
          warning: '#f59e0b',
          info: '#3b82f6',
        },
      },
    },
  },
  plugins: [],
}