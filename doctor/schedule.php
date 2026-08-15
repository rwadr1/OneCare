<?php
session_start();
include("../config/db.php");

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "doctor"
) {
    header("Location: ../login.php");
    exit();
}

$userId = (int)$_SESSION["user_id"];
$message = "";
$messageType = "success";

$stmt = $conn->prepare(
    "SELECT id
     FROM doctors
     WHERE user_id = ?
     LIMIT 1"
);

$stmt->bind_param("i", $userId);
$stmt->execute();

$doctor = $stmt->get_result()->fetch_assoc();

if (!$doctor) {
    die("Doctor not found.");
}

$doctorId = (int)$doctor["id"];

$days = [
    0 => "Sunday",
    1 => "Monday",
    2 => "Tuesday",
    3 => "Wednesday",
    4 => "Thursday",
    5 => "Friday",
    6 => "Saturday"
];

/*
|--------------------------------------------------------------------------
| Save Weekly Schedule
|--------------------------------------------------------------------------
*/
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["save_weekly_schedule"])
) {
    $conn->begin_transaction();

    try {
        $deleteStmt = $conn->prepare(
            "DELETE FROM doctor_schedule
             WHERE doctor_id = ?"
        );

        $deleteStmt->bind_param("i", $doctorId);
        $deleteStmt->execute();

        $insertStmt = $conn->prepare(
            "INSERT INTO doctor_schedule
             (doctor_id, day_of_week, start_time, end_time)
             VALUES (?, ?, ?, ?)"
        );

        foreach ($days as $dayNumber => $dayName) {
            if (!isset($_POST["work_day"][$dayNumber])) {
                continue;
            }

            $start = $_POST["start_time"][$dayNumber] ?? "";
            $end = $_POST["end_time"][$dayNumber] ?? "";

            if ($start === "" || $end === "") {
                throw new Exception(
                    "Start and end times are required for " . $dayName . "."
                );
            }

            if ($start >= $end) {
                throw new Exception(
                    "End time must be later than start time for " .
                    $dayName . "."
                );
            }

            $insertStmt->bind_param(
                "iiss",
                $doctorId,
                $dayNumber,
                $start,
                $end
            );

            if (!$insertStmt->execute()) {
                throw new Exception("Unable to save weekly schedule.");
            }
        }

        $conn->commit();

        $message = "Weekly schedule updated successfully.";
        $messageType = "success";

    } catch (Throwable $e) {
        $conn->rollback();

        $message = $e->getMessage();
        $messageType = "error";
    }
}

/*
|--------------------------------------------------------------------------
| Save Date Exception
|--------------------------------------------------------------------------
*/
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["save_exception"])
) {
    $workDate = $_POST["work_date"] ?? "";
    $isWorking = isset($_POST["is_working"]) ? 1 : 0;
    $startTime = $_POST["exception_start_time"] ?? "";
    $endTime = $_POST["exception_end_time"] ?? "";
    $note = trim($_POST["note"] ?? "");

    if ($workDate === "" || $workDate < date("Y-m-d")) {
        $message = "Please select today or a future date.";
        $messageType = "error";

    } elseif (
        $isWorking === 1 &&
        (
            $startTime === "" ||
            $endTime === "" ||
            $startTime >= $endTime
        )
    ) {
        $message = "Please enter valid working hours.";
        $messageType = "error";

    } else {
        if ($isWorking === 0) {
            $startTime = null;
            $endTime = null;
        }

        $stmt = $conn->prepare(
            "INSERT INTO doctor_schedule_exceptions
             (
                doctor_id,
                work_date,
                is_working,
                start_time,
                end_time,
                note
             )
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                is_working = VALUES(is_working),
                start_time = VALUES(start_time),
                end_time = VALUES(end_time),
                note = VALUES(note)"
        );

        $stmt->bind_param(
            "isisss",
            $doctorId,
            $workDate,
            $isWorking,
            $startTime,
            $endTime,
            $note
        );

        if ($stmt->execute()) {
            $message = "Date exception saved successfully.";
            $messageType = "success";
        } else {
            $message = "Unable to save the date exception.";
            $messageType = "error";
        }
    }
}

