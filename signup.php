<?php
include("config/db.php");

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST["name"];
    $email = $_POST["email"];
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT);
    $role = $_POST["role"];

    $check = $conn->query("SELECT * FROM users WHERE email='$email'");

    if ($check->num_rows > 0) {
        $message = "Email already exists!";
    } else {
        $sql = "INSERT INTO users (name, email, password, role)
                VALUES ('$name', '$email', '$password', '$role')";

        if ($conn->query($sql) === TRUE) {
            $new_user_id = $conn->insert_id;

            if ($role == "doctor") {
                $specialization = "General Doctor";
                $provider_id = 1;

                $insertDoctor = "INSERT INTO doctors (user_id, provider_id, specialization)
                                 VALUES ('$new_user_id', '$provider_id', '$specialization')";

                if ($conn->query($insertDoctor) === TRUE) {
                    $new_doctor_id = $conn->insert_id;

                    for ($day = 0; $day <= 4; $day++) {
                        $conn->query("INSERT INTO doctor_schedule 
                                      (doctor_id, day_of_week, start_time, end_time)
                                      VALUES 
                                      ('$new_doctor_id', '$day', '08:00:00', '18:00:00')");
                    }
                }
            }

            $message = "Registration successful!";
        } else {
            $message = "Error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up - OneCare</title>

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background:
                radial-gradient(circle at top left, #bbdefb 0, transparent 30%),
                radial-gradient(circle at bottom right, #90caf9 0, transparent 28%),
                linear-gradient(135deg, #eef7ff, #ffffff);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #263238;
        }

        .signup-box {
            width: 420px;
            background: white;
            padding: 38px;
            border-radius: 26px;
            box-shadow: 0 18px 45px rgba(0,0,0,0.12);
            border: 1px solid #e3edf7;
        }

        .logo-area {
            text-align: center;
            margin-bottom: 25px;
        }

        .logo-area img {
            width: 85px;
            height: 85px;
            object-fit: cover;
            border-radius: 20px;
            margin-bottom: 10px;
        }

        .logo-area h2 {
            margin: 0;
            color: #1565c0;
            font-size: 30px;
        }

        .logo-area p {
            margin: 8px 0 0;
            color: #607d8b;
            font-size: 15px;
        }

        .message {
            text-align: center;
            padding: 12px;
            border-radius: 12px;
            background: #e8f5e9;
            color: #2e7d32;
            font-weight: bold;
            margin-bottom: 18px;
        }

        .error {
            background: #ffebee;
            color: #c62828;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
            color: #455a64;
        }

        input, select {
            width: 100%;
            padding: 13px;
            margin-bottom: 16px;
            border-radius: 12px;
            border: 1px solid #cfd8dc;
            box-sizing: border-box;
            font-size: 15px;
        }

        button {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, #1565c0, #1e88e5);
            color: white;
            font-size: 17px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 10px 22px rgba(21,101,192,0.25);
        }

        .login-link {
            display: block;
            margin-top: 18px;
            text-align: center;
            color: #1565c0;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>

<body>

<div class="signup-box">

    <div class="logo-area">
        <img src="assets/images/logo99.png" alt="OneCare Logo">
        <h2>Create Account</h2>
        <p>Join OneCare and manage your healthcare easily.</p>
    </div>

    <?php if ($message != ""): ?>
        <p class="message <?php echo ($message == 'Email already exists!') ? 'error' : ''; ?>">
            <?php echo $message; ?>
        </p>
    <?php endif; ?>

    <form method="post">

        <label>Full Name</label>
        <input type="text" name="name" placeholder="Enter full name" required>

        <label>Email</label>
        <input type="email" name="email" placeholder="Enter email" required>

        <label>Password</label>
        <input type="password" name="password" placeholder="Enter password" required>

        <label>Account Type</label>
        <select name="role">
            <option value="patient">Regular User</option>
            <option value="senior">Senior</option>
        </select>

        <button type="submit">Create Account</button>
    </form>

    <a class="login-link" href="login.php">Already have an account? Login</a>

</div>

</body>
</html>