<?php
session_start();
include("config/db.php");

$message = "";
$messageType = "";
$resetLink = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $messageType = "error";
    } else {
        $stmt = $conn->prepare(
            "SELECT id
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $user = $stmt->get_result()->fetch_assoc();

        /*
         * Always show the same message.
         * This prevents revealing whether an email exists.
         */
        $message = "If this email exists, a password reset link was created.";
        $messageType = "success";

        if ($user) {
            $userId = (int)$user["id"];

            /*
             * Mark previous unused reset links as used.
             */
            $oldStmt = $conn->prepare(
                "UPDATE password_resets
                 SET used_at = NOW()
                 WHERE user_id = ?
                 AND used_at IS NULL"
            );

            $oldStmt->bind_param("i", $userId);
            $oldStmt->execute();

            /*
             * Create a secure random token.
             */
            $token = bin2hex(random_bytes(32));

            /*
             * Store only the token hash in the database.
             */
            $tokenHash = hash("sha256", $token);

            /*
             * The reset link expires after 30 minutes.
             */
            $insertStmt = $conn->prepare(
                "INSERT INTO password_resets
                 (user_id, token_hash, expires_at)
                 VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))"
            );

            $insertStmt->bind_param(
                "is",
                $userId,
                $tokenHash
            );

            if ($insertStmt->execute()) {
                /*
                 * Development mode:
                 * Show the link on the screen for the project demo.
                 *
                 * In a real system, this link should be sent by email.
                 */
                $resetLink =
                    "http://localhost/OneCare/reset_password.php?token=" .
                    urlencode($token);
            } else {
                $message = "Unable to create the reset request.";
                $messageType = "error";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Forgot Password | OneCare</title>

<style>
*{
    box-sizing:border-box
}

body{
    margin:0;
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:25px;
    font-family:Arial,sans-serif;
    color:#263238;
    background:linear-gradient(135deg,#eaf5ff,#f8fbff)
}

.box{
    width:min(460px,100%);
    padding:38px;
    border-radius:22px;
    background:white;
    box-shadow:0 18px 45px rgba(21,101,192,.16)
}

.logo{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:10px;
    margin-bottom:25px;
    color:#1565c0;
    font-size:25px;
    font-weight:bold
}

.logo img{
    width:45px;
    height:45px;
    object-fit:contain
}

h1{
    margin:0 0 10px;
    color:#1565c0;
    text-align:center
}

.subtitle{
    margin:0 0 25px;
    color:#607d8b;
    text-align:center;
    line-height:1.5
}

label{
    display:block;
    margin:0 0 8px;
    font-weight:bold
}

input{
    width:100%;
    padding:14px;
    border:1px solid #cfd8dc;
    border-radius:12px;
    font-size:16px;
    background:#f8fbfe
}

input:focus{
    outline:none;
    border-color:#1e88e5;
    box-shadow:0 0 0 3px rgba(30,136,229,.12)
}

button{
    width:100%;
    padding:14px;
    margin-top:20px;
    border:0;
    border-radius:12px;
    color:white;
    background:#1565c0;
    font-size:17px;
    font-weight:bold;
    cursor:pointer
}

button:hover{
    background:#0d47a1
}

.message{
    padding:12px;
    margin-bottom:18px;
    border-radius:10px;
    text-align:center;
    font-weight:bold;
    line-height:1.4
}

.message.success{
    color:#1565c0;
    background:#e3f2fd
}

.message.error{
    color:#c62828;
    background:#ffebee
}

.reset-link{
    margin-top:18px;
    padding:14px;
    border-radius:10px;
    color:#1b5e20;
    background:#e8f5e9;
    word-break:break-all;
    line-height:1.5
}

.reset-link a{
    color:#1b5e20;
    font-weight:bold
}

.warning{
    display:block;
    margin-top:10px;
    color:#607d8b;
    font-size:13px;
    line-height:1.4
}

.back{
    display:block;
    margin-top:20px;
    text-align:center;
    color:#1565c0;
    text-decoration:none
}

.back:hover{
    text-decoration:underline
}
</style>
</head>

<body>

<div class="box">

    <div class="logo">
        <img
            src="/OneCare/assets/images/logo99.png"
            alt="OneCare Logo"
        >

        <span>OneCare</span>
    </div>

    <h1>Forgot Password</h1>

    <p class="subtitle">
        Enter the email address connected to your account.
    </p>

    <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form method="post">

        <label for="email">
            Email Address
        </label>

        <input
            id="email"
            type="email"
            name="email"
            placeholder="Enter your email"
            value="<?php echo htmlspecialchars($_POST["email"] ?? ""); ?>"
            autocomplete="email"
            required
        >

        <button type="submit">
            Create Reset Link
        </button>

    </form>

    <?php if ($resetLink): ?>
        <div class="reset-link">

            <strong>Development reset link:</strong>

            <br><br>

            <a href="<?php echo htmlspecialchars($resetLink); ?>">
                Open Reset Password Page
            </a>

            <small class="warning">
                This link is displayed only for the local project demo.
                In a real system, it should be sent to the user's email.
            </small>

        </div>
    <?php endif; ?>

    <a class="back" href="login.php">
        Back to Login
    </a>

</div>

</body>
</html>