<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up | AKAF Menu</title>
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
        <h2><i class="fas fa-user-plus"></i> Create an Account</h2>
        <form action="process_register.php" method="POST">
            <div class="mb-3">
			    <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="mb-3">
			    <label for="full_name" class="form-label">Full Name</label> 
                <input type="text" name="name" class="form-control">
            </div>
            <div class="mb-3">
			       <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
			       <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                <input type="password" name="password" class="form-control" required>
            </div>
            
			<?php 
			$jsonData = file_get_contents('data/countries.json');
			$countries = json_decode($jsonData, true);
			function getFlagEmoji($countryCode) {
				$codePoints = explode(' ',mb_convert_encoding('&#' . (127397 + ord($countryCode[0])) . ';&#' . (127397 + ord($countryCode[1])) . ';', 'UTF-8', 'HTML-Entities'));
				return implode('', $codePoints);
			}
			?>
			<form method="POST">
			<div class="mb-3">
			    <label for="phone" class="form-label">Phone Number</label>
				<div class="input-group">
				    <select class="form-select" name="country-code" >
					<?php
					foreach ($countries as $code => $country) {
						$dialCode = isset($country['dial_code']) ? $country['dial_code'] : '';
						$flagEmoji = get flagEmoji($code);
						echo "<option value=\"$dialCode\">$flagEmoji $dialCode ({$country['name']})</option>";
					}
					?>
					</Select>
                <input type="tel" name="phone" class="form-control" placeholder="Enter your Phone number">
            </div>
            <div class="mb-3">
                <input type="text" id="datepicker" name="dob" class="form-control" placeholder="Date of Birth" required>
            </div>
			</div>
			<button type="submit" class="btn btn-primary"> SEND </button>
            <div class="mb-3">
                <select name="language" class="form-control" required>
                    <option value="">Select Default Language</option>
                    <option value="en">English</option>
                    <option value="fa">Persian</option>
                    <option value="fr">French</option>
                    <option value="es">Spanish</option>
                    <option value="de">German</option>
                    <option value="it">Italian</option>
                    <option value="ar">Arabic</option>
                    <option value="zh">Chinese</option>
                </select>
            </div>
            <div class="mb-3">
                <select name="nationality" class="form-control" required>
                    <option value="">Select Nationality</option>
                    <option value="Afghan">Afghan</option>
                    <option value="Albanian">Albanian</option>
                    <option value="Algerian">Algerian</option>
                    <option value="American">American</option>
                    <option value="Andorran">Andorran</option>
                    <option value="Angolan">Angolan</option>
                    <option value="Argentinian">Argentinian</option>
                    <option value="Armenian">Armenian</option>
                    <option value="Australian">Australian</option>
                    <option value="Austrian">Austrian</option>
                    <option value="Azerbaijani">Azerbaijani</option>
                    <option value="Bahraini">Bahraini</option>
                    <option value="Bangladeshi">Bangladeshi</option>
                    <option value="Brazilian">Brazilian</option>
                    <option value="British">British</option>
                    <option value="Canadian">Canadian</option>
                    <option value="Chilean">Chilean</option>
                    <option value="Chinese">Chinese</option>
                    <option value="Colombian">Colombian</option>
                    <option value="Egyptian">Egyptian</option>
                    <option value="French">French</option>
                    <option value="German">German</option>
                    <option value="Indian">Indian</option>
                    <option value="Iranian">Iranian</option>
                    <option value="Iraqi">Iraqi</option>
                    <option value="Italian">Italian</option>
                    <option value="Japanese">Japanese</option>
                    <option value="Jordanian">Jordanian</option>
                    <option value="Kuwaiti">Kuwaiti</option>
                    <option value="Lebanese">Lebanese</option>
                    <option value="Malaysian">Malaysian</option>
                    <option value="Mexican">Mexican</option>
                    <option value="Pakistani">Pakistani</option>
                    <option value="Palestinian">Palestinian</option>
                    <option value="Russian">Russian</option>
                    <option value="Saudi">Saudi</option>
                    <option value="Syrian">Syrian</option>
                    <option value="Turkish">Turkish</option>
                    <option value="United States">United States</option>
                </select>
            </div>
            <button type="submit" class="btn btn-success w-100"><i class="fas fa-user-plus"></i> Sign Up</button>
        </form>
        <p class="mt-3">Already have an account? <a href="user_login.php" class="text-info">Login</a></p>
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
