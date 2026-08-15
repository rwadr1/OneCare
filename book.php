<?php
session_start();
include("config/db.php");

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$doctor_id = (int)($_GET["doctor_id"] ?? $_POST["doctor_id"] ?? 0);

if ($doctor_id <= 0) {
    die("Doctor not selected.");
}
$patient_id = (int) $_SESSION["user_id"];
$message = "";
$message_type = "";
$selected_date = $_GET["date"] ?? $_POST["date"] ?? "";
$available_slots = [];
$alternativeDoctors = null;

/* Doctor details */
$sqlDoctor = "SELECT users.name,
                     doctors.specialization,
                     doctors.profession,
                     doctors.city,
                     providers.name AS provider_name
              FROM doctors
              JOIN users ON doctors.user_id = users.id
              JOIN providers ON doctors.provider_id = providers.id
              WHERE doctors.id = '$doctor_id'";

$resultDoctor = $conn->query($sqlDoctor);

if (!$resultDoctor || $resultDoctor->num_rows == 0) {
    die("Doctor not found.");
}

$doctor = $resultDoctor->fetch_assoc();

/* Load available time slots.
   A specific-date exception overrides the regular weekly schedule. */
function loadAvailableSlots($conn, $doctor_id, $selected_date)
{
    if (empty($selected_date) || $selected_date < date("Y-m-d")) {
        return [];
    }

    $exceptionStmt = $conn->prepare(
        "SELECT is_working, start_time, end_time
         FROM doctor_schedule_exceptions
         WHERE doctor_id = ? AND work_date = ?
         LIMIT 1"
    );

    $exceptionStmt->bind_param("is", $doctor_id, $selected_date);
    $exceptionStmt->execute();
    $exception = $exceptionStmt->get_result()->fetch_assoc();

    if ($exception) {
        if ((int)$exception["is_working"] === 0) {
            return [];
        }

        $startTime = $exception["start_time"];
        $endTime = $exception["end_time"];
    } else {
        $day_of_week = (int)date("w", strtotime($selected_date));

        $scheduleStmt = $conn->prepare(
            "SELECT start_time, end_time
             FROM doctor_schedule
             WHERE doctor_id = ? AND day_of_week = ?
             LIMIT 1"
        );

        $scheduleStmt->bind_param("ii", $doctor_id, $day_of_week);
        $scheduleStmt->execute();
        $schedule = $scheduleStmt->get_result()->fetch_assoc();

        if (!$schedule) {
            return [];
        }

        $startTime = $schedule["start_time"];
        $endTime = $schedule["end_time"];
    }

    $bookedStmt = $conn->prepare(
        "SELECT time
         FROM appointments
         WHERE doctor_id = ?
           AND date = ?
           AND status = 'booked'"
    );

    $bookedStmt->bind_param("is", $doctor_id, $selected_date);
    $bookedStmt->execute();
    $bookedResult = $bookedStmt->get_result();
    $bookedTimes = [];

    while ($row = $bookedResult->fetch_assoc()) {
        $bookedTimes[$row["time"]] = true;
    }

    $slots = [];
    $start = strtotime($selected_date . " " . $startTime);
    $end = strtotime($selected_date . " " . $endTime);

    while ($start < $end) {
        $slot = date("H:i:s", $start);

        if (
            !(
                $selected_date === date("Y-m-d") &&
                $slot <= date("H:i:s")
            ) &&
            !isset($bookedTimes[$slot])
        ) {
            $slots[] = $slot;
        }

        $start = strtotime("+30 minutes", $start);
    }

    return $slots;
}

/* Alternative doctors with same specialization and profession */
function loadAlternativeDoctors(
    $conn,
    $doctor_id,
    $specialization,
    $profession
) {
    $specialization = $conn->real_escape_string($specialization);
    $profession = $conn->real_escape_string($profession);

    $sql = "SELECT doctors.id,
                   users.name,
                   doctors.specialization,
                   doctors.profession,
                   doctors.city,
                   providers.name AS provider_name
            FROM doctors
            JOIN users ON doctors.user_id = users.id
            JOIN providers ON doctors.provider_id = providers.id
            WHERE doctors.id != '$doctor_id'
            AND doctors.specialization = '$specialization'
            AND doctors.profession = '$profession'
            ORDER BY users.name ASC
            LIMIT 3";

    return $conn->query($sql);
}

