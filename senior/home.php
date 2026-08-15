<?php
session_start();

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "senior"
) {
    header("Location: ../login.php");
    exit();
}

$name = $_SESSION["user_name"] ?? "Senior";

include("../includes/header.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Senior Home | OneCare</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            color: #263238;
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
        }

        .page-wrapper {
            width: min(1180px, 92%);
            margin: 38px auto 60px;
        }

        .hero {
            padding: 40px;
            margin-bottom: 30px;
            border-radius: 28px;
            color: white;
            background:
                linear-gradient(
                    135deg,
                    #0d47a1,
                    #1976d2,
                    #42a5f5
                );
            box-shadow:
                0 16px 40px rgba(21, 101, 192, 0.24);
        }

        .hero-badge {
            display: inline-block;
            padding: 8px 14px;
            margin-bottom: 13px;
            border-radius: 999px;
            font-size: 15px;
            font-weight: bold;
            background: rgba(255,255,255,0.18);
            border: 1px solid rgba(255,255,255,0.28);
        }

        .hero h1 {
            margin: 0;
            font-size: 42px;
        }

        .hero p {
            max-width: 700px;
            margin: 12px 0 0;
            font-size: 20px;
            line-height: 1.7;
            color: rgba(255,255,255,0.92);
        }

        .section-title {
            margin-bottom: 18px;
        }

        .section-title h2 {
            margin: 0;
            color: #16324f;
            font-size: 29px;
        }

        .section-title p {
            margin: 7px 0 0;
            color: #78909c;
            font-size: 18px;
        }

        .services {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }

        .service-card {
            padding: 30px;
            border-radius: 23px;
            background: white;
            border: 1px solid #e2ecf5;
            box-shadow:
                0 10px 28px rgba(21, 101, 192, 0.08);
        }

        .service-icon {
            width: 72px;
            height: 72px;
            margin-bottom: 18px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            background: #e3f2fd;
        }

        .service-card h3 {
            margin: 0 0 10px;
            color: #1565c0;
            font-size: 27px;
        }

        .service-card p {
            min-height: 60px;
            margin: 0 0 22px;
            color: #607d8b;
            font-size: 18px;
            line-height: 1.7;
        }

        .service-btn {
            display: block;
            padding: 16px;
            border-radius: 14px;
            text-align: center;
            text-decoration: none;
            color: white;
            font-size: 20px;
            font-weight: bold;
            background: #1565c0;
        }

        .appointments-btn {
            background: #43a047;
        }

        @media (max-width: 760px) {
            .page-wrapper {
                width: 94%;
            }

            .hero {
                padding: 28px 24px;
            }

            .services {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<div class="page-wrapper">

    <section class="hero">

        <span class="hero-badge">
            OneCare Senior Portal
        </span>

        <h1>
            Welcome, <?php echo htmlspecialchars($name); ?>
        </h1>

        <p>
            Search for doctors, book appointments
            and review your medical visits easily.
        </p>

    </section>

    <div class="section-title">
        <h2>Senior Services</h2>
        <p>Choose the service you need.</p>
    </div>

    <section class="services">

        <article class="service-card">

            <div class="service-icon">🔍</div>

            <h3>Search Doctor</h3>

            <p>
                Find a doctor and continue
                to choose an appointment date and time.
            </p>

            <a class="service-btn" href="search.php">
                Find a Doctor
            </a>

        </article>

        <article class="service-card">

            <div class="service-icon">📋</div>

            <h3>My Appointments</h3>

            <p>
                View upcoming, completed,
                cancelled and missed appointments.
            </p>

            <a
                class="service-btn appointments-btn"
                href="appointments.php"
            >
                View My Appointments
            </a>

        </article>

    </section>

</div>

</body>
</html>