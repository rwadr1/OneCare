<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "senior") {
    header("Location: ../login.php");
    exit();
}

$patientId = (int) $_SESSION["user_id"];
$message = "";

$conn->query("
    UPDATE appointments
    SET status = 'missed'
    WHERE patient_id = $patientId
      AND status = 'booked'
      AND TIMESTAMP(date, time) < NOW()
");

if (isset($_GET["cancel_id"])) {
    $appointmentId = (int) $_GET["cancel_id"];

    $cancel = $conn->query("
        UPDATE appointments
        SET status = 'cancelled'
        WHERE id = $appointmentId
          AND patient_id = $patientId
          AND status = 'booked'
          AND TIMESTAMP(date, time) > NOW()
    ");

    $message = $cancel && $conn->affected_rows > 0
        ? "Appointment cancelled successfully."
        : "This appointment cannot be cancelled.";
}

$result = $conn->query("
    SELECT appointments.id, appointments.date,
           appointments.time, appointments.status,
           users.name AS doctor_name,
           doctors.specialization,
           providers.name AS provider_name
    FROM appointments
    JOIN doctors ON appointments.doctor_id = doctors.id
    JOIN users ON doctors.user_id = users.id
    JOIN providers ON doctors.provider_id = providers.id
    WHERE appointments.patient_id = $patientId
    ORDER BY appointments.date DESC, appointments.time DESC
");

include("../includes/header.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments | OneCare</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            color: #263238;
            background: linear-gradient(135deg, #eef7ff, #f8fbff);
        }

        .wrapper {
            width: min(1100px, 90%);
            margin: 38px auto 60px;
        }

        .hero {
            padding: 38px;
            margin-bottom: 28px;
            border-radius: 26px;
            color: white;
            background: linear-gradient(135deg, #0d47a1, #1976d2, #42a5f5);
            box-shadow: 0 16px 38px rgba(21,101,192,.22);
        }

        .hero span {
            display: inline-block;
            padding: 8px 14px;
            margin-bottom: 12px;
            border-radius: 999px;
            font-weight: bold;
            background: rgba(255,255,255,.18);
        }

        .hero h1 {
            margin: 0;
            font-size: 40px;
        }

        .hero p {
            margin: 12px 0 0;
            font-size: 20px;
            color: rgba(255,255,255,.92);
        }

        .message, .empty {
            padding: 20px;
            margin-bottom: 24px;
            border-radius: 18px;
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            background: white;
            box-shadow: 0 8px 22px rgba(0,0,0,.08);
        }

        .message { color: #1565c0; }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 22px;
        }

        .card {
            position: relative;
            overflow: hidden;
            padding: 28px;
            border-radius: 23px;
            background: white;
            border: 1px solid #e2ecf5;
            box-shadow: 0 10px 28px rgba(21,101,192,.08);
        }

        .card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: #1e88e5;
        }

        .card.completed::before { background: #43a047; }
        .card.cancelled::before { background: #e53935; }
        .card.missed::before { background: #fb8c00; }

        .card h2 {
            margin: 4px 0 18px;
            color: #1565c0;
            font-size: 28px;
        }

        .card p {
            margin: 11px 0;
            color: #455a64;
            font-size: 19px;
        }

        .status {
            display: inline-block;
            padding: 10px 17px;
            margin-top: 10px;
            border-radius: 999px;
            font-size: 18px;
            font-weight: bold;
        }

        .booked {
            color: #8a6200;
            background: #fff8e1;
        }

        .completed {
            color: #2e7d32;
            background: #e8f5e9;
        }

        .cancelled {
            color: #c62828;
            background: #ffebee;
        }

        .missed {
            color: #e65100;
            background: #fff3e0;
        }

        .actions {
            display: grid;
            gap: 12px;
            margin-top: 20px;
        }

        .btn {
            display: block;
            padding: 16px;
            border-radius: 14px;
            text-align: center;
            text-decoration: none;
            color: white;
            font-size: 19px;
            font-weight: bold;
        }

        .cancel-btn { background: #d32f2f; }
        .summary-btn { background: #43a047; }

        @media (max-width: 650px) {
            .wrapper { width: 94%; }
            .hero, .card { padding: 24px; }
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body>

<div class="wrapper">

    <section class="hero">
        <span>OneCare Senior Portal</span>
        <h1>My Appointments</h1>
        <p>View and manage your medical appointments.</p>
    </section>

    <?php if ($message !== ""): ?>
        <div class="message">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="grid">

        <?php if ($result && $result->num_rows > 0): ?>

            <?php while ($row = $result->fetch_assoc()): ?>

                <?php
                $status = $row["status"];
                $appointmentId = (int) $row["id"];
                ?>

                <article class="card <?php echo $status; ?>">

                    <h2>
                        Dr. <?php echo htmlspecialchars($row["doctor_name"]); ?>
                    </h2>

                    <p>
                        <strong>Specialization:</strong>
                        <?php echo htmlspecialchars($row["specialization"]); ?>
                    </p>

                    <p>
                        <strong>Healthcare Provider:</strong>
                        <?php echo htmlspecialchars($row["provider_name"]); ?>
                    </p>

                    <p>
                        <strong>Date:</strong>
                        <?php echo date("F j, Y", strtotime($row["date"])); ?>
                    </p>

                    <p>
                        <strong>Time:</strong>
                        <?php echo date("H:i", strtotime($row["time"])); ?>
                    </p>

                    <span class="status <?php echo $status; ?>">
                        <?php echo ucfirst($status); ?>
                    </span>

                    <div class="actions">

                        <?php if ($status === "booked"): ?>
                            <a
                                class="btn cancel-btn"
                                href="appointments.php?cancel_id=<?php echo $appointmentId; ?>"
                                onclick="return confirm('Cancel this appointment?');"
                            >
                                Cancel Appointment
                            </a>
                        <?php endif; ?>

                        <?php if ($status === "completed"): ?>
                            <a
                                class="btn summary-btn"
                                href="visit_summary.php?appointment_id=<?php echo $appointmentId; ?>"
                            >
                                View Visit Summary
                            </a>
                        <?php endif; ?>

                    </div>

                </article>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="empty">
                You have no appointments.
            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>