<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION["role"] ?? "";
$userName = $_SESSION["user_name"] ?? "User";

if ($role === "senior") {
    header("Location: senior/home.php");
    exit();
}

if ($role === "doctor") {
    header("Location: doctor/home.php");
    exit();
}

if ($role === "admin") {
    header("Location: admin/manage.php");
    exit();
}

include("includes/header.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Home - OneCare</title>

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #eef7ff, #f8fbff);
            color: #263238;
        }

        .page-wrapper {
            width: 88%;
            margin: 40px auto;
        }

        .hero {
            background: white;
            padding: 35px;
            border-radius: 24px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .hero h1 {
            color: #1565c0;
            font-size: 36px;
            margin: 0;
        }

        .hero p {
            color: #607d8b;
            font-size: 17px;
            margin-top: 10px;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
        }

        .card {
            background: white;
            padding: 28px;
            border-radius: 22px;
            box-shadow: 0 8px 22px rgba(0,0,0,0.08);
            border: 1px solid #e3edf7;
            position: relative;
        }

        .card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            height: 7px;
            width: 100%;
            border-radius: 22px 22px 0 0;
            background: #1e88e5;
        }

        .icon {
            font-size: 38px;
            margin-bottom: 12px;
        }

        .card h3 {
            color: #1565c0;
            font-size: 23px;
            margin: 0 0 10px;
        }

        .card p {
            color: #607d8b;
            min-height: 45px;
        }

        .card a {
            display: inline-block;
            margin-top: 15px;
            padding: 12px 18px;
            background: #1565c0;
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: bold;
        }

        .card a:hover {
            background: #0d47a1;
        }
    </style>
</head>

<body>

<div class="page-wrapper">

    <div class="hero">
        <h1>Welcome to OneCare, <?php echo htmlspecialchars($userName); ?></h1>
        <p>Manage your medical appointments, find doctors, and view visit summaries in one place.</p>
    </div>

    <div class="cards-grid">

        <div class="card">
            <div class="icon">🔍</div>
            <h3>Search Doctor</h3>
            <p>Find doctors by specialization or provider.</p>
            <a href="patient/search.php">Search</a>
        </div>

        <div class="card">
            <div class="icon">📅</div>
            <h3>Book Appointment</h3>
            <p>Choose a doctor, date, and available time.</p>
            <a href="patient/search.php">Book Now</a>
        </div>

        <div class="card">
            <div class="icon">📄</div>
            <h3>My Appointments</h3>
            <p>View booked appointments and completed visit summaries.</p>
            <a href="patient/appointments.php">View</a>
        </div>

    </div>

</div>

</body>
</html>