<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET["doctor_id"])) {
    die("Doctor not selected.");
}

$doctor_id = (int)$_GET["doctor_id"];
$year = isset($_GET["year"]) ? (int)$_GET["year"] : (int)date("Y");

/* Doctor */
$sqlDoctor = "SELECT users.name, doctors.specialization,
                     providers.name AS provider_name
              FROM doctors
              JOIN users ON doctors.user_id = users.id
              JOIN providers ON doctors.provider_id = providers.id
              WHERE doctors.id = $doctor_id";

$resultDoctor = $conn->query($sqlDoctor);

if (!$resultDoctor || $resultDoctor->num_rows == 0) {
    die("Doctor not found.");
}

$doctor = $resultDoctor->fetch_assoc();

/* Weekly schedule */
$sqlSchedule = "SELECT day_of_week, start_time, end_time
                FROM doctor_schedule
                WHERE doctor_id = $doctor_id";

$resultSchedule = $conn->query($sqlSchedule);
$workDays = [];

while ($row = $resultSchedule->fetch_assoc()) {
    $workDays[(int)$row["day_of_week"]] = [
        "start" => $row["start_time"],
        "end" => $row["end_time"]
    ];
}

/* Specific date changes */
$sqlExceptions = "SELECT work_date, is_working, start_time, end_time
                  FROM doctor_schedule_exceptions
                  WHERE doctor_id = $doctor_id
                  AND YEAR(work_date) = $year";

$resultExceptions = $conn->query($sqlExceptions);
$dateExceptions = [];

if ($resultExceptions) {
    while ($row = $resultExceptions->fetch_assoc()) {
        $dateExceptions[$row["work_date"]] = [
            "is_working" => (int)$row["is_working"],
            "start" => $row["start_time"],
            "end" => $row["end_time"]
        ];
    }
}

/* Booked appointments */
$sqlAppointments = "SELECT date, COUNT(*) AS total
                    FROM appointments
                    WHERE doctor_id = $doctor_id
                    AND status = 'booked'
                    AND YEAR(date) = $year
                    GROUP BY date";

$resultAppointments = $conn->query($sqlAppointments);
$appointmentsByDate = [];

