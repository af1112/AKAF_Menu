<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نرم‌افزار مدیریت خانه صفا</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/vazirmatn@33.0.0/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: #f3f4f6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            max-width: 600px;
            width: 100%;
            padding: 1.5rem;
        }
        .card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
        }
        .btn {
            transition: all 0.3s ease;
            width: 100%;
            margin-bottom: 1rem;
            padding: 0.75rem 1rem;
            font-size: 1.125rem;
            border-radius: 0.375rem;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        h1 {
            font-size: 1.875rem;
            line-height: 2.25rem;
        }
        @media (max-width: 640px) {
            .container {
                padding: 1rem;
            }
            h1 {
                font-size: 1.5rem;
                line-height: 2rem;
            }
            .btn {
                font-size: 1rem;
                padding: 0.5rem 0.75rem;
            }
            .card {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card text-center">
            <h1 class="font-bold text-gray-800 mb-6">نرم‌افزار مدیریت خانه صفا</h1>
            <div class="flex flex-col space-y-4">
                <button onclick="location.href='expenses.php'" class="btn bg-blue-600 text-white hover:bg-blue-700">مدیریت هزینه‌ها</button>
                <button onclick="location.href='furniture_expenses.php'" class="btn bg-purple-600 text-white hover:bg-purple-700">مدیریت هزینه‌های اثاثیه</button>
                <button onclick="location.href='payments.php'" class="btn bg-gray-600 text-white hover:bg-gray-700">مدیریت پرداخت‌ها</button>
                <button onclick="location.href='set_fixed_shares.php'" class="btn bg-orange-600 text-white hover:bg-orange-700">تنظیم هزینه‌های ماهانه خانواده‌ها</button>
                <button onclick="location.href='manage_presence.php'" class="btn bg-indigo-600 text-white hover:bg-indigo-700">مدیریت حضور خانواده‌ها</button>
                <button onclick="location.href='calculate_balance.php'" class="btn bg-green-600 text-white hover:bg-green-700">تراز مالی</button>
            </div>
        </div>
    </div>
</body>
</html>