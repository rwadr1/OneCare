<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$userName = $_SESSION["user_name"] ?? "Guest";
$userRole = $_SESSION["role"] ?? "";

$homeLinks = [
    "patient" => "/OneCare/patient/home.php",
    "senior"  => "/OneCare/senior/home.php",
    "doctor"  => "/OneCare/doctor/home.php",
    "admin"   => "/OneCare/admin/manage.php"
];

$homeLink = $homeLinks[$userRole] ?? "/OneCare/login.php";
?>

<style>
*{box-sizing:border-box}

.navbar{
    position:sticky;
    top:0;
    z-index:1000;
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:14px 34px;
    background:rgba(255,255,255,.96);
    border-bottom:1px solid #e6eef7;
    box-shadow:0 5px 18px rgba(21,101,192,.08);
    font-family:Arial,sans-serif
}

.navbar-left,.navbar-right,.logo{
    display:flex;
    align-items:center
}

.navbar-left{gap:35px}
.navbar-right{gap:14px}

.logo{
    gap:11px;
    color:#1565c0;
    font-size:28px;
    font-weight:bold;
    text-decoration:none
}

.logo img{
    width:44px;
    height:44px;
    object-fit:contain
}

.home-link{
    padding:11px 18px;
    border-radius:12px;
    color:#455a64;
    font-weight:bold;
    text-decoration:none
}

.home-link:hover{
    color:#1565c0;
    background:#eef6ff
}

.user-box{
    padding:9px 15px;
    border-radius:14px;
    background:#eef6ff
}

.user-name,.user-role{display:block}

.user-name{
    color:#1565c0;
    font-weight:bold
}

.user-role{
    margin-top:2px;
    font-size:12px;
    color:#607d8b
}

.logout-btn{
    padding:10px 17px;
    border-radius:12px;
    color:white;
    background:#c62828;
    text-decoration:none;
    font-weight:bold
}

.logout-btn:hover{background:#a91d1d}

@media(max-width:650px){
    .navbar{
        padding:13px 18px;
        gap:12px;
        flex-wrap:wrap
    }

    .navbar-left{gap:15px}

    .logo{font-size:23px}

    .logo img{
        width:38px;
        height:38px
    }
}
</style>

<nav class="navbar">

    <div class="navbar-left">

        <a class="logo" href="<?php echo $homeLink; ?>">
            <img src="/OneCare/assets/images/logo99.png" alt="OneCare Logo">
            <span>OneCare</span>
        </a>
    
        <?php if ($userRole): ?>
            <a class="home-link" href="<?php echo $homeLink; ?>">
                Home
            </a>
        <?php endif; ?>

    </div>

    <?php if ($userRole): ?>

        <div class="navbar-right">

            <div class="user-box">
                <span class="user-name">
                    <?php echo htmlspecialchars($userName); ?>
                </span>

                <span class="user-role">
                    <?php echo ucfirst($userRole); ?>
                </span>
            </div>

            <a class="logout-btn" href="/OneCare/logout.php">
                Logout
            </a>

        </div>

    <?php endif; ?>

</nav>