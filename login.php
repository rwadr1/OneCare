<?php
session_start();
include("config/db.php");

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = $conn->prepare("SELECT id,name,email,password,role FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($password, $user["password"])) {
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["user_name"] = $user["name"];
        $_SESSION["role"] = $user["role"];

        $pages = [
            "patient" => "patient/home.php",
            "senior" => "senior/home.php",
            "doctor" => "doctor/home.php",
            "admin" => "admin/manage.php"
        ];

        header("Location: " . ($pages[$user["role"]] ?? "patient/home.php"));
        exit();
    }

    $message = "Incorrect email or password.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login | OneCare</title>

<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;font-family:Arial,sans-serif;color:#263238;background:linear-gradient(135deg,#eaf5ff,#f8fbff)}
.page{min-height:100vh;display:grid;grid-template-columns:1.15fr .85fr}
.intro{display:flex;flex-direction:column;justify-content:center;padding:70px;background:linear-gradient(135deg,#0d47a1,#1976d2,#42a5f5);color:white}
.logo{display:flex;align-items:center;gap:12px;margin-bottom:45px;font-size:30px;font-weight:bold}
.logo img{width:55px;height:55px;object-fit:contain;background:transparent;border-radius:0;padding:0}
.intro h1{margin:0 0 18px;font-size:48px;line-height:1.1}
.intro p{max-width:560px;margin:0 0 30px;font-size:20px;line-height:1.7;color:rgba(255,255,255,.92)}
.features{display:grid;gap:14px}
.feature{padding:15px 18px;border-radius:15px;background:rgba(255,255,255,.14);font-size:17px}
.login-side{display:flex;align-items:center;justify-content:center;padding:35px}
.login-box{width:min(430px,100%);padding:38px;border-radius:25px;background:white;box-shadow:0 18px 45px rgba(21,101,192,.16)}
.login-box h2{margin:0 0 8px;color:#1565c0;font-size:32px}
.subtitle{margin:0 0 25px;color:#607d8b}
label{display:block;margin:15px 0 7px;font-weight:bold;color:#37474f}
input{width:100%;padding:14px;border:1px solid #cfd8dc;border-radius:12px;font-size:16px;background:#f8fbfe}
input:focus{outline:none;border-color:#1e88e5;box-shadow:0 0 0 3px rgba(30,136,229,.12)}
button{width:100%;padding:14px;margin-top:22px;border:0;border-radius:12px;color:white;background:#1565c0;font-size:17px;font-weight:bold;cursor:pointer}
button:hover{background:#0d47a1}
.message{padding:12px;margin-bottom:15px;border-radius:10px;color:#c62828;background:#ffebee;text-align:center;font-weight:bold}
.signup{display:block;margin-top:18px;text-align:center;color:#1565c0;text-decoration:none}
.signup:hover{text-decoration:underline}
@media(max-width:850px){.page{grid-template-columns:1fr}.intro{padding:40px}.intro h1{font-size:38px}.login-side{padding:30px 20px}}
.forgot{
    display:block;
    margin-top:16px;
    text-align:center;
    color:#607d8b;
    text-decoration:none
}

.forgot:hover{
    color:#1565c0;
    text-decoration:underline
}
</style>
</head>

<body>

<div class="page">

    <section class="intro">
        <div class="logo">
            <img src="/OneCare/assets/images/logo99.png" alt="OneCare Logo">
            <span>OneCare</span>
        </div>

        <h1>Your healthcare, organized in one place.</h1>

        <p>
            Search for doctors, book appointments, manage schedules,
            and review medical visit summaries easily.
        </p>

        <div class="features">
            <div class="feature">Find doctors by specialization and provider</div>
            <div class="feature">Book and manage appointments online</div>
            <div class="feature">Access medical visit summaries securely</div>
        </div>
    </section>

    <section class="login-side">

        <div class="login-box">
            <h2>Welcome Back</h2>
            <p class="subtitle">Sign in to your OneCare account.</p>

            <?php if ($message): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <form method="post">

                <label>Email Address</label>
                <input
                    type="email"
                    name="email"
                    placeholder="Enter your email"
                    value="<?php echo htmlspecialchars($_POST["email"] ?? ""); ?>"
                    required
                >

                <label>Password</label>
                <input
                    type="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                >

            <button type="submit">Login to OneCare</button>
                </form>

                <a class="forgot" href="forgot_password.php">
                    Forgot your password?
                </a>

                <a class="signup" href="signup.php">
                    Don't have an account? Create one
                </a>
        </div>

    </section>

</div>

</body>
</html>