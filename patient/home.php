<?php
session_start();
include("../config/db.php");

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "patient"
) {
    header("Location: ../login.php");
    exit();
}

$patientId = (int) $_SESSION["user_id"];
$patientName = $_SESSION["user_name"] ?? "Patient";

$upcomingCount = 0;
$completedCount = 0;
$missedCount = 0;

$stmt = $conn->prepare("
    SELECT status, COUNT(*) AS total
    FROM appointments
    WHERE patient_id = ?
    GROUP BY status
");

$stmt->bind_param("i", $patientId);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    if ($row["status"] === "booked") {
        $upcomingCount = (int) $row["total"];
    } elseif ($row["status"] === "completed") {
        $completedCount = (int) $row["total"];
    } elseif ($row["status"] === "missed") {
        $missedCount = (int) $row["total"];
    }
}

$stmt->close();

include("../includes/header.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Patient Home | OneCare</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background:
                radial-gradient(
                    circle at top left,
                    rgba(30, 136, 229, 0.10),
                    transparent 32%
                ),
                linear-gradient(
                    135deg,
                    #eef7ff,
                    #f8fbff 55%,
                    #edf6ff
                );
            margin: 0;
            min-height: 100vh;
            color: #263238;
        }

        .page-wrapper {
            width: min(1180px, 92%);
            margin: 38px auto 60px;
        }

        .hero {
            position: relative;
            overflow: hidden;
            background:
                linear-gradient(
                    135deg,
                    #0d47a1,
                    #1976d2,
                    #42a5f5
                );
            padding: 38px;
            border-radius: 28px;
            box-shadow: 0 16px 40px rgba(21, 101, 192, 0.24);
            margin-bottom: 28px;
            color: white;
        }

        .hero::before {
            content: "";
            position: absolute;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.10);
            right: -75px;
            top: -120px;
        }

        .hero::after {
            content: "";
            position: absolute;
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
            right: 125px;
            bottom: -125px;
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .hero-badge {
            display: inline-block;
            padding: 7px 13px;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: 999px;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 13px;
        }

        .hero h1 {
            margin: 0;
            font-size: clamp(30px, 4vw, 42px);
            letter-spacing: -1px;
        }

        .hero p {
            margin: 12px 0 0;
            color: rgba(255, 255, 255, 0.90);
            font-size: 16px;
            line-height: 1.7;
            max-width: 650px;
        }

        .statistics-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 34px;
        }

        .stat-card {
            background: white;
            border-radius: 19px;
            padding: 22px;
            border: 1px solid #e3edf7;
            box-shadow: 0 8px 24px rgba(21, 101, 192, 0.07);
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .stat-icon {
            width: 52px;
            height: 52px;
            min-width: 52px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .upcoming-icon {
            background: #e3f2fd;
        }

        .completed-icon {
            background: #e8f5e9;
        }

        .missed-icon {
            background: #fff3e0;
        }

        .stat-number {
            display: block;
            font-size: 27px;
            color: #16324f;
            margin-bottom: 3px;
        }

        .stat-label {
            color: #78909c;
            font-size: 13px;
            font-weight: bold;
        }

        .section-heading {
            margin-bottom: 18px;
        }

        .section-heading h2 {
            color: #16324f;
            margin: 0;
            font-size: 25px;
        }

        .section-heading p {
            color: #78909c;
            margin: 6px 0 0;
            font-size: 14px;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 23px;
        }

        .action-card {
            position: relative;
            overflow: hidden;
            background: white;
            padding: 28px;
            border-radius: 23px;
            border: 1px solid #e2ecf5;
            box-shadow: 0 10px 28px rgba(21, 101, 192, 0.08);
            transition: 0.25s ease;
        }

        .action-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 36px rgba(21, 101, 192, 0.13);
        }

        .action-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            height: 6px;
            width: 100%;
            background:
                linear-gradient(
                    90deg,
                    #1e88e5,
                    #64b5f6
                );
        }

        .action-icon {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            background: #e3f2fd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 31px;
            margin-bottom: 20px;
        }

        .action-card h3 {
            color: #1565c0;
            font-size: 23px;
            margin: 0 0 10px;
        }

        .action-card p {
            color: #607d8b;
            margin: 0 0 22px;
            line-height: 1.7;
            min-height: 54px;
        }

        .action-btn {
            display: block;
            width: 100%;
            text-align: center;
            padding: 13px 18px;
            border-radius: 12px;
            background: #1565c0;
            color: white;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
            transition: 0.2s ease;
        }

        .action-btn:hover {
            background: #0d47a1;
            transform: translateY(-2px);
        }

        .appointments-btn {
            background: #43a047;
        }

        .appointments-btn:hover {
            background: #2e7d32;
        }

        @media (max-width: 800px) {
            .page-wrapper {
                width: 94%;
                margin-top: 24px;
            }

            .hero {
                padding: 28px 23px;
            }

            .statistics-grid {
                grid-template-columns: 1fr;
            }

            .actions-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<div class="page-wrapper">

    <section class="hero">

        <div class="hero-content">

            <span class="hero-badge">
                OneCare Patient Portal
            </span>

            <h1>
                Welcome,
                <?php echo htmlspecialchars($patientName); ?>
            </h1>

            <p>
                Find doctors, book medical appointments,
                and manage your appointment history from one place.
            </p>

        </div>

    </section>

    <section class="statistics-grid">

        <div class="stat-card">

            <div class="stat-icon upcoming-icon">
                📅
            </div>

            <div>

                <strong class="stat-number">
                    <?php echo $upcomingCount; ?>
                </strong>

                <span class="stat-label">
                    Booked Appointments
                </span>

            </div>

        </div>

        <div class="stat-card">

            <div class="stat-icon completed-icon">
                ✓
            </div>

            <div>

                <strong class="stat-number">
                    <?php echo $completedCount; ?>
                </strong>

                <span class="stat-label">
                    Completed Appointments
                </span>

            </div>

        </div>

        <div class="stat-card">

            <div class="stat-icon missed-icon">
                !
            </div>

            <div>

                <strong class="stat-number">
                    <?php echo $missedCount; ?>
                </strong>

                <span class="stat-label">
                    Missed Appointments
                </span>

            </div>

        </div>

    </section>

    <section>

        <div class="section-heading">

            <h2>Patient Services</h2>

            <p>
                Choose the service you would like to use.
            </p>

        </div>

        <div class="actions-grid">

            <article class="action-card">

                <div class="action-icon">
                    🔍
                </div>

                <h3>Search Doctor</h3>

                <p>
                    Search by doctor name, specialization,
                    profession, city, or healthcare provider.
                </p>

                <a
                    class="action-btn"
                    href="/OneCare/patient/search.php"
                >
                    Find a Doctor
                </a>

            </article>

            <article class="action-card">

                <div class="action-icon">
                    📋
                </div>

                <h3>My Appointments</h3>

                <p>
                    View booked, completed, cancelled,
                    and missed medical appointments.
                </p>

                <a
                    class="action-btn appointments-btn"
                    href="/OneCare/patient/appointments.php"
                >
                    View My Appointments
                </a>

            </article>

        </div>

    </section>

</div>

</body>
</html>