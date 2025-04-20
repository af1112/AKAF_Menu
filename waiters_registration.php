<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waiters Sign Up | AKAF Menu</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <style>
        body {
            background: linear-gradient(to right, #141E30, #243B55);
            color: white;
            font-family: 'Arial', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
			margin-top: 90px;
        }
        .register-box {
            background: rgba(0, 0, 0, 0.8);
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.2);
            width: 400px;
            text-align: center;
        }
        .register-box h2 {
            margin-bottom: 20px;
            font-weight: bold;
        }
        .register-box .form-control {
            background: transparent;
            border: none;
            border-bottom: 2px solid white;
            color: white;
            text-align: center;
        }
        .register-box .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }
        .register-box .btn {
            background: #28a745;
            border: none;
            transition: 0.3s;
        }
        .register-box .btn:hover {
            background: #218838;
        }
        select {
            background-color: white !important;
            color: black !important;
        }
        option {
            background-color: white;
            color: black;
        }
    </style>

</head>
<body>

    <div class="register-box">
        <h2><i class="fas fa-user-plus"></i> Create an Waiter Account</h2>
        <form action="process_waiter_registration.php" method="POST">
            <div class="mb-3">
			    <label for="fullName" class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="fullname" class="form-control" required>
            </div>
            <div class="mb-3">
			    <label for="Image_url" class="form-label">Waiter Image</label> 
                <input type="file" name="image_url" class="form-control">
            </div>
			<div class="mb-3">
			    <label for="phone_number" class="form-label">Phone Number</label>
                <input type="tel" name="phone_number" class="form-control" placeholder="Enter your Phone number">
			</div>
            <div class="mb-3">
                <input type="text" id="datepicker" name="dob" class="form-control" placeholder="Date of Birth" required> <!--date of birth-->
            </div>
			<div class="mb-3">
			    <label for="ID_num" class="form-label">ID Number </label>
				<input type="text" name="ID_num" class="form-control">
			</div>
			<button type="submit" class="btn btn-primary"> SEND </button>
			
    </div>

    <script>
        $(function () {
            $("#datepicker").datepicker({
                dateFormat: "yy-mm-dd",
                changeMonth: true,
                changeYear: true,
                yearRange: "1900:2025"
            });
        });
    </script>

</body>
</html>
