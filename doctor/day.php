<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "doctor") {
    header("Location: ../login.php");
    exit();
}

$date = $_GET["date"] ?? "";
$userId = (int) $_SESSION["user_id"];

if (!$date || !strtotime($date)) die("Invalid date.");

$doctorResult = $conn->query("SELECT id FROM doctors WHERE user_id=$userId");
if (!$doctorResult || !$doctorResult->num_rows) die("Doctor record not found.");

$doctorId = (int) $doctorResult->fetch_assoc()["id"];
$dayOfWeek = date("w", strtotime($date));

$scheduleResult = $conn->query("SELECT start_time,end_time FROM doctor_schedule
                               WHERE doctor_id=$doctorId AND day_of_week=$dayOfWeek");
$schedule = $scheduleResult?->fetch_assoc();

$conn->query("UPDATE appointments SET status='missed'
              WHERE doctor_id=$doctorId AND status='booked'
              AND TIMESTAMP(date,time)<NOW()");

$appointments = $conn->query("SELECT a.id,a.time,a.status,u.name patient_name,u.email patient_email
                              FROM appointments a
                              JOIN users u ON a.patient_id=u.id
                              WHERE a.doctor_id=$doctorId AND a.date='$date'
                              ORDER BY a.time");

$count = $appointments ? $appointments->num_rows : 0;
include("../includes/header.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Day Appointments | OneCare</title>

<style>
*{box-sizing:border-box}
body{margin:0;font-family:Arial,sans-serif;color:#263238;background:linear-gradient(135deg,#eef7ff,#f8fbff)}
.wrapper{width:min(1100px,90%);margin:38px auto 60px}
.hero{padding:34px;margin-bottom:24px;border-radius:25px;color:white;background:linear-gradient(135deg,#0d47a1,#1976d2,#42a5f5);box-shadow:0 15px 35px rgba(21,101,192,.22)}
.hero span{display:inline-block;padding:7px 13px;margin-bottom:10px;border-radius:999px;font-weight:bold;background:rgba(255,255,255,.18)}
.hero h1{margin:0;font-size:36px}
.hero p{margin:10px 0 0;font-size:18px}
.schedule,.appointments-box{padding:25px;margin-bottom:22px;border-radius:21px;background:white;border:1px solid #e2ecf5;box-shadow:0 9px 24px rgba(21,101,192,.08)}
.schedule{display:flex;justify-content:space-between;align-items:center;border-left:7px solid #1e88e5}
.schedule h2,.appointments-box h2{margin:0;color:#1565c0}
.schedule p{margin:5px 0 0;font-size:17px;color:#455a64}
.count{padding:10px 16px;border-radius:999px;color:#1565c0;font-weight:bold;background:#e3f2fd}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:18px;margin-top:22px}
.card{padding:22px;border-radius:17px;background:#f8fbff;border:1px solid #dcecff;border-left:6px solid #1e88e5}
.card.completed{border-left-color:#43a047}.card.cancelled{border-left-color:#e53935}.card.missed{border-left-color:#fb8c00}
.card h3{margin:0 0 14px;color:#1565c0;font-size:22px}
.card p{margin:8px 0;color:#455a64}
.status{display:inline-block;padding:8px 13px;margin-top:7px;border-radius:999px;font-weight:bold}
.booked{color:#8a6200;background:#fff8e1}.completed{color:#2e7d32;background:#e8f5e9}
.cancelled{color:#c62828;background:#ffebee}.missed{color:#e65100;background:#fff3e0}
.btn{display:block;margin-top:16px;padding:13px;border-radius:11px;text-align:center;color:white;text-decoration:none;font-weight:bold;background:#1565c0}
.back{display:block;width:max-content;margin:25px auto 0;padding:14px 24px;border-radius:12px;color:white;text-decoration:none;font-weight:bold;background:#607d8b}
.empty{text-align:center;padding:35px;color:#607d8b;font-size:19px}
.off{color:#c62828;font-weight:bold}
@media(max-width:650px){.wrapper{width:94%}.schedule{display:block}.count{display:inline-block;margin-top:15px}.hero{padding:25px}}
</style>
</head>

<body>
<div class="wrapper">

    <section class="hero">
        <span>OneCare Doctor Portal</span>
        <h1><?php echo date("l, F j, Y", strtotime($date)); ?></h1>
        <p>View your working hours and appointments for this day.</p>
    </section>

    <section class="schedule">
        <div>
            <h2>Work Schedule</h2>

            <?php if ($schedule): ?>
                <p>
                    Working hours:
                    <strong><?php echo date("H:i", strtotime($schedule["start_time"])); ?></strong>
                    -
                    <strong><?php echo date("H:i", strtotime($schedule["end_time"])); ?></strong>
                </p>
            <?php else: ?>
                <p class="off">This is not a working day.</p>
            <?php endif; ?>
        </div>

        <div class="count">
            <?php echo $count; ?> Appointment<?php echo $count == 1 ? "" : "s"; ?>
        </div>
    </section>

    <section class="appointments-box">
        <h2>Appointments</h2>

        <?php if ($count > 0): ?>
            <div class="grid">

                <?php while ($row = $appointments->fetch_assoc()): ?>
                    <article class="card <?php echo $row["status"]; ?>">

                        <h3><?php echo htmlspecialchars($row["patient_name"]); ?></h3>

                        <p>
                            <strong>Email:</strong>
                            <?php echo htmlspecialchars($row["patient_email"]); ?>
                        </p>

                        <p>
                            <strong>Time:</strong>
                            <?php echo date("H:i", strtotime($row["time"])); ?>
                        </p>

                        <span class="status <?php echo $row["status"]; ?>">
                            <?php echo ucfirst($row["status"]); ?>
                        </span>

                        <a class="btn" href="appointment_details.php?id=<?php echo $row["id"]; ?>">
                            View Appointment Details
                        </a>

                    </article>
                <?php endwhile; ?>

            </div>
        <?php else: ?>
            <div class="empty">No appointments scheduled for this day.</div>
        <?php endif; ?>
    </section>

    <a class="back" href="calendar.php">Back to Calendar</a>

</div>
</body>
</html>