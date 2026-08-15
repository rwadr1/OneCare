<?php
session_start();
include("config/db.php");

$message = "";
$success = false;
$resetRequest = null;

$token = trim($_GET["token"] ?? $_POST["token"] ?? "");

if ($token === "") {
    $message = "Invalid password reset link.";
} else {
    $tokenHash = hash("sha256", $token);

    $stmt = $conn->prepare(
        "SELECT
            pr.id,
            pr.user_id,
            u.password AS current_password
         FROM password_resets pr
         JOIN users u ON u.id = pr.user_id
         WHERE pr.token_hash = ?
         AND pr.expires_at > NOW()
         AND pr.used_at IS NULL
         LIMIT 1"
    );

    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();

    $resetRequest = $stmt->get_result()->fetch_assoc();

    if (!$resetRequest) {
        $message = "This password reset link is invalid, expired, or already used.";
    } elseif ($_SERVER["REQUEST_METHOD"] === "POST") {
        $password = $_POST["password"] ?? "";
        $confirmPassword = $_POST["confirm_password"] ?? "";

        if ($password !== $confirmPassword) {
            $message = "The passwords do not match.";

        } elseif (strlen($password) < 8) {
            $message = "Password must contain at least 8 characters.";

        } elseif (preg_match('/\s/', $password)) {
            $message = "Password cannot contain spaces.";

        } elseif (!preg_match('/^[\x21-\x7E]+$/', $password)) {
            $message = "Password may contain English letters, numbers and symbols only.";

        } elseif (!preg_match('/[a-z]/', $password)) {
            $message = "Password must contain at least one lowercase English letter.";

        } elseif (!preg_match('/[0-9]/', $password)) {
            $message = "Password must contain at least one number.";

        } elseif (password_verify(
            $password,
            $resetRequest["current_password"]
        )) {
            $message = "The new password cannot be the same as your current password.";

        } else {
            $userId = (int)$resetRequest["user_id"];
            $resetId = (int)$resetRequest["id"];

            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $conn->begin_transaction();

            try {
                $updateStmt = $conn->prepare(
                    "UPDATE users
                     SET password = ?
                     WHERE id = ?"
                );

                $updateStmt->bind_param(
                    "si",
                    $passwordHash,
                    $userId
                );

                if (!$updateStmt->execute()) {
                    throw new Exception("Password update failed.");
                }

                $usedStmt = $conn->prepare(
                    "UPDATE password_resets
                     SET used_at = NOW()
                     WHERE id = ?"
                );

                $usedStmt->bind_param("i", $resetId);

                if (!$usedStmt->execute()) {
                    throw new Exception("Token update failed.");
                }

                $conn->commit();

                $success = true;
                $message = "Your password was changed successfully.";
            } catch (Throwable $e) {
                $conn->rollback();
                $message = "Unable to change the password.";
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

<title>Reset Password | OneCare</title>

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
    text-align:center
}

label{
    display:block;
    margin:15px 0 8px;
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

.password-help{
    display:block;
    margin-top:8px;
    color:#607d8b;
    font-size:13px;
    line-height:1.45
}

button{
    width:100%;
    padding:14px;
    margin-top:22px;
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
    color:#c62828;
    background:#ffebee;
    text-align:center;
    font-weight:bold;
    line-height:1.4
}

.message.success{
    color:#1b5e20;
    background:#e8f5e9
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

    <h1>Reset Password</h1>

    <p class="subtitle">
        Choose a new password for your account.
    </p>

    <?php if ($message): ?>
        <div class="message <?php echo $success ? "success" : ""; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if (!$success && $resetRequest): ?>

        <form method="post">

            <input
                type="hidden"
                name="token"
                value="<?php echo htmlspecialchars($token); ?>"
            >

            <label for="password">
                New Password
            </label>

            <input
                id="password"
                type="password"
                name="password"
                placeholder="Enter a strong password"
                minlength="8"
                autocomplete="new-password"
                required
            >

            <small class="password-help">
                At least 8 characters, one uppercase letter,
                one lowercase letter, one number and one special character.
                English characters only.
            </small>

            <label for="confirm_password">
                Confirm New Password
            </label>

            <input
                id="confirm_password"
                type="password"
                name="confirm_password"
                placeholder="Enter the password again"
                minlength="8"
                autocomplete="new-password"
                required
            >

            <button type="submit">
                Change Password
            </button>

        </form>

    <?php endif; ?>

    <a class="back" href="login.php">
        Back to Login
    </a>

</div>

</body>
</html>