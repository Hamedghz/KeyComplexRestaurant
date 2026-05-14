# Key Complex Restaurant Landing Page

وب‌سایت معرفی مجموعه رستورانی **کِی** با طراحی موبایل‌محور، ساختار RTL، رابط کاربری مدرن و کدنویسی ماژولار با Vite.

## ویژگی‌ها

- هدر ثابت با دکمه تماس سریع.
- اسلایدر Hero با ناوبری دکمه‌ای، دات‌ها و پخش خودکار.
- بخش معرفی، شبکه‌های اجتماعی و KPIهای کلیدی.
- منوی تب‌بندی‌شده (صبحانه، غذای ایرانی، فست‌فود، کافه/دسر).
- کارت‌های منو با تصویر فول‌عرض و محتوای متنی زیر تصویر.
- فرم عضویت مشتریان با اعتبارسنجی سمت کلاینت.
- ناوبری پایین صفحه برای تجربه بهتر در موبایل.

## تکنولوژی‌ها

- **Vite** برای توسعه سریع و بیلد
- **Vanilla JavaScript (ES Modules)**
- **CSS ماژولار** (base / layout / components / pages)

## ساختار پروژه

```text
assets/
  css/
    base/
    components/
    layout/
    pages/
  images/
  js/
    core/
    modules/
    services/
    utils/
index.html
```

## شروع سریع

```bash
npm install
npm run dev
```

سپس آدرس خروجی Vite را باز کنید (معمولاً `http://localhost:5173`).

## اسکریپت‌ها

- `npm run dev` اجرای محیط توسعه
- `npm run build` ساخت نسخه production
- `npm run preview` نمایش نسخه build شده
- `npm run lint` اجرای ESLint
- `npm test` اجرای چک‌های CI (در حال حاضر lint)

## نکات توسعه

- تصاویر اسلایدر در `assets/images/slide1.svg` تا `slide3.svg` نگهداری می‌شوند.
- برای تغییر محتوا، `index.html` و فایل‌های CSS در `assets/css/pages/home.css` را ویرایش کنید.
- منطق اسلایدر در `assets/js/modules/carousel.js` قرار دارد.

## کیفیت و تست

پیشنهاد می‌شود قبل از هر commit، دستورات زیر اجرا شوند:

```bash
npm run lint
npm run build
```

## License

برای استفاده داخلی مجموعه کِی.
