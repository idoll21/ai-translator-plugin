import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';
import './index.css'; // این خط را اضافه کنید!
// اگر می‌خواهید یک فایل CSS اصلی داشته باشید (حتی برای Tailwind base styles)
// می‌توانید یک فایل './index.css' بسازید و آن را اینجا ایمپورت کنید.
// import './index.css'; 

const root = ReactDOM.createRoot(document.getElementById('ai-translator-root'));
root.render(
  <React.StrictMode>
    <App />
  </React.StrictMode>
);

// اگر می‌خواهید Tailwind CSS را به درستی در پروژه React خود (نه فقط inline)
// ادغام کنید تا فایل CSS نهایی تولید شود، باید یک فایل `index.css` (یا مشابه آن)
// در کنار `App.js` در پوشه `src` خود بسازید و محتوای زیر را به آن اضافه کنید:
// @tailwind base;
// @tailwind components;
// @tailwind utilities;
//
// سپس این فایل `index.css` را در این فایل (index.js) ایمپورت کنید:
// import './index.css';
//
// این کار باعث می‌شود Tailwind CSS به درستی در Build شما پردازش شود و
// یک فایل CSS نهایی در پوشه `build/static/css/` تولید شود.