/*
|--------------------------------------------------------------------------
| Delete Date Exception
|--------------------------------------------------------------------------
*/
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["delete_exception"])
) {
    $exceptionId = (int)($_POST["exception_id"] ?? 0);

    $stmt = $conn->prepare(
        "DELETE FROM doctor_schedule_exceptions
         WHERE id = ?
         AND doctor_id = ?"
    );

    $stmt->bind_param(
        "ii",
        $exceptionId,
        $doctorId
    );

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $message = "Date exception removed.";
        $messageType = "success";
    } else {
        $message = "Unable to remove the date exception.";
        $messageType = "error";
    }
}

/*
|--------------------------------------------------------------------------
| Load Weekly Schedule
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare(
    "SELECT day_of_week, start_time, end_time
     FROM doctor_schedule
     WHERE doctor_id = ?"
);

$stmt->bind_param("i", $doctorId);
$stmt->execute();

$resultSchedule = $stmt->get_result();
$schedule = [];

while ($row = $resultSchedule->fetch_assoc()) {
    $schedule[(int)$row["day_of_week"]] = [
        "start" => $row["start_time"],
        "end" => $row["end_time"]
    ];
}

/*
|--------------------------------------------------------------------------
| Load Future Date Exceptions
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare(
    "SELECT
        id,
        work_date,
        is_working,
        start_time,
        end_time,
        note
     FROM doctor_schedule_exceptions
     WHERE doctor_id = ?
     AND work_date >= CURDATE()
     ORDER BY work_date ASC"
);

$stmt->bind_param("i", $doctorId);
$stmt->execute();

$exceptions = $stmt->get_result();

include("../includes/header.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Doctor Schedule</title>

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
            padding: 32px;
            border-radius: 24px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            margin-bottom: 28px;
        }

        .hero h1 {
            color: #1565c0;
            margin: 0;
            font-size: 34px;
        }

        .hero p {
            color: #607d8b;
            font-size: 17px;
        }

        .message {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 14px;
            border-radius: 14px;
            text-align: center;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .schedule-card {
            background: white;
            border-radius: 24px;
            padding: 28px;
            box-shadow: 0 8px 22px rgba(0,0,0,0.08);
            border: 1px solid #e3edf7;
        }

        .day-row {
            display: grid;
            grid-template-columns: 120px 120px 1fr 1fr;
            gap: 18px;
            align-items: center;
            padding: 18px 0;
            border-bottom: 1px solid #eef2f6;
        }

        .day-row:last-child {
            border-bottom: none;
        }

        .day-name {
            font-weight: bold;
            color: #1565c0;
            font-size: 17px;
        }

        label {
            font-weight: bold;
            color: #455a64;
        }

        input[type="time"] {
            width: 100%;
            padding: 12px;
            border-radius: 12px;
            border: 1px solid #cfd8dc;
            font-size: 15px;
        }

        input[type="checkbox"] {
            width: 22px;
            height: 22px;
        }

        .save-btn {
            margin-top: 25px;
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 15px;
            background: #1565c0;
            color: white;
            font-size: 17px;
            font-weight: bold;
            cursor: pointer;
        }

        .save-btn:hover {
            background: #0d47a1;
        }

        @media (max-width: 800px) {
            .day-row {
                grid-template-columns: 1fr;
            }
        }
        .message.error{
            color:#c62828;
            background:#ffebee
        }

        .message.success{
            color:#2e7d32;
            background:#e8f5e9
        }
    </style>
</head>

<body>
<div class="page-wrapper">

    <div class="hero">
        <h1>Doctor Schedule</h1>
        <p>Choose your working days and set appointment hours.</p>
    </div>

    <?php if ($message !== ""): ?>
        <p class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </p>
    <?php endif; ?>

    <div class="schedule-card">
        <form method="post">

            <?php foreach ($days as $dayNumber => $dayName): ?>

                <?php
                    $isChecked = isset($schedule[$dayNumber]);

                    $startValue = $isChecked
                        ? date("H:i", strtotime($schedule[$dayNumber]["start"]))
                        : "08:00";

                    $endValue = $isChecked
                        ? date("H:i", strtotime($schedule[$dayNumber]["end"]))
                        : "18:00";
                ?>

                <div class="day-row">

                    <div class="day-name">
                        <?php echo $dayName; ?>
                    </div>

                    <label>
                        <input
                            type="checkbox"
                            name="work_day[<?php echo $dayNumber; ?>]"
                            <?php echo $isChecked ? "checked" : ""; ?>
                        >
                        Work Day
                    </label>

                    <div>
                        <label>Start Time</label>

                        <input
                            type="time"
                            name="start_time[<?php echo $dayNumber; ?>]"
                            value="<?php echo $startValue; ?>"
                        >
                    </div>

                    <div>
                        <label>End Time</label>

                        <input
                            type="time"
                            name="end_time[<?php echo $dayNumber; ?>]"
                            value="<?php echo $endValue; ?>"
                        >
                    </div>

                </div>

            <?php endforeach; ?>

            <button
                class="save-btn"
                type="submit"
                name="save_weekly_schedule"
            >
                Save Weekly Schedule
            </button>

        </form>
    </div>

    <div class="schedule-card exception-card">

        <h2>Specific Date Change</h2>

        <p class="section-description">
            Close a specific date or use different working hours for that date.
        </p>

        <form method="post">

            <div class="exception-grid">

                <div>
                    <label for="work_date">Date</label>

                    <input
                        id="work_date"
                        type="date"
                        name="work_date"
                        min="<?php echo date("Y-m-d"); ?>"
                        required
                    >
                </div>

                <div class="working-check">
                    <label>
                        <input
                            id="is_working"
                            type="checkbox"
                            name="is_working"
                            checked
                        >
                        Working on this date
                    </label>
                </div>

                <div>
                    <label for="exception_start_time">
                        Start Time
                    </label>

                    <input
                        id="exception_start_time"
                        type="time"
                        name="exception_start_time"
                        value="08:00"
                    >
                </div>

                <div>
                    <label for="exception_end_time">
                        End Time
                    </label>

                    <input
                        id="exception_end_time"
                        type="time"
                        name="exception_end_time"
                        value="18:00"
                    >
                </div>

            </div>

            <div class="note-field">

                <label for="note">
                    Note
                </label>

                <input
                    id="note"
                    type="text"
                    name="note"
                    maxlength="255"
                    placeholder="Example: Personal day or half working day"
                >

            </div>

            <button
                class="save-btn"
                type="submit"
                name="save_exception"
            >
                Save Date Change
            </button>

        </form>

    </div>

    <div class="schedule-card exception-card">

        <h2>Upcoming Date Changes</h2>

        <?php if ($exceptions->num_rows > 0): ?>

            <div class="exception-list">

                <?php while ($exception = $exceptions->fetch_assoc()): ?>

                    <div class="exception-item">

                        <div>

                            <strong>
                                <?php
                                echo date(
                                    "l, F j, Y",
                                    strtotime($exception["work_date"])
                                );
                                ?>
                            </strong>

                            <?php if ((int)$exception["is_working"] === 1): ?>

                                <span class="exception-hours">
                                    Working:
                                    <?php
                                    echo date(
                                        "H:i",
                                        strtotime($exception["start_time"])
                                    );
                                    ?>
                                    -
                                    <?php
                                    echo date(
                                        "H:i",
                                        strtotime($exception["end_time"])
                                    );
                                    ?>
                                </span>

                            <?php else: ?>

                                <span class="closed-date">
                                    Closed
                                </span>

                            <?php endif; ?>

                            <?php if (!empty($exception["note"])): ?>

                                <small>
                                    <?php
                                    echo htmlspecialchars(
                                        $exception["note"]
                                    );
                                    ?>
                                </small>

                            <?php endif; ?>

                        </div>

                        <form method="post">

                            <input
                                type="hidden"
                                name="exception_id"
                                value="<?php echo (int)$exception["id"]; ?>"
                            >

                            <button
                                class="delete-btn"
                                type="submit"
                                name="delete_exception"
                                onclick="return confirm('Remove this date change?');"
                            >
                                Remove
                            </button>

                        </form>

                    </div>

                <?php endwhile; ?>

            </div>

        <?php else: ?>

            <p class="empty-exceptions">
                No upcoming date changes.
            </p>

        <?php endif; ?>

    </div>

</div>

<script>
const workingCheckbox = document.getElementById("is_working");
const exceptionStart = document.getElementById("exception_start_time");
const exceptionEnd = document.getElementById("exception_end_time");

function updateExceptionInputs() {
    const disabled = !workingCheckbox.checked;

    exceptionStart.disabled = disabled;
    exceptionEnd.disabled = disabled;
}

workingCheckbox.addEventListener(
    "change",
    updateExceptionInputs
);

updateExceptionInputs();
</script>

</body>
</html>