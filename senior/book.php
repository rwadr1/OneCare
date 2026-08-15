<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "senior") {
    header("Location: ../login.php");
    exit();
}

$doctorId = (int)($_GET["doctor_id"] ?? $_POST["doctor_id"] ?? 0);
$patientId = (int)$_SESSION["user_id"];

if ($doctorId <= 0) {
    die("Doctor was not selected.");
}

$message = "";
$messageType = "error";
$selectedDate = $_GET["date"] ?? $_POST["date"] ?? "";
$availableSlots = [];

$stmt = $conn->prepare(
    "SELECT u.name, d.specialization, d.profession, d.city,
            p.name AS provider_name
     FROM doctors d
     JOIN users u ON d.user_id = u.id
     JOIN providers p ON d.provider_id = p.id
     WHERE d.id = ?"
);

$stmt->bind_param("i", $doctorId);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();

if (!$doctor) {
    die("Doctor was not found.");
}

/* Get the correct working hours for one specific date.
   A date exception always overrides the regular weekly schedule. */
function getWorkingHoursForDate(
    mysqli $conn,
    int $doctorId,
    string $date
): ?array {
    $stmt = $conn->prepare(
        "SELECT is_working, start_time, end_time
         FROM doctor_schedule_exceptions
         WHERE doctor_id = ? AND work_date = ?
         LIMIT 1"
    );

    $stmt->bind_param("is", $doctorId, $date);
    $stmt->execute();
    $exception = $stmt->get_result()->fetch_assoc();

    if ($exception) {
        if ((int)$exception["is_working"] === 0) {
            return null;
        }

        return [
            "start_time" => $exception["start_time"],
            "end_time" => $exception["end_time"]
        ];
    }

    $dayOfWeek = (int)date("w", strtotime($date));

    $stmt = $conn->prepare(
        "SELECT start_time, end_time
         FROM doctor_schedule
         WHERE doctor_id = ? AND day_of_week = ?
         LIMIT 1"
    );

    $stmt->bind_param("ii", $doctorId, $dayOfWeek);
    $stmt->execute();
    $schedule = $stmt->get_result()->fetch_assoc();

    if (!$schedule) {
        return null;
    }

    return [
        "start_time" => $schedule["start_time"],
        "end_time" => $schedule["end_time"]
    ];
}

/* Create available times for selected date */
function getAvailableSlots(
    mysqli $conn,
    int $doctorId,
    string $date
): array {
    if ($date === "" || $date < date("Y-m-d")) {
        return [];
    }

    $workingHours = getWorkingHoursForDate($conn, $doctorId, $date);

    if (!$workingHours) {
        return [];
    }

    $stmt = $conn->prepare(
        "SELECT time
         FROM appointments
         WHERE doctor_id = ?
           AND date = ?
           AND status = 'booked'"
    );

    $stmt->bind_param("is", $doctorId, $date);
    $stmt->execute();
    $bookedResult = $stmt->get_result();
    $bookedTimes = [];

    while ($row = $bookedResult->fetch_assoc()) {
        $bookedTimes[$row["time"]] = true;
    }

    $slots = [];
    $start = strtotime($date . " " . $workingHours["start_time"]);
    $end = strtotime($date . " " . $workingHours["end_time"]);

    while ($start < $end) {
        $slot = date("H:i:s", $start);

        if (
            !($date === date("Y-m-d") && $slot <= date("H:i:s")) &&
            !isset($bookedTimes[$slot])
        ) {
            $slots[] = $slot;
        }

        $start = strtotime("+30 minutes", $start);
    }

    return $slots;
}

