<!DOCTYPE html>
<html lang="fa">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>منوی دیجیتال رستوران</title>
  <style>
    body {
      margin: 0;
      font-family: "Vazirmatn", sans-serif;
      direction: rtl;
      background: #f9f9f9;
      color: #333;
    }
    header {
      background: #2b2b2b;
      color: white;
      padding: 1rem 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    header nav a {
      color: white;
      margin: 0 1rem;
      text-decoration: none;
    }
    .hero {
      background: url('https://source.unsplash.com/1600x600/?restaurant,menu') center/cover;
      height: 400px;
      display: flex;
      justify-content: center;
      align-items: center;
      text-align: center;
      color: white;
      flex-direction: column;
    }
    .hero h1 {
      font-size: 2.5rem;
      margin: 0;
    }
    .hero p {
      font-size: 1.2rem;
    }
    .cta-btn {
      background: #e63946;
      color: white;
      border: none;
      padding: 0.8rem 2rem;
      font-size: 1rem;
      cursor: pointer;
      margin-top: 1rem;
      border-radius: 5px;
    }
    .section {
      padding: 2rem;
      max-width: 1100px;
      margin: auto;
    }
    .features {
      display: flex;
      flex-wrap: wrap;
      gap: 2rem;
      justify-content: space-between;
    }
    .feature {
      background: white;
      padding: 1.5rem;
      border-radius: 8px;
      flex: 1 1 30%;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    footer {
      background: #222;
      color: white;
      text-align: center;
      padding: 1rem;
    }

    @media (max-width: 768px) {
      .features {
        flex-direction: column;
      }
    }
  </style>
</head>
<body>

<header>
  <h2>منوی دیجیتال</h2>
  <nav>
    <a href="#features">ویژگی‌ها</a>
    <a href="#pricing">قیمت‌گذاری</a>
    <a href="#contact">تماس با ما</a>
  </nav>
</header>

<div class="hero">
  <h1>منوی دیجیتال رستوران</h1>
  <p>منوی آنلاین، بدون تماس، حرفه‌ای و زیبا برای رستوران شما</p>
  <button class="cta-btn">مشاهده دمو</button>
</div>

<section id="features" class="section">
  <h2>ویژگی‌ها</h2>
  <div class="features">
    <div class="feature">
      <h3>نمایش سریع و زیبا</h3>
      <p>طراحی کاملاً واکنش‌گرا و سریع روی موبایل و تبلت</p>
    </div>
    <div class="feature">
      <h3>ویرایش ساده منو</h3>
      <p>بدون نیاز به دانش فنی، فقط با چند کلیک منو را تغییر دهید</p>
    </div>
    <div class="feature">
      <h3>بدون نصب اپ</h3>
      <p>مشتری فقط با اسکن QR به منو دسترسی خواهد داشت</p>
    </div>
  </div>
</section>

<section id="pricing" class="section">
  <h2>پلن‌های قیمت‌گذاری</h2>
  <p>پلن‌های ماهیانه مناسب برای همه کسب‌وکارها</p>
  <!-- این بخش رو می‌تونیم به صورت کارت‌های قیمت‌گذاری اضافه کنیم -->
</section>

<section id="contact" class="section">
  <h2>تماس با ما</h2>
  <p>برای ثبت سفارش یا دریافت مشاوره رایگان با ما در تماس باشید.</p>
  <!-- فرم تماس در آینده اضافه می‌شه -->
</section>

<footer>
  © 2025 منوی دیجیتال. همه حقوق محفوظ است.
</footer>

</body>
</html>