if ($resultAppointments) {
    while ($row = $resultAppointments->fetch_assoc()) {
        $appointmentsByDate[$row["date"]] = (int)$row["total"];
    }
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
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #eef7ff, #f8fbff);
            margin: 0;
            color: #263238;
        }

        .page-wrapper {
            width: 95%;
            margin: 30px auto;
        }

        .hero {
            background: white;
            padding: 28px;
            border-radius: 22px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: center;
        }

        .hero h1 {
            color: #1565c0;
            margin: 0;
        }

        .hero p {
            margin: 8px 0 0;
            color: #607d8b;
        }

        .back-btn,
        .year-nav a {
            background: #1565c0;
            color: white;
            padding: 11px 18px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: bold;
        }

        .doctor-box {
            background: #eef6ff;
            border-radius: 18px;
            padding: 18px;
            margin-bottom: 25px;
            border-left: 6px solid #1e88e5;
        }

        .doctor-box p {
            margin: 8px 0;
            font-weight: bold;
            color: #455a64;
        }

        .year-nav {
            text-align: center;
            margin-bottom: 25px;
        }

        .year-nav a {
            display: inline-block;
            margin: 0 8px;
        }

        .months {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 22px;
        }

        .month {
            background: white;
            padding: 18px;
            border-radius: 18px;
            box-shadow: 0 8px 22px rgba(0,0,0,0.08);
        }

        .month h2 {
            color: #1565c0;
            text-align: center;
            margin-top: 0;
        }

        .days-header,
        .days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 6px;
            text-align: center;
        }

        .days-header div {
            font-weight: bold;
            padding: 6px;
        }

        .day {
            display: block;
            min-height: 62px;
            padding: 7px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 14px;
            box-sizing: border-box;
        }

        .available-day {
            background: #e3f2fd;
            border: 1px solid #42a5f5;
            color: #1565c0;
            font-weight: bold;
        }

        .has-appointments {
            background: #e8f5e9;
            border: 1px solid #66bb6a;
            color: #2e7d32;
            font-weight: bold;
        }

        .full-day {
            background: #fff8e1;
            border: 1px solid #fbc02d;
            color: #8a6200;
            font-weight: bold;
        }
        .closed-clickable {
            background: #eeeeee;
            border: 1px solid #d0d0d0;
            color: #777;
            font-weight: bold;
            cursor: pointer;
        }

        .closed-clickable:hover {
            background: #dddddd;
        }
        .closed-day,
        .past-day {
            background: #eeeeee;
            border: 1px solid #d0d0d0;
            color: #999;
            cursor: not-allowed;
        }

        .available-day:hover {
            background: #bbdefb;
        }

        .has-appointments:hover {
            background: #c8e6c9;
        }

        .full-day:hover {
            background: #ffefb5;
        }

        .small {
            display: block;
            font-size: 11px;
            margin-top: 3px;
        }

        .empty {
            background: transparent;
        }

        @media (max-width: 1000px) {
            .months {
                grid-template-columns: 1fr;
            }

            .hero {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>

<body>

<div class="page-wrapper">

    <div class="hero">
        <div>
            <h1>Select an Appointment Date</h1>
            <p>
                Blue: available, green: some appointments,
                yellow: full, red: no slots but alternatives are available.
            </p>
        </div>

        <a class="back-btn" href="search.php">Back to Search</a>
    </div>

    <div class="doctor-box">
        <p>Doctor: Dr. <?php echo htmlspecialchars($doctor["name"]); ?></p>
        <p>Specialization: <?php echo htmlspecialchars($doctor["specialization"]); ?></p>
        <p>Provider: <?php echo htmlspecialchars($doctor["provider_name"]); ?></p>
    </div>

    <div class="year-nav">
        <a href="?doctor_id=<?php echo $doctor_id; ?>&year=<?php echo $year - 1; ?>">
            Previous Year
        </a>

        <a href="?doctor_id=<?php echo $doctor_id; ?>&year=<?php echo date("Y"); ?>">
            Current Year
        </a>

        <a href="?doctor_id=<?php echo $doctor_id; ?>&year=<?php echo $year + 1; ?>">
            Next Year
        </a>
    </div>

    <div class="months">

        <?php
        $monthNames = [
            1 => "January", 2 => "February", 3 => "March",
            4 => "April", 5 => "May", 6 => "June",
            7 => "July", 8 => "August", 9 => "September",
            10 => "October", 11 => "November", 12 => "December"
        ];

        for ($month = 1; $month <= 12; $month++) {
            $daysInMonth = cal_days_in_month(
                CAL_GREGORIAN,
                $month,
                $year
            );

            $firstDay = date(
                "w",
                strtotime("$year-$month-01")
            );

            echo "<div class='month'>";
            echo "<h2>{$monthNames[$month]}</h2>";

            echo "<div class='days-header'>";
            echo "<div>Sun</div><div>Mon</div><div>Tue</div>";
            echo "<div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>";
            echo "</div>";

            echo "<div class='days'>";

            for ($i = 0; $i < $firstDay; $i++) {
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

                $isPast = $date < date("Y-m-d");
                $isWorking = isset($workDays[$dayOfWeek]);
                $isSpecificClosed = false;

                $startTime = $isWorking
                    ? $workDays[$dayOfWeek]["start"]
                    : null;

                $endTime = $isWorking
                    ? $workDays[$dayOfWeek]["end"]
                    : null;

                /* Specific date overrides weekly schedule */
                if (isset($dateExceptions[$date])) {
                    $exception = $dateExceptions[$date];

                    if ($exception["is_working"] === 0) {
                        $isWorking = false;
                        $isSpecificClosed = true;
                        $startTime = null;
                        $endTime = null;
                    } else {
                        $isWorking = true;
                        $startTime = $exception["start"];
                        $endTime = $exception["end"];
                    }
                }

                if ($isPast) {
                    echo "<div class='day past-day'>";
                    echo "$day<span class='small'>Past</span>";
                    echo "</div>";
                    continue;
                }

                /* Doctor closed this specific date */
                if ($isSpecificClosed) {
                    echo "<a class='day closed-clickable'
                            href='../book.php?doctor_id=$doctor_id&date=$date'>";

                    echo "$day";
                    echo "<span class='small'>Closed</span>";
                    echo "</a>";
                    continue;
}

                /* Normal weekly day off */
                if (!$isWorking || !$startTime || !$endTime) {
                    echo "<div class='day closed-day'>";
                    echo "$day<span class='small'>Closed</span>";
                    echo "</div>";
                    continue;
                }

                $start = strtotime($startTime);
                $end = strtotime($endTime);
                $capacity = (int)(($end - $start) / 1800);
                $booked = $appointmentsByDate[$date] ?? 0;
                $hours = date("H:i", $start) . "-" . date("H:i", $end);

                if ($capacity <= 0) {
                    echo "<a class='day special-closed'
                             href='../book.php?doctor_id=$doctor_id&date=$date'>";

                    echo "$day";
                    echo "<span class='small'>No Slots</span>";
                    echo "<span class='small'>See Alternatives</span>";
                    echo "</a>";

                }elseif ($booked >= $capacity) {
                        echo "<a class='day full-day'
                                href='../book.php?doctor_id=$doctor_id&date=$date'>";

                        echo "$day";
                        echo "<span class='small'>$hours</span>";
                        echo "<span class='small'>Full</span>";
                        echo "</a>";

                } else {
                    $class = $booked > 0
                        ? "has-appointments"
                        : "available-day";

                    echo "<a class='day $class'
                             href='../book.php?doctor_id=$doctor_id&date=$date'>";

                    echo "$day";
                    echo "<span class='small'>$hours</span>";

                    if ($booked > 0) {
                        echo "<span class='small'>$booked/$capacity booked</span>";
                    } else {
                        echo "<span class='small'>Available</span>";
                    }

                    echo "</a>";
                }
            }

            echo "</div>";
            echo "</div>";
        }
        
        ?>

    </div>
</div>

</body>
</html>