/* Confirm appointment */
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["confirm_appointment"])
) {
    $selectedDate = $_POST["date"] ?? "";
    $selectedTime = $_POST["time"] ?? "";

    if (
        $selectedDate < date("Y-m-d") ||
        (
            $selectedDate === date("Y-m-d") &&
            $selectedTime <= date("H:i:s")
        )
    ) {
        $message = "You cannot book an appointment in the past.";
    } else {
        $availableSlots = getAvailableSlots(
            $conn,
            $doctorId,
            $selectedDate
        );

        if (!in_array($selectedTime, $availableSlots, true)) {
            $message = "This appointment time is no longer available.";
        } else {
            $insert = $conn->prepare(
                "INSERT INTO appointments
                 (patient_id, doctor_id, date, time, status)
                 VALUES (?, ?, ?, ?, 'booked')"
            );

            $insert->bind_param(
                "iiss",
                $patientId,
                $doctorId,
                $selectedDate,
                $selectedTime
            );

            if ($insert->execute()) {
                $message = "Appointment booked successfully!";
                $messageType = "success";
                $availableSlots = [];

            } elseif ($insert->errno === 1062) {
                $message = "This appointment time was just booked by another user. Please select another time.";

                $availableSlots = getAvailableSlots(
                    $conn,
                    $doctorId,
                    $selectedDate
                );

            } else {
                $message = "The appointment could not be booked.";
            }
        }
    }
}

/* Load times when a date is selected */
if (
    $selectedDate !== "" &&
    $messageType !== "success"
) {
    $availableSlots = getAvailableSlots(
        $conn,
        $doctorId,
        $selectedDate
    );

    if (
        empty($availableSlots) &&
        $selectedDate >= date("Y-m-d")
    ) {
        $message = "No available appointment times on this date.";
    }
}

/* Calendar information */
$year = isset($_GET["year"])
    ? (int)$_GET["year"]
    : (int)date("Y");

if ($year < (int)date("Y")) {
    $year = (int)date("Y");
}

$previousYear = $year - 1;
$nextYear = $year + 1;

function getDayStatus(
    mysqli $conn,
    int $doctorId,
    string $date
): array {
    if ($date < date("Y-m-d")) {
        return ["past", "Past"];
    }

    $workingHours = getWorkingHoursForDate($conn, $doctorId, $date);

    if (!$workingHours) {
        return ["closed", "Closed"];
    }

    $startTime = $workingHours["start_time"];
    $endTime = $workingHours["end_time"];
    $start = strtotime($date . " " . $startTime);
    $end = strtotime($date . " " . $endTime);
    $totalSlots = 0;

    while ($start < $end) {
        $slot = date("H:i:s", $start);

        if ($date !== date("Y-m-d") || $slot > date("H:i:s")) {
            $totalSlots++;
        }

        $start = strtotime("+30 minutes", $start);
    }

    if ($totalSlots === 0) {
        return ["closed", "Closed"];
    }

    if ($date === date("Y-m-d")) {
        $currentTime = date("H:i:s");

        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM appointments
             WHERE doctor_id = ?
               AND date = ?
               AND status = 'booked'
               AND time >= ?
               AND time < ?
               AND time > ?"
        );

        $stmt->bind_param(
            "issss",
            $doctorId,
            $date,
            $startTime,
            $endTime,
            $currentTime
        );
    } else {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM appointments
             WHERE doctor_id = ?
               AND date = ?
               AND status = 'booked'
               AND time >= ?
               AND time < ?"
        );

        $stmt->bind_param(
            "isss",
            $doctorId,
            $date,
            $startTime,
            $endTime
        );
    }

    $stmt->execute();
    $booked = (int)$stmt->get_result()->fetch_assoc()["total"];

    if ($booked === 0) {
        return ["available", "Available"];
    }

    if ($booked >= $totalSlots) {
        return ["full", "Full"];
    }

    return ["partial", "Some booked"];
}

