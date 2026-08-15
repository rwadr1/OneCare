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
$year = isset($_GET["year"])
    ? (int)$_GET["year"]
    : (int)date("Y");

/*
|--------------------------------------------------------------------------
| Get Doctor
|--------------------------------------------------------------------------
*/
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
    die("Doctor record not found.");
}

$doctorId = (int)$doctor["id"];

/*
|--------------------------------------------------------------------------
| Appointments by Date
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare(
    "SELECT
        date,
        COUNT(*) AS total,
        SUM(
            CASE
                WHEN status = 'booked' THEN 1
                ELSE 0
            END
        ) AS booked_count,
        SUM(
            CASE
                WHEN status = 'completed' THEN 1
                ELSE 0
            END
        ) AS completed_count
     FROM appointments
     WHERE doctor_id = ?
     AND status != 'cancelled'
     AND YEAR(date) = ?
     GROUP BY date"
);

$stmt->bind_param("ii", $doctorId, $year);
$stmt->execute();

$resultAppointments = $stmt->get_result();
$appointmentsByDate = [];

while ($row = $resultAppointments->fetch_assoc()) {
    $appointmentsByDate[$row["date"]] = [
        "total" => (int)$row["total"],
        "booked" => (int)$row["booked_count"],
        "completed" => (int)$row["completed_count"]
    ];
}

/*
|--------------------------------------------------------------------------
| Weekly Work Schedule
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare(
    "SELECT
        day_of_week,
        start_time,
        end_time
     FROM doctor_schedule
     WHERE doctor_id = ?"
);

$stmt->bind_param("i", $doctorId);
$stmt->execute();

$resultSchedule = $stmt->get_result();
$workDays = [];

while ($row = $resultSchedule->fetch_assoc()) {
    $workDays[(int)$row["day_of_week"]] = [
        "start" => $row["start_time"],
        "end" => $row["end_time"]
    ];
}

/*
|--------------------------------------------------------------------------
| Specific-Date Changes
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare(
    "SELECT
        work_date,
        is_working,
        start_time,
        end_time
     FROM doctor_schedule_exceptions
     WHERE doctor_id = ?
     AND YEAR(work_date) = ?"
);

$stmt->bind_param("ii", $doctorId, $year);
$stmt->execute();

$resultExceptions = $stmt->get_result();
$dateExceptions = [];

while ($row = $resultExceptions->fetch_assoc()) {
    $dateExceptions[$row["work_date"]] = [
        "is_working" => (int)$row["is_working"],
        "start" => $row["start_time"],
        "end" => $row["end_time"]
    ];
}

include("../includes/header.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Doctor Calendar</title>

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f9ff;
            color: #263238;
        }

        .container {
            width: 95%;
            margin: 30px auto;
        }

        h1 {
            text-align: center;
            color: #1e88e5;
        }

        .year-nav {
            text-align: center;
            margin-bottom: 22px;
        }

        .year-nav a {
            display: inline-block;
            padding: 10px 18px;
            margin: 0 8px;
            border-radius: 10px;
            background: #1e88e5;
            color: white;
            text-decoration: none;
            font-weight: bold;
        }

        .calendar-legend {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 24px;
            margin-bottom: 25px;
            padding: 14px 18px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            font-weight: bold;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .legend-color {
            width: 24px;
            height: 24px;
            border-radius: 7px;
            display: inline-block;
        }

        .legend-closed {
            background: #eeeeee;
            border: 1px solid #bdbdbd;
        }

        .legend-available {
            background: #e3f2fd;
            border: 1px solid #42a5f5;
        }

        .legend-some {
            background: #e8f5e9;
            border: 1px solid #66bb6a;
        }

        .legend-full {
            background: #fff8e1;
            border: 1px solid #fbc02d;
        }

        .months {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
        }

        .month {
            padding: 18px;
            border-radius: 15px;
            background: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .month h2 {
            margin-top: 0;
            text-align: center;
            color: #1e88e5;
        }

        .days-header,
        .days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 6px;
            text-align: center;
        }

        .days-header div {
            padding: 6px;
            font-weight: bold;
        }

        .day {
            display: block;
            min-height: 66px;
            padding: 7px;
            box-sizing: border-box;
            border-radius: 9px;
            text-decoration: none;
            font-size: 14px;
        }

        /* Blue: working day with no booked appointments */
        .available-day {
            background: #e3f2fd;
            border: 1px solid #42a5f5;
            color: #1565c0;
            font-weight: bold;
        }

        .available-day:hover {
            background: #bbdefb;
        }

        /* Green: some appointments are booked */
        .has-appointments {
            background: #e8f5e9;
            border: 1px solid #66bb6a;
            color: #2e7d32;
            font-weight: bold;
        }

        .has-appointments:hover {
            background: #c8e6c9;
        }

        /* Yellow: every available slot is booked */
        .full-day {
            background: #fff8e1;
            border: 1px solid #fbc02d;
            color: #8a6200;
            font-weight: bold;
        }

        .full-day:hover {
            background: #ffefb5;
        }
        .closed-day {
            background: #eeeeee;
            border: 1px solid #bdbdbd;
            color: #888;
            cursor: not-allowed;
        }

        .past-day {
            background: #eeeeee;
            border: 1px solid #bdbdbd;
            color: #777;
        }

        .past-day.has-history {
            cursor: pointer;
            font-weight: bold;
        }

        .past-day.has-history:hover {
            background: #dddddd;
            border-color: #9e9e9e;
        }

        .empty {
            background: transparent;
        }

        .small {
            display: block;
            margin-top: 3px;
            font-size: 11px;
        }

        @media (max-width: 1000px) {
            .months {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <h1>
        Doctor Calendar -
        <?php echo $year; ?>
    </h1>

    <div class="year-nav">
        <a href="calendar.php?year=<?php echo $year - 1; ?>">
            Previous Year
        </a>

        <a href="calendar.php?year=<?php echo date("Y"); ?>">
            Current Year
        </a>

        <a href="calendar.php?year=<?php echo $year + 1; ?>">
            Next Year
        </a>
    </div>

    <div class="calendar-legend">

        <span class="legend-item">
            <i class="legend-color legend-closed"></i>
            Closed or Past
        </span>

        <span class="legend-item">
            <i class="legend-color legend-available"></i>
            Available
        </span>

        <span class="legend-item">
            <i class="legend-color legend-some"></i>
            Some Appointments
        </span>

        <span class="legend-item">
            <i class="legend-color legend-full"></i>
            Fully Booked
        </span>

    </div>

    <div class="months">

        <?php
        $monthNames = [
            1 => "January",
            2 => "February",
            3 => "March",
            4 => "April",
            5 => "May",
            6 => "June",
            7 => "July",
            8 => "August",
            9 => "September",
            10 => "October",
            11 => "November",
            12 => "December"
        ];

        for ($month = 1; $month <= 12; $month++) {

            $daysInMonth = cal_days_in_month(
                CAL_GREGORIAN,
                $month,
                $year
            );

            $firstDayOfWeek = (int)date(
                "w",
                strtotime("$year-$month-01")
            );

            echo "<div class='month'>";
            echo "<h2>{$monthNames[$month]}</h2>";

            echo "<div class='days-header'>";
            echo "<div>Sun</div>";
            echo "<div>Mon</div>";
            echo "<div>Tue</div>";
            echo "<div>Wed</div>";
            echo "<div>Thu</div>";
            echo "<div>Fri</div>";
            echo "<div>Sat</div>";
            echo "</div>";

            echo "<div class='days'>";

            for ($i = 0; $i < $firstDayOfWeek; $i++) {
                echo "<div class='empty'></div>";
            }

            for ($day = 1; $day <= $daysInMonth; $day++) {

                $date = sprintf(
                    "%04d-%02d-%02d",
                    $year,
                    $month,
                    $day
                );

                $dayOfWeek = (int)date(
                    "w",
                    strtotime($date)
                );

                $isPastDay = $date < date("Y-m-d");

                /*
                |--------------------------------------------------------------------------
                | Start with the normal weekly schedule
                |--------------------------------------------------------------------------
                */
                $isWorking = isset($workDays[$dayOfWeek]);

                $startTime = $isWorking
                    ? $workDays[$dayOfWeek]["start"]
                    : null;

                $endTime = $isWorking
                    ? $workDays[$dayOfWeek]["end"]
                    : null;

                /*
                |--------------------------------------------------------------------------
                | Specific date overrides the weekly schedule
                |--------------------------------------------------------------------------
                */
                if (isset($dateExceptions[$date])) {

                    $exception = $dateExceptions[$date];

                    $isWorking =
                        $exception["is_working"] === 1;

                    if ($isWorking) {
                        $startTime = $exception["start"];
                        $endTime = $exception["end"];
                    } else {
                        $startTime = null;
                        $endTime = null;
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Calculate number of 30-minute slots
                |--------------------------------------------------------------------------
                */
                $capacity = 0;

                if (
                    $isWorking &&
                    !empty($startTime) &&
                    !empty($endTime)
                ) {
                    $startTimestamp = strtotime($startTime);
                    $endTimestamp = strtotime($endTime);

                    if ($endTimestamp > $startTimestamp) {
                        $capacity = intdiv(
                            $endTimestamp - $startTimestamp,
                            1800
                        );
                    }
                }

                $total =
                    $appointmentsByDate[$date]["total"] ?? 0;

                $bookedCount =
                    $appointmentsByDate[$date]["booked"] ?? 0;

                $completedCount =
                    $appointmentsByDate[$date]["completed"] ?? 0;

                /*
                |--------------------------------------------------------------------------
                | Gray: past day
                |--------------------------------------------------------------------------
                */
                if ($isPastDay) {

                    if ($total > 0) {
                        echo "<a class='day past-day has-history' ";
                        echo "href='day.php?date=$date'>";

                        echo $day;
                        echo "<span class='small'>Past</span>";
                        echo "<span class='small'>";
                        echo $total . " appointment(s)";
                        echo "</span>";

                        if ($completedCount > 0) {
                            echo "<span class='small'>";
                            echo $completedCount . " completed";
                            echo "</span>";
                        }

                        echo "</a>";

                    } else {
                        echo "<div class='day past-day'>";
                        echo $day;
                        echo "<span class='small'>Past</span>";
                        echo "</div>";
                    }

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Gray: weekly off or specific date closed
                |--------------------------------------------------------------------------
                */
                if (!$isWorking || $capacity <= 0) {

                    echo "<div class='day closed-day'>";
                    echo $day;
                    echo "<span class='small'>Off</span>";
                    echo "</div>";

                    continue;
                }

                $hours =
                    date("H:i", strtotime($startTime)) .
                    "-" .
                    date("H:i", strtotime($endTime));

                /*
                |--------------------------------------------------------------------------
                | Yellow: fully booked
                |--------------------------------------------------------------------------
                */
                if ($bookedCount >= $capacity) {

                    $class = "full-day";
                    $statusText = "Full";

                /*
                |--------------------------------------------------------------------------
                | Green: some appointments booked
                |--------------------------------------------------------------------------
                */
                } elseif ($bookedCount > 0) {

                    $class = "has-appointments";

                    $statusText =
                        $bookedCount .
                        "/" .
                        $capacity .
                        " booked";

                /*
                |--------------------------------------------------------------------------
                | Blue: no booked appointments
                |--------------------------------------------------------------------------
                */
                } else {

                    $class = "available-day";
                    $statusText = "Available";
                }

                echo "<a class='day $class' ";
                echo "href='day.php?date=$date'>";

                echo $day;

                echo "<span class='small'>";
                echo $hours;
                echo "</span>";

                echo "<span class='small'>";
                echo $statusText;
                echo "</span>";

                if ($total > 0) {
                    echo "<span class='small'>";
                    echo $total . " appointment(s)";
                    echo "</span>";
                }

                if ($completedCount > 0) {
                    echo "<span class='small'>";
                    echo $completedCount . " completed";
                    echo "</span>";
                }

                echo "</a>";
            }

            echo "</div>";
            echo "</div>";
        }
        ?>

    </div>

</div>

</body>
</html>