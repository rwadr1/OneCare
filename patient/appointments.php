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

$patient_id = (int) $_SESSION["user_id"];
$message = "";
$message_type = "";

/*
|--------------------------------------------------------------------------
| Automatically Mark Past Appointments as Missed
|--------------------------------------------------------------------------
*/
$missedStmt = $conn->prepare("
    UPDATE appointments
    SET status = 'missed'
    WHERE patient_id = ?
      AND status = 'booked'
      AND TIMESTAMP(date, time) < NOW()
");

$missedStmt->bind_param("i", $patient_id);
$missedStmt->execute();
$missedStmt->close();

/*
|--------------------------------------------------------------------------
| Cancel Appointment
|--------------------------------------------------------------------------
*/
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["cancel_appointment"])
) {
    $appointment_id = isset($_POST["appointment_id"])
        ? (int) $_POST["appointment_id"]
        : 0;

    if ($appointment_id <= 0) {
        $message = "Invalid appointment.";
        $message_type = "error";
    } else {
        $cancelStmt = $conn->prepare("
            UPDATE appointments
            SET status = 'cancelled'
            WHERE id = ?
              AND patient_id = ?
              AND status = 'booked'
              AND TIMESTAMP(date, time) > NOW()
        ");

        $cancelStmt->bind_param(
            "ii",
            $appointment_id,
            $patient_id
        );

        if ($cancelStmt->execute()) {
            if ($cancelStmt->affected_rows > 0) {
                $message = "Appointment cancelled successfully.";
                $message_type = "success";
            } else {
                $message = "This appointment cannot be cancelled.";
                $message_type = "error";
            }
        } else {
            $message = "An error occurred while cancelling the appointment.";
            $message_type = "error";
        }

        $cancelStmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| Get Patient Appointments
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT
        appointments.id,
        appointments.date,
        appointments.time,
        appointments.status,

        users.name AS doctor_name,

        doctors.specialization,
        doctors.profession,
        doctors.city,

        providers.name AS provider_name,
        providers.type AS provider_type

    FROM appointments

    JOIN doctors
        ON appointments.doctor_id = doctors.id

    JOIN users
        ON doctors.user_id = users.id

    JOIN providers
        ON doctors.provider_id = providers.id

    WHERE appointments.patient_id = ?

    ORDER BY
        CASE
            WHEN appointments.status = 'booked'
                 AND TIMESTAMP(
                     appointments.date,
                     appointments.time
                 ) >= NOW()
            THEN 0
            ELSE 1
        END,
        appointments.date ASC,
        appointments.time ASC
");

$stmt->bind_param("i", $patient_id);
$stmt->execute();

$result = $stmt->get_result();

$upcomingAppointments = [];
$previousAppointments = [];

$totalAppointments = 0;
$bookedCount = 0;
$completedCount = 0;
$cancelledCount = 0;
$missedCount = 0;

while ($row = $result->fetch_assoc()) {
    $totalAppointments++;

    if ($row["status"] === "booked") {
        $bookedCount++;
    } elseif ($row["status"] === "completed") {
        $completedCount++;
    } elseif ($row["status"] === "cancelled") {
        $cancelledCount++;
    } elseif ($row["status"] === "missed") {
        $missedCount++;
    }

    $appointmentDateTime = strtotime(
        $row["date"] . " " . $row["time"]
    );

    if (
        $row["status"] === "booked" &&
        $appointmentDateTime >= time()
    ) {
        $upcomingAppointments[] = $row;
    } else {
        $previousAppointments[] = $row;
    }
}

$stmt->close();

include("../includes/header.php");

/*
|--------------------------------------------------------------------------
| Appointment Card Function
|--------------------------------------------------------------------------
*/
function displayAppointmentCard($appointment)
{
    $appointmentId = (int) $appointment["id"];

    $doctorName = htmlspecialchars(
        $appointment["doctor_name"] ?? "Unknown Doctor"
    );

    $specialization = htmlspecialchars(
        $appointment["specialization"] ?? "Not specified"
    );

    $profession = htmlspecialchars(
        $appointment["profession"] ?? "Not specified"
    );

    $city = htmlspecialchars(
        $appointment["city"] ?? "Not specified"
    );

    $providerName = htmlspecialchars(
        $appointment["provider_name"] ?? "Not specified"
    );

    $providerType = htmlspecialchars(
        $appointment["provider_type"] ?? ""
    );

    $status = strtolower(
        $appointment["status"] ?? "booked"
    );

    $statusText = ucfirst($status);

    $formattedDate = date(
        "l, F j, Y",
        strtotime($appointment["date"])
    );

    $formattedTime = date(
        "H:i",
        strtotime($appointment["time"])
    );

    $statusClass = "status-booked";
    $cardClass = "booked-card";

    if ($status === "completed") {
        $statusClass = "status-completed";
        $cardClass = "completed-card";
    } elseif ($status === "cancelled") {
        $statusClass = "status-cancelled";
        $cardClass = "cancelled-card";
    } elseif ($status === "missed") {
        $statusClass = "status-missed";
        $cardClass = "missed-card";
    }
    ?>

    <article class="appointment-card <?php echo $cardClass; ?>">

        <div class="appointment-top">

            <div class="date-box">
                <span class="date-month">
                    <?php echo date("M", strtotime($appointment["date"])); ?>
                </span>

                <span class="date-day">
                    <?php echo date("d", strtotime($appointment["date"])); ?>
                </span>
            </div>

            <div class="doctor-heading">
                <span class="appointment-label">
                    Medical Appointment
                </span>

                <h3>
                    Dr. <?php echo $doctorName; ?>
                </h3>

                <p>
                    <?php echo $specialization; ?>
                </p>
            </div>

            <span class="status <?php echo $statusClass; ?>">
                <?php echo $statusText; ?>
            </span>

        </div>

        <div class="appointment-details">

            <div class="detail-item">
                <div class="detail-icon">📅</div>

                <div>
                    <span class="detail-label">
                        Appointment Date
                    </span>

                    <strong>
                        <?php echo $formattedDate; ?>
                    </strong>
                </div>
            </div>

            <div class="detail-item">
                <div class="detail-icon">🕐</div>

                <div>
                    <span class="detail-label">
                        Appointment Time
                    </span>

                    <strong>
                        <?php echo $formattedTime; ?>
                    </strong>
                </div>
            </div>

            <div class="detail-item">
                <div class="detail-icon">🩺</div>

                <div>
                    <span class="detail-label">
                        Profession
                    </span>

                    <strong>
                        <?php echo $profession; ?>
                    </strong>
                </div>
            </div>

            <div class="detail-item">
                <div class="detail-icon">📍</div>

                <div>
                    <span class="detail-label">
                        City
                    </span>

                    <strong>
                        <?php echo $city; ?>
                    </strong>
                </div>
            </div>

            <div class="detail-item">
                <div class="detail-icon">🏥</div>

                <div>
                    <span class="detail-label">
                        Healthcare Provider
                    </span>

                    <strong>
                        <?php echo $providerName; ?>

                        <?php if ($providerType !== ""): ?>
                            <small>
                                (<?php echo $providerType; ?>)
                            </small>
                        <?php endif; ?>
                    </strong>
                </div>
            </div>

        </div>

        <?php if ($status === "missed"): ?>

            <div class="missed-message">
                <strong>Appointment Missed</strong>

                <span>
                    This appointment passed without being completed.
                </span>
            </div>

        <?php endif; ?>

        <div class="appointment-footer">

            <span class="appointment-number">
                Appointment #<?php echo $appointmentId; ?>
            </span>

            <div class="actions">

                <?php if ($status === "booked"): ?>

                    <form
                        method="post"
                        class="cancel-form"
                        onsubmit="return confirm(
                            'Are you sure you want to cancel this appointment?'
                        );"
                    >
                        <input
                            type="hidden"
                            name="appointment_id"
                            value="<?php echo $appointmentId; ?>"
                        >

                        <button
                            type="submit"
                            name="cancel_appointment"
                            class="btn cancel-btn"
                        >
                            Cancel Appointment
                        </button>
                    </form>

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

        </div>

    </article>

    <?php
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>My Appointments | OneCare</title>

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
            margin-bottom: 26px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 30px;
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

        .hero-content,
        .hero-actions {
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

        .hero-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
            min-width: 190px;
        }

        .hero-btn {
            display: block;
            text-align: center;
            padding: 13px 20px;
            border-radius: 13px;
            text-decoration: none;
            font-weight: bold;
            transition: 0.25s ease;
        }

        .primary-hero-btn {
            background: white;
            color: #1565c0;
        }

        .secondary-hero-btn {
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.55);
            background: rgba(255, 255, 255, 0.10);
        }

        .hero-btn:hover {
            transform: translateY(-2px);
        }

        .message {
            padding: 16px 20px;
            border-radius: 14px;
            text-align: center;
            font-weight: bold;
            margin-bottom: 24px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
        }

        .message-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .message-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        .statistics-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
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
            transition: 0.25s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 13px 30px rgba(21, 101, 192, 0.12);
        }

        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        .total-icon {
            background: #e3f2fd;
        }

        .booked-icon {
            background: #fff8e1;
        }

        .completed-icon {
            background: #e8f5e9;
        }

        .cancelled-icon {
            background: #ffebee;
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

        .appointments-section {
            margin-top: 34px;
        }

        .section-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 18px;
        }

        .section-heading h2 {
            margin: 0;
            color: #16324f;
            font-size: 25px;
        }

        .section-heading p {
            color: #78909c;
            margin: 6px 0 0;
            font-size: 14px;
        }

        .appointment-count {
            background: #e3f2fd;
            color: #1565c0;
            padding: 8px 13px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: bold;
            white-space: nowrap;
        }

        .appointments-grid {
            display: grid;
            grid-template-columns: repeat(
                auto-fit,
                minmax(410px, 1fr)
            );
            gap: 23px;
        }

        .appointment-card {
            position: relative;
            overflow: hidden;
            background: white;
            border-radius: 23px;
            padding: 25px;
            border: 1px solid #e2ecf5;
            box-shadow: 0 10px 28px rgba(21, 101, 192, 0.08);
            transition: 0.25s ease;
        }

        .appointment-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 36px rgba(21, 101, 192, 0.13);
        }

        .appointment-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            height: 6px;
            width: 100%;
        }

        .booked-card::before {
            background: linear-gradient(90deg, #1e88e5, #64b5f6);
        }

        .completed-card::before {
            background: linear-gradient(90deg, #43a047, #81c784);
        }

        .cancelled-card::before {
            background: linear-gradient(90deg, #e53935, #ef9a9a);
        }

        .missed-card::before {
            background: linear-gradient(90deg, #fb8c00, #ffcc80);
        }

        .cancelled-card {
            opacity: 0.82;
        }

        .missed-card {
            background: #fffdf8;
        }

        .appointment-top {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding-top: 5px;
        }

        .date-box {
            width: 66px;
            min-width: 66px;
            height: 70px;
            border-radius: 17px;
            background: #e3f2fd;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .date-month {
            color: #1976d2;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .date-day {
            color: #0d47a1;
            font-size: 26px;
            font-weight: bold;
            line-height: 1.1;
        }

        .doctor-heading {
            flex: 1;
            min-width: 0;
        }

        .appointment-label {
            color: #90a4ae;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .doctor-heading h3 {
            color: #1565c0;
            font-size: 21px;
            margin: 6px 0 5px;
        }

        .doctor-heading p {
            color: #607d8b;
            margin: 0;
            font-size: 14px;
        }

        .status {
            display: inline-block;
            padding: 8px 13px;
            border-radius: 999px;
            font-weight: bold;
            font-size: 12px;
            white-space: nowrap;
        }

        .status-booked {
            background: #fff8e1;
            color: #8a6200;
        }

        .status-completed {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-cancelled {
            background: #ffebee;
            color: #c62828;
        }

        .status-missed {
            background: #fff3e0;
            color: #e65100;
        }

        .appointment-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 23px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 13px;
            background: #f8fbfe;
            border-radius: 14px;
            border: 1px solid #edf3f8;
        }

        .detail-item:last-child {
            grid-column: 1 / -1;
        }

        .detail-icon {
            width: 37px;
            height: 37px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            box-shadow: 0 3px 9px rgba(0, 0, 0, 0.06);
            flex-shrink: 0;
        }

        .detail-label {
            display: block;
            color: #90a4ae;
            font-size: 11px;
            margin-bottom: 4px;
        }

        .detail-item strong {
            display: block;
            color: #37474f;
            font-size: 13px;
            line-height: 1.4;
        }

        .detail-item small {
            color: #78909c;
            font-weight: normal;
        }

        .missed-message {
            margin-top: 18px;
            padding: 14px 16px;
            border-radius: 13px;
            background: #fff3e0;
            border: 1px solid #ffe0b2;
            color: #e65100;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .missed-message strong {
            font-size: 14px;
        }

        .missed-message span {
            color: #8d6e63;
            font-size: 12px;
        }

        .appointment-footer {
            border-top: 1px solid #edf2f7;
            margin-top: 21px;
            padding-top: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .appointment-number {
            color: #90a4ae;
            font-size: 12px;
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .cancel-form {
            margin: 0;
        }

        .btn {
            display: inline-block;
            padding: 11px 16px;
            border: none;
            border-radius: 11px;
            color: white;
            text-decoration: none;
            font-weight: bold;
            font-size: 13px;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .cancel-btn {
            background: #d32f2f;
        }

        .cancel-btn:hover {
            background: #b71c1c;
        }

        .summary-btn {
            background: #43a047;
        }

        .summary-btn:hover {
            background: #2e7d32;
        }

        .empty-state {
            background: white;
            padding: 55px 30px;
            border-radius: 24px;
            text-align: center;
            border: 1px solid #e3edf7;
            box-shadow: 0 10px 28px rgba(21, 101, 192, 0.08);
            grid-column: 1 / -1;
        }

        .empty-icon {
            width: 84px;
            height: 84px;
            margin: 0 auto 18px;
            border-radius: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e3f2fd;
            font-size: 38px;
        }

        .empty-state h3 {
            color: #16324f;
            font-size: 23px;
            margin: 0 0 10px;
        }

        .empty-state p {
            color: #78909c;
            margin: 0 auto 22px;
            max-width: 480px;
            line-height: 1.7;
        }

        .find-doctor-btn {
            display: inline-block;
            background: #1565c0;
            color: white;
            text-decoration: none;
            padding: 13px 22px;
            border-radius: 12px;
            font-weight: bold;
        }

        @media (max-width: 1100px) {
            .statistics-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 950px) {
            .statistics-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .hero {
                align-items: flex-start;
            }
        }

        @media (max-width: 760px) {
            .page-wrapper {
                width: 94%;
                margin-top: 24px;
            }

            .hero {
                flex-direction: column;
                padding: 28px 23px;
            }

            .hero-actions {
                width: 100%;
            }

            .appointments-grid {
                grid-template-columns: 1fr;
            }

            .appointment-top {
                flex-wrap: wrap;
            }

            .status {
                margin-left: 82px;
            }
        }

        @media (max-width: 520px) {
            .statistics-grid {
                grid-template-columns: 1fr;
            }

            .appointment-details {
                grid-template-columns: 1fr;
            }

            .detail-item:last-child {
                grid-column: auto;
            }

            .appointment-footer {
                flex-direction: column;
                align-items: stretch;
            }

            .actions,
            .cancel-form,
            .btn {
                width: 100%;
            }

            .btn {
                text-align: center;
            }

            .status {
                margin-left: 0;
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

            <h1>My Appointments</h1>

            <p>
                Manage your upcoming medical appointments,
                review previous visits, and access your completed
                visit summaries in one place.
            </p>
        </div>

        <div class="hero-actions">
            <a
                class="hero-btn primary-hero-btn"
                href="/ONECARE/patient/search.php"
            >
                Find a Doctor
            </a>

      

    </section>

    <?php if ($message !== ""): ?>

        <div class="message message-<?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>

    <section class="statistics-grid">

        <div class="stat-card">
            <div class="stat-icon total-icon">📋</div>

            <div>
                <strong class="stat-number">
                    <?php echo $totalAppointments; ?>
                </strong>

                <span class="stat-label">
                    Total Appointments
                </span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon booked-icon">📅</div>

            <div>
                <strong class="stat-number">
                    <?php echo $bookedCount; ?>
                </strong>

                <span class="stat-label">
                    Booked
                </span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon completed-icon">✓</div>

            <div>
                <strong class="stat-number">
                    <?php echo $completedCount; ?>
                </strong>

                <span class="stat-label">
                    Completed
                </span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon cancelled-icon">×</div>

            <div>
                <strong class="stat-number">
                    <?php echo $cancelledCount; ?>
                </strong>

                <span class="stat-label">
                    Cancelled
                </span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon missed-icon">!</div>

            <div>
                <strong class="stat-number">
                    <?php echo $missedCount; ?>
                </strong>

                <span class="stat-label">
                    Missed
                </span>
            </div>
        </div>

    </section>

    <section class="appointments-section">

        <div class="section-heading">

            <div>
                <h2>Upcoming Appointments</h2>

                <p>
                    Your scheduled and active medical visits.
                </p>
            </div>

            <span class="appointment-count">
                <?php echo count($upcomingAppointments); ?>
                Upcoming
            </span>

        </div>

        <div class="appointments-grid">

            <?php if (count($upcomingAppointments) > 0): ?>

                <?php foreach ($upcomingAppointments as $appointment): ?>
                    <?php displayAppointmentCard($appointment); ?>
                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty-state">

                    <div class="empty-icon">📅</div>

                    <h3>No Upcoming Appointments</h3>

                    <p>
                        You currently have no upcoming appointments.
                        Search for a doctor and choose a suitable date
                        and time for your next medical visit.
                    </p>

                    <a
                        class="find-doctor-btn"
                        href="search_doctor.php"
                    >
                        Find a Doctor
                    </a>

                </div>

            <?php endif; ?>

        </div>

    </section>

    <?php if (count($previousAppointments) > 0): ?>

        <section class="appointments-section">

            <div class="section-heading">

                <div>
                    <h2>Appointment History</h2>

                    <p>
                        Completed, cancelled, missed, and previous appointments.
                    </p>
                </div>

                <span class="appointment-count">
                    <?php echo count($previousAppointments); ?>
                    Previous
                </span>

            </div>

            <div class="appointments-grid">

                <?php foreach ($previousAppointments as $appointment): ?>
                    <?php displayAppointmentCard($appointment); ?>
                <?php endforeach; ?>

            </div>

        </section>

    <?php endif; ?>

</div>

</body>
</html>