/* Load selected date */
if (!empty($selected_date)) {
    if ($selected_date < date("Y-m-d")) {
        $message = "You cannot select a past date.";
        $message_type = "error";
    } else {
        $available_slots = loadAvailableSlots(
            $conn,
            $doctor_id,
            $selected_date
        );

        if (empty($available_slots)) {
            $message = "No available time slots were found for this date.";
            $message_type = "error";

            $alternativeDoctors = loadAlternativeDoctors(
                $conn,
                $doctor_id,
                $doctor["specialization"],
                $doctor["profession"]
            );
        }
    }
}
/* Save appointment */
if (isset($_POST["confirm_appointment"])) {
    $selected_date = $_POST["date"] ?? "";
    $selected_time = $_POST["time"] ?? "";

    if (empty($selected_time)) {
        $message = "Please select an appointment time.";
        $message_type = "error";

    } elseif (
        $selected_date < date("Y-m-d") ||
        (
            $selected_date === date("Y-m-d") &&
            $selected_time <= date("H:i:s")
        )
    ) {
        $message = "You cannot book an appointment in the past.";
        $message_type = "error";

    } else {
        // Recheck the final schedule, date exception and booked times.
        $available_slots = loadAvailableSlots(
            $conn,
            $doctor_id,
            $selected_date
        );

        if (!in_array($selected_time, $available_slots, true)) {
            $message = "This appointment time is no longer available.";
            $message_type = "error";

        } else {
            $insertStmt = $conn->prepare(
                "INSERT INTO appointments
                 (patient_id, doctor_id, date, time, status)
                 VALUES (?, ?, ?, ?, 'booked')"
            );

            $insertStmt->bind_param(
                "iiss",
                $patient_id,
                $doctor_id,
                $selected_date,
                $selected_time
            );

            if ($insertStmt->execute()) {
                $message = "Appointment booked successfully.";
                $message_type = "success";
                $available_slots = [];

            } elseif ($insertStmt->errno === 1062) {
                $message = "This appointment time was just booked by another user. Please select another time.";
                $message_type = "error";

                $available_slots = loadAvailableSlots(
                    $conn,
                    $doctor_id,
                    $selected_date
                );

            } else {
                $message = "Error booking appointment.";
                $message_type = "error";
            }
        }
    }
}

