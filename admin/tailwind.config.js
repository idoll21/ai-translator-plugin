 // admin/tailwind.config.js
/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./src/**/*.{js,jsx,ts,tsx}", // این مسیرها را بررسی کنید تا مطمئن شوید Tailwind فایل‌های React شما را اسکن می‌کند.
    "./public/index.html",
  ],
  theme: {
    extend: {
        fontFamily: {
            inter: ['Inter', 'sans-serif'], // اضافه کردن فونت اینتر
        },
    },
  },
  plugins: [],
};