include("../includes/header.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Book Appointment | OneCare</title>

<style>
*{box-sizing:border-box}

body{
    margin:0;
    font-family:Arial,sans-serif;
    color:#263238;
    background:linear-gradient(135deg,#edf6ff,#f8fbff)
}

.container{
    width:min(1400px,92%);
    margin:38px auto 60px
}

.hero{
    padding:38px;
    margin-bottom:28px;
    border-radius:26px;
    color:white;
    background:linear-gradient(135deg,#0d47a1,#1976d2,#42a5f5);
    box-shadow:0 15px 35px rgba(21,101,192,.18)
}

.hero span{
    display:inline-block;
    padding:8px 15px;
    margin-bottom:12px;
    border-radius:999px;
    background:rgba(255,255,255,.18);
    font-size:17px;
    font-weight:bold
}

.hero h1{
    margin:0 0 10px;
    font-size:42px
}

.hero p{
    margin:0;
    font-size:21px;
    line-height:1.5
}

.doctor-card{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:15px;
    padding:25px;
    margin-bottom:25px;
    border-left:7px solid #1e88e5;
    border-radius:22px;
    background:white;
    box-shadow:0 8px 24px rgba(21,101,192,.09)
}

.doctor-item{
    padding:16px;
    border-radius:15px;
    background:#f1f7fd;
    font-size:18px
}

.doctor-item strong{
    display:block;
    margin-bottom:6px;
    color:#1565c0
}

.message{
    padding:20px;
    margin-bottom:24px;
    border-radius:17px;
    text-align:center;
    font-size:21px;
    font-weight:bold
}

.message.success{
    color:#2e7d32;
    background:#e8f5e9
}

.message.error{
    color:#c62828;
    background:#ffebee
}

.year-controls{
    display:flex;
    justify-content:center;
    gap:12px;
    margin:24px 0
}

.year-controls a{
    padding:14px 20px;
    border-radius:13px;
    color:white;
    background:#1976d2;
    text-decoration:none;
    font-size:18px;
    font-weight:bold
}

.year-controls a:hover{
    background:#0d47a1
}

.legend{
    display:flex;
    justify-content:center;
    flex-wrap:wrap;
    gap:15px;
    margin-bottom:25px
}

.legend-item{
    display:flex;
    align-items:center;
    gap:8px;
    font-size:16px;
    font-weight:bold
}

.legend-box{
    width:23px;
    height:23px;
    border-radius:6px
}

.legend-box.closed{background:#e0e0e0}
.legend-box.available{background:#d9efff;border:2px solid #42a5f5}
.legend-box.partial{background:#e8f5e9;border:2px solid #66bb6a}
.legend-box.full{background:#fff3cd;border:2px solid #fbc02d}

.calendar-grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:20px
}

.month{
    padding:20px;
    border-radius:22px;
    background:white;
    box-shadow:0 8px 22px rgba(21,101,192,.09)
}

.month h2{
    margin:0 0 18px;
    color:#1565c0;
    text-align:center;
    font-size:27px
}

.weekdays,.days{
    display:grid;
    grid-template-columns:repeat(7,1fr);
    gap:7px
}

.weekdays div{
    padding:8px 2px;
    text-align:center;
    font-size:14px;
    font-weight:bold;
    color:#455a64
}

.day{
    min-height:65px;
    padding:8px 4px;
    border-radius:11px;
    text-align:center;
    text-decoration:none;
    color:#263238;
    border:1px solid transparent
}

.day-number{
    display:block;
    margin-bottom:4px;
    font-size:18px;
    font-weight:bold
}

.day-status{
    display:block;
    font-size:11px
}

.day.past,.day.closed{
    color:#9e9e9e;
    background:#eeeeee;
    cursor:not-allowed
}

.day.available{
    color:#0d47a1;
    background:#e3f2fd;
    border-color:#42a5f5
}

.day.partial{
    color:#2e7d32;
    background:#e8f5e9;
    border-color:#66bb6a
}

.day.full{
    color:#8d6e00;
    background:#fff8df;
    border-color:#fbc02d;
    cursor:not-allowed
}

.day.available:hover,.day.partial:hover{
    transform:translateY(-2px);
    box-shadow:0 5px 12px rgba(21,101,192,.16)
}

.day.selected{
    outline:4px solid rgba(21,101,192,.25)
}

.booking-panel{
    max-width:950px;
    padding:32px;
    margin:35px auto 0;
    border-radius:24px;
    background:white;
    box-shadow:0 10px 28px rgba(21,101,192,.12)
}

.booking-panel h2{
    margin:0 0 10px;
    color:#1565c0;
    font-size:32px
}

.selected-date{
    padding:18px;
    margin:20px 0;
    border-left:6px solid #1e88e5;
    border-radius:15px;
    background:#eef6ff;
    font-size:21px;
    font-weight:bold
}

.slots{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:12px;
    margin:20px 0
}

.slot input{
    display:none
}

.slot label{
    display:block;
    padding:16px;
    border:2px solid #cfd8dc;
    border-radius:14px;
    text-align:center;
    font-size:21px;
    font-weight:bold;
    cursor:pointer
}

.slot input:checked + label{
    color:white;
    border-color:#1565c0;
    background:#1565c0
}

.confirm-btn,.back-btn{
    display:block;
    width:100%;
    padding:19px;
    border:0;
    border-radius:15px;
    text-align:center;
    text-decoration:none;
    font-size:22px;
    font-weight:bold;
    cursor:pointer
}

.confirm-btn{
    margin-top:20px;
    color:white;
    background:#43a047
}

.confirm-btn:hover{
    background:#2e7d32
}

.back-btn{
    max-width:420px;
    margin:28px auto 0;
    color:white;
    background:#607d8b
}

.back-btn:hover{
    background:#455a64
}

@media(max-width:1050px){
    .calendar-grid{grid-template-columns:repeat(2,1fr)}
    .doctor-card{grid-template-columns:repeat(2,1fr)}
}

@media(max-width:680px){
    .container{width:95%}
    .hero{padding:28px}
    .hero h1{font-size:34px}
    .calendar-grid{grid-template-columns:1fr}
    .doctor-card{grid-template-columns:1fr}
    .slots{grid-template-columns:repeat(2,1fr)}
    .year-controls{flex-wrap:wrap}
}
</style>
</head>

<body>

<div class="container">

    <section class="hero">
        <span>OneCare Senior Portal</span>
        <h1>Book an Appointment</h1>
        <p>Select an available date and appointment time.</p>
    </section>

    <section class="doctor-card">

        <div class="doctor-item">
            <strong>Doctor</strong>
            Dr. <?php echo htmlspecialchars($doctor["name"]); ?>
        </div>

        <div class="doctor-item">
            <strong>Specialization</strong>
            <?php echo htmlspecialchars($doctor["specialization"]); ?>
        </div>

        <div class="doctor-item">
            <strong>Healthcare Provider</strong>
            <?php echo htmlspecialchars($doctor["provider_name"]); ?>
        </div>

        <div class="doctor-item">
            <strong>City</strong>
            <?php echo htmlspecialchars($doctor["city"]); ?>
        </div>

    </section>

    <?php if ($message !== ""): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($messageType === "success"): ?>

        <a class="back-btn" href="appointments.php">
            View My Appointments
        </a>

    <?php else: ?>

        <div class="year-controls">

            <?php if ($previousYear >= (int)date("Y")): ?>
                <a href="?doctor_id=<?php echo $doctorId; ?>&year=<?php echo $previousYear; ?>">
                    Previous Year
                </a>
            <?php endif; ?>

            <a href="?doctor_id=<?php echo $doctorId; ?>&year=<?php echo date("Y"); ?>">
                Current Year
            </a>

            <a href="?doctor_id=<?php echo $doctorId; ?>&year=<?php echo $nextYear; ?>">
                Next Year
            </a>

        </div>

        <div class="legend">
            <div class="legend-item">
                <span class="legend-box closed"></span>
                Closed or Past
            </div>

            <div class="legend-item">
                <span class="legend-box available"></span>
                Available
            </div>

            <div class="legend-item">
                <span class="legend-box partial"></span>
                Some Appointments
            </div>

            <div class="legend-item">
                <span class="legend-box full"></span>
                Fully Booked
            </div>
        </div>

        <div class="calendar-grid">

        <?php for ($month = 1; $month <= 12; $month++): ?>

            <?php
            $firstDay = strtotime("$year-$month-01");
            $daysInMonth = (int)date("t", $firstDay);
            $startDay = (int)date("w", $firstDay);
            ?>

            <section class="month">

                <h2>
                    <?php echo date("F", $firstDay); ?>
                </h2>

                <div class="weekdays">
                    <div>Sun</div>
                    <div>Mon</div>
                    <div>Tue</div>
                    <div>Wed</div>
                    <div>Thu</div>
                    <div>Fri</div>
                    <div>Sat</div>
                </div>

                <div class="days">

                    <?php for ($blank = 0; $blank < $startDay; $blank++): ?>
                        <div></div>
                    <?php endfor; ?>

                    <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>

                        <?php
                        $date = sprintf(
                            "%04d-%02d-%02d",
                            $year,
                            $month,
                            $day
                        );

                        [$status, $statusText] = getDayStatus(
                            $conn,
                            $doctorId,
                            $date
                        );

                        $selectedClass = $date === $selectedDate
                            ? " selected"
                            : "";

                        $canSelect = in_array(
                            $status,
                            ["available", "partial"],
                            true
                        );
                        ?>

                        <?php if ($canSelect): ?>

                            <a
                                class="day <?php echo $status . $selectedClass; ?>"
                                href="?doctor_id=<?php echo $doctorId; ?>&year=<?php echo $year; ?>&date=<?php echo $date; ?>#booking"
                            >
                                <span class="day-number">
                                    <?php echo $day; ?>
                                </span>

                                <span class="day-status">
                                    <?php echo $statusText; ?>
                                </span>
                            </a>

                        <?php else: ?>

                            <div class="day <?php echo $status; ?>">
                                <span class="day-number">
                                    <?php echo $day; ?>
                                </span>

                                <span class="day-status">
                                    <?php echo $statusText; ?>
                                </span>
                            </div>

                        <?php endif; ?>

                    <?php endfor; ?>

                </div>

            </section>

        <?php endfor; ?>

        </div>

        <?php if ($selectedDate !== ""): ?>

            <section class="booking-panel" id="booking">

                <h2>Select an Appointment Time</h2>

                <div class="selected-date">
                    <?php
                    echo date(
                        "l, F j, Y",
                        strtotime($selectedDate)
                    );
                    ?>
                </div>

                <?php if (!empty($availableSlots)): ?>

                    <form method="post">

                        <input
                            type="hidden"
                            name="doctor_id"
                            value="<?php echo $doctorId; ?>"
                        >

                        <input
                            type="hidden"
                            name="date"
                            value="<?php echo htmlspecialchars($selectedDate); ?>"
                        >

                        <div class="slots">

                            <?php foreach ($availableSlots as $index => $slot): ?>

                                <div class="slot">
                                    <input
                                        type="radio"
                                        id="slot-<?php echo $index; ?>"
                                        name="time"
                                        value="<?php echo htmlspecialchars($slot); ?>"
                                        required
                                    >

                                    <label for="slot-<?php echo $index; ?>">
                                        <?php echo date("H:i", strtotime($slot)); ?>
                                    </label>
                                </div>

                            <?php endforeach; ?>

                        </div>

                        <button
                            class="confirm-btn"
                            type="submit"
                            name="confirm_appointment"
                        >
                            Confirm Appointment
                        </button>

                    </form>

                <?php endif; ?>

            </section>

        <?php endif; ?>

        <a class="back-btn" href="search.php">
            Back to Doctor Search
        </a>

    <?php endif; ?>

</div>

</body>
</html>