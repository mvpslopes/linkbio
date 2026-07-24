/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './index.html',
    './depoimentos/**/*.{php,html,js}',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        display: ['Sora', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
    },
  },
  plugins: [],
};