include("includes/header.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Book Appointment</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            color: #263238;
            background: linear-gradient(135deg, #eef7ff, #f8fbff);
        }

        .page-wrapper {
            width: 90%;
            max-width: 1100px;
            margin: 35px auto 60px;
        }

        .hero {
            padding: 32px;
            margin-bottom: 25px;
            border-radius: 24px;
            color: white;
            background: linear-gradient(135deg, #1565c0, #42a5f5);
            box-shadow: 0 12px 28px rgba(21, 101, 192, 0.22);
        }

        .hero h1 {
            margin: 0 0 8px;
            font-size: 34px;
        }

        .hero p {
            margin: 0;
            color: #e3f2fd;
        }

        .booking-layout {
            display: grid;
            grid-template-columns: 1fr 1.4fr;
            gap: 24px;
            align-items: start;
        }

        .card {
            background: white;
            padding: 26px;
            border-radius: 22px;
            border: 1px solid #e3edf7;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        }

        .doctor-card h2,
        .booking-card h2 {
            margin-top: 0;
            color: #1565c0;
        }

        .doctor-name {
            margin-bottom: 20px;
            padding-bottom: 18px;
            border-bottom: 1px solid #e8eef4;
        }

        .doctor-name h3 {
            margin: 0 0 6px;
            font-size: 24px;
            color: #16324f;
        }

        .doctor-name p {
            margin: 0;
            color: #607d8b;
        }

        .doctor-detail {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid #eef2f6;
        }

        .doctor-detail strong {
            color: #455a64;
        }

        .doctor-detail span {
            color: #607d8b;
            text-align: right;
        }

        .message {
            padding: 14px;
            margin-bottom: 20px;
            border-radius: 12px;
            text-align: center;
            font-weight: bold;
        }

        .success {
            color: #2e7d32;
            background: #e8f5e9;
            border: 1px solid #c8e6c9;
        }

        .error {
            color: #c62828;
            background: #ffebee;
            border: 1px solid #ffcdd2;
        }

        .date-box {
            padding: 18px;
            margin-bottom: 22px;
            border-radius: 15px;
            background: #f2f8ff;
            border-left: 5px solid #1e88e5;
        }

        .date-box span {
            display: block;
            margin-bottom: 5px;
            color: #78909c;
            font-size: 13px;
        }

        .date-box strong {
            color: #263238;
            font-size: 17px;
        }

        .section-label {
            display: block;
            margin-bottom: 12px;
            color: #455a64;
            font-weight: bold;
        }

        .time-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 22px;
        }

        .time-option input {
            display: none;
        }

        .time-option label {
            display: block;
            padding: 12px 8px;
            text-align: center;
            border-radius: 11px;
            border: 1px solid #cfd8dc;
            color: #455a64;
            background: white;
            cursor: pointer;
            font-weight: bold;
            transition: 0.2s;
        }

        .time-option label:hover {
            border-color: #1976d2;
            color: #1565c0;
            background: #f1f8ff;
        }

        .time-option input:checked + label {
            color: white;
            background: #1565c0;
            border-color: #1565c0;
        }

        .confirm-btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            color: white;
            background: #1565c0;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .confirm-btn:hover {
            background: #0d47a1;
        }

        .back-link {
            display: block;
            margin-top: 18px;
            text-align: center;
            color: #1565c0;
            text-decoration: none;
            font-weight: bold;
        }

        .success-actions {
            display: flex;
            gap: 12px;
            margin-top: 18px;
        }

        .success-actions a {
            flex: 1;
            padding: 12px;
            border-radius: 11px;
            text-align: center;
            text-decoration: none;
            font-weight: bold;
        }

        .appointments-btn {
            color: white;
            background: #43a047;
        }

        .calendar-btn {
            color: #1565c0;
            background: #e3f2fd;
        }

        .alternative-box {
            margin-top: 25px;
            padding-top: 22px;
            border-top: 1px solid #e3edf7;
        }

        .alternative-box h3 {
            margin: 0 0 7px;
            color: #1565c0;
        }

        .alternative-box > p {
            color: #78909c;
        }

        .alternative-card {
            padding: 16px;
            margin-top: 12px;
            border-radius: 14px;
            background: #f8fbff;
            border: 1px solid #dbeafe;
        }

        .alternative-card h4 {
            margin: 0 0 9px;
            color: #16324f;
        }

        .alternative-card p {
            margin: 5px 0;
            color: #607d8b;
            font-size: 14px;
        }

        .alternative-btn {
            display: inline-block;
            margin-top: 10px;
            padding: 10px 14px;
            border-radius: 9px;
            color: white;
            background: #43a047;
            text-decoration: none;
            font-weight: bold;
        }

        @media (max-width: 850px) {
            .booking-layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 600px) {
            .page-wrapper {
                width: 94%;
            }

            .time-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .success-actions {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<div class="page-wrapper">

    <div class="hero">
        <h1>Book an Appointment</h1>
        <p>Select an available time and confirm your appointment.</p>
    </div>

    <div class="booking-layout">

        <div class="card doctor-card">

            <h2>Doctor Information</h2>

            <div class="doctor-name">
                <h3>
                    Dr. <?php echo htmlspecialchars($doctor["name"]); ?>
                </h3>

                <p>
                    <?php echo htmlspecialchars($doctor["specialization"]); ?>
                </p>
            </div>

            <div class="doctor-detail">
                <strong>Profession</strong>

                <span>
                    <?php echo htmlspecialchars($doctor["profession"]); ?>
                </span>
            </div>

            <div class="doctor-detail">
                <strong>City</strong>

                <span>
                    <?php echo htmlspecialchars($doctor["city"]); ?>
                </span>
            </div>

            <div class="doctor-detail">
                <strong>Provider</strong>

                <span>
                    <?php echo htmlspecialchars($doctor["provider_name"]); ?>
                </span>
            </div>

        </div>

        <div class="card booking-card">

            <h2>Appointment Details</h2>

            <?php if ($message != ""): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($selected_date)): ?>

                <div class="date-box">
                    <span>Selected Date</span>

                    <strong>
                        <?php
                        echo date(
                            "l, F j, Y",
                            strtotime($selected_date)
                        );
                        ?>
                    </strong>
                </div>

            <?php endif; ?>

            <?php if (!empty($available_slots)): ?>

                <form method="post">

                    <input
                        type="hidden"
                        name="doctor_id"
                        value="<?php echo $doctor_id; ?>"
                    >

                    <input
                        type="hidden"
                        name="date"
                        value="<?php echo htmlspecialchars($selected_date); ?>"
                    >

                    <span class="section-label">
                        Select an Available Time
                    </span>

                    <div class="time-grid">

                        <?php foreach ($available_slots as $index => $slot): ?>

                            <div class="time-option">

                                <input
                                    type="radio"
                                    name="time"
                                    id="time-<?php echo $index; ?>"
                                    value="<?php echo htmlspecialchars($slot); ?>"
                                >

                                <label for="time-<?php echo $index; ?>">
                                    <?php echo date("H:i", strtotime($slot)); ?>
                                </label>

                            </div>

                        <?php endforeach; ?>

                    </div>

                    <button
                        type="submit"
                        name="confirm_appointment"
                        class="confirm-btn"
                    >
                        Confirm Appointment
                    </button>

                </form>

            <?php endif; ?>

            <?php if ($message_type === "success"): ?>

                <div class="success-actions">

                    <a
                        href="patient/appointments.php"
                        class="appointments-btn"
                    >
                        My Appointments
                    </a>

                    <a
                        href="patient/doctor_calendar.php?doctor_id=<?php echo $doctor_id; ?>"
                        class="calendar-btn"
                    >
                        Back to Calendar
                    </a>

                </div>

            <?php else: ?>

                <a
                    class="back-link"
                    href="patient/doctor_calendar.php?doctor_id=<?php echo $doctor_id; ?>"
                >
                    Back to Doctor Calendar
                </a>

            <?php endif; ?>

            <?php
            if (
                $alternativeDoctors &&
                $alternativeDoctors->num_rows > 0
            ):
            ?>

                <div class="alternative-box">

                    <h3>Alternative Doctors</h3>

                    <p>
                        Doctors with the same specialization and profession.
                    </p>

                    <?php while ($alt = $alternativeDoctors->fetch_assoc()): ?>

                        <div class="alternative-card">

                            <h4>
                                Dr. <?php echo htmlspecialchars($alt["name"]); ?>
                            </h4>

                            <p>
                                <strong>Specialization:</strong>
                                <?php echo htmlspecialchars($alt["specialization"]); ?>
                            </p>

                            <p>
                                <strong>Profession:</strong>
                                <?php echo htmlspecialchars($alt["profession"]); ?>
                            </p>

                            <p>
                                <strong>City:</strong>
                                <?php echo htmlspecialchars($alt["city"]); ?>
                            </p>

                            <p>
                                <strong>Provider:</strong>
                                <?php echo htmlspecialchars($alt["provider_name"]); ?>
                            </p>

                            <a
                                class="alternative-btn"
                                href="patient/doctor_calendar.php?doctor_id=<?php echo $alt["id"]; ?>"
                            >
                                View Available Times
                            </a>

                        </div>

                    <?php endwhile; ?>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

</body>
</html>