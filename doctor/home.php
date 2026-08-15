<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "doctor") {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION["user_id"];
$user_name = $_SESSION["user_name"];

$sqlDoctor = "SELECT id FROM doctors WHERE user_id = '$user_id'";
$resultDoctor = $conn->query($sqlDoctor);

if (!$resultDoctor || $resultDoctor->num_rows == 0) {
    die("Doctor not found.");
}

$doctor = $resultDoctor->fetch_assoc();
$doctor_id = $doctor["id"];

$today = date("Y-m-d");

// תורים היום
$sqlToday = "SELECT COUNT(*) AS total
             FROM appointments
             WHERE doctor_id = '$doctor_id'
             AND date = '$today'
             AND status = 'booked'";

$resultToday = $conn->query($sqlToday);
$todayAppointments = $resultToday->fetch_assoc()["total"] ?? 0;

// תורים עתידיים
$sqlUpcoming = "SELECT COUNT(*) AS total
                FROM appointments
                WHERE doctor_id = '$doctor_id'
                AND date >= '$today'
                AND status = 'booked'";

$resultUpcoming = $conn->query($sqlUpcoming);
$upcomingAppointments = $resultUpcoming->fetch_assoc()["total"] ?? 0;

// סיכומים שהושלמו
$sqlCompleted = "SELECT COUNT(*) AS total
                 FROM appointments
                 WHERE doctor_id = '$doctor_id'
                 AND status = 'completed'";

$resultCompleted = $conn->query($sqlCompleted);
$completedVisits = $resultCompleted->fetch_assoc()["total"] ?? 0;

include("../includes/header.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Doctor Dashboard</title>

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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 22px;
            box-shadow: 0 8px 22px rgba(0,0,0,0.08);
            text-align: center;
            border: 1px solid #e3edf7;
        }

        .stat-number {
            font-size: 42px;
            color: #1565c0;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .stat-title {
            color: #607d8b;
            font-size: 18px;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
        }

        .action-card {
            background: white;
            padding: 28px;
            border-radius: 22px;
            box-shadow: 0 8px 22px rgba(0,0,0,0.08);
            border: 1px solid #e3edf7;
            position: relative;
        }

        .action-card::before {
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

        .action-card h3 {
            color: #1565c0;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .action-card p {
            color: #607d8b;
            min-height: 45px;
        }

        .action-card a {
            display: inline-block;
            margin-top: 15px;
            padding: 12px 18px;
            background: #1565c0;
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: bold;
        }
    </style>
</head>

<body>

<div class="page-wrapper">

    <div class="hero">
        <h1>Welcome Dr. <?php echo htmlspecialchars($user_name); ?></h1>
        <p>Manage appointments, calendars and patient visit summaries.</p>
    </div>

    <div class="stats-grid">

        <div class="stat-card">
            <div class="stat-number"><?php echo $todayAppointments; ?></div>
            <div class="stat-title">Appointments Today</div>
        </div>

        <div class="stat-card">
            <div class="stat-number"><?php echo $upcomingAppointments; ?></div>
            <div class="stat-title">Upcoming Appointments</div>
        </div>

        <div class="stat-card">
            <div class="stat-number"><?php echo $completedVisits; ?></div>
            <div class="stat-title">Completed Visits</div>
        </div>

    </div>

    <div class="actions-grid">

        <div class="action-card">
            <div class="icon">📅</div>
            <h3>Calendar</h3>
            <p>View and manage appointments by day and month.</p>
            <a href="calendar.php">Open Calendar</a>
        </div>

        <div class="action-card">
            <div class="icon">🩺</div>
            <h3>Visit Summaries</h3>
            <p>Complete patient visit summaries and medical notes.</p>
            <a href="calendar.php">View Appointments</a>
        </div>

        <div class="action-card">
            <div class="icon">👨‍⚕️</div>
            <h3>Doctor Schedule</h3>
            <p>Manage your working days and appointment hours.</p>
            <a href="schedule.php">Manage Schedule</a>
        </div>

    </div>

</div>

</body>
</html>