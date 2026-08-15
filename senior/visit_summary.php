<?php
session_start();
include("../config/db.php");

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "senior"
) {
    header("Location: ../login.php");
    exit();
}

$patientId = (int) $_SESSION["user_id"];
$appointmentId = (int) ($_GET["appointment_id"] ?? 0);

if ($appointmentId <= 0) {
    die("Invalid appointment.");
}

$sql = "SELECT a.date, a.time,
               patient.name AS patient_name,
               doctor_user.name AS doctor_name,
               d.specialization,
               p.name AS provider_name,
               v.complaint, v.diagnosis, v.findings,
               v.treatment, v.medications, v.allergies,
               v.background_diseases, v.recommendations,
               v.follow_up, v.notes
        FROM appointments a
        JOIN users patient ON a.patient_id = patient.id
        JOIN doctors d ON a.doctor_id = d.id
        JOIN users doctor_user ON d.user_id = doctor_user.id
        JOIN providers p ON d.provider_id = p.id
        JOIN visit_summaries v ON a.id = v.appointment_id
        WHERE a.id = ?
          AND a.patient_id = ?
          AND a.status = 'completed'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $appointmentId, $patientId);
$stmt->execute();

$summary = $stmt->get_result()->fetch_assoc();

if (!$summary) {
    die("Visit summary not found.");
}

include("../includes/header.php");

$sections = [
    "Reason for Visit" => "complaint",
    "Diagnosis" => "diagnosis",
    "Examination Findings" => "findings",
    "Treatment" => "treatment",
    "Medications" => "medications",
    "Allergies" => "allergies",
    "Background Diseases" => "background_diseases",
    "Recommendations" => "recommendations",
    "Follow-Up" => "follow_up",
    "Doctor Notes" => "notes"
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Visit Summary | OneCare</title>

<style>
*{box-sizing:border-box}

body{
    margin:0;
    font-family:Arial,sans-serif;
    color:#263238;
    background:linear-gradient(135deg,#eef7ff,#f8fbff)
}

.wrapper{
    width:min(1000px,90%);
    margin:38px auto 60px
}

.hero{
    padding:36px;
    margin-bottom:25px;
    border-radius:26px;
    color:white;
    background:linear-gradient(135deg,#0d47a1,#1976d2,#42a5f5);
    box-shadow:0 16px 38px rgba(21,101,192,.22)
}

.hero span{
    display:inline-block;
    padding:8px 14px;
    margin-bottom:12px;
    border-radius:999px;
    font-weight:bold;
    background:rgba(255,255,255,.18)
}

.hero h1{
    margin:0;
    font-size:40px
}

.hero p{
    margin:10px 0 0;
    font-size:19px;
    color:rgba(255,255,255,.92)
}

.info,.section{
    padding:26px;
    margin-bottom:20px;
    border-radius:22px;
    background:white;
    border:1px solid #e2ecf5;
    box-shadow:0 9px 24px rgba(21,101,192,.08)
}

.info{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:14px 25px;
    border-left:7px solid #1e88e5
}

.info p{
    margin:0;
    font-size:18px;
    color:#455a64
}

.section h2{
    margin:0 0 10px;
    color:#1565c0;
    font-size:25px
}

.section p{
    margin:0;
    font-size:19px;
    line-height:1.7;
    color:#37474f
}

.actions{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:14px;
    margin-top:25px
}

.btn{
    padding:16px;
    border:none;
    border-radius:14px;
    color:white;
    text-align:center;
    text-decoration:none;
    font-size:19px;
    font-weight:bold;
    cursor:pointer
}

.print-btn{background:#43a047}
.print-btn:hover{background:#2e7d32}

.back-btn{background:#607d8b}
.back-btn:hover{background:#455a64}

.signature{
    margin-top:28px;
    font-size:17px
}

@media(max-width:650px){
    .wrapper{width:94%}

    .info,
    .actions{
        grid-template-columns:1fr
    }

    .hero,
    .info,
    .section{
        padding:22px
    }
}

@media print{
    .navbar,
    .actions{
        display:none!important
    }

    body{
        background:white
    }

    .wrapper{
        width:100%;
        margin:0
    }

    .hero,
    .info,
    .section{
        box-shadow:none;
        border-radius:0;
        border:none;
        padding:8px 0;
        margin:0 0 8px;
        color:black;
        background:white
    }

    .hero{
        border-bottom:2px solid black
    }

    .hero h1,
    .hero p,
    .hero span,
    .info p,
    .section h2,
    .section p{
        color:black
    }

    .hero span{
        display:none
    }

    .hero h1{
        font-size:22px
    }

    .hero p,
    .info p{
        font-size:12px
    }

    .section h2{
        font-size:13px
    }

    .section p{
        font-size:11px
    }
}
</style>
</head>

<body>

<div class="wrapper">

    <section class="hero">
        <span>OneCare Senior Portal</span>
        <h1>Medical Visit Summary</h1>
        <p>This summary was completed by the doctor after the visit.</p>
    </section>

    <section class="info">

        <p>
            <strong>Patient:</strong>
            <?php echo htmlspecialchars($summary["patient_name"]); ?>
        </p>

        <p>
            <strong>Doctor:</strong>
            Dr. <?php echo htmlspecialchars($summary["doctor_name"]); ?>
        </p>

        <p>
            <strong>Specialization:</strong>
            <?php echo htmlspecialchars($summary["specialization"]); ?>
        </p>

        <p>
            <strong>Provider:</strong>
            <?php echo htmlspecialchars($summary["provider_name"]); ?>
        </p>

        <p>
            <strong>Date:</strong>
            <?php echo date("F j, Y", strtotime($summary["date"])); ?>
        </p>

        <p>
            <strong>Time:</strong>
            <?php echo date("H:i", strtotime($summary["time"])); ?>
        </p>

    </section>

    <?php foreach ($sections as $title => $field): ?>

        <section class="section">

            <h2>
                <?php echo htmlspecialchars($title); ?>
            </h2>

            <p dir="auto">
                <?php
                echo nl2br(
                    htmlspecialchars(
                        $summary[$field] ?: "Not provided."
                    )
                );
                ?>
            </p>

        </section>

    <?php endforeach; ?>

    <div class="signature">
        <p>Doctor Signature: ____________________</p>
        <p>Date: ____________________</p>
    </div>

    <div class="actions">

        <button
            class="btn print-btn"
            type="button"
            onclick="window.print()"
        >
            Print Summary
        </button>

        <a class="btn back-btn" href="appointments.php">
            Back to My Appointments
        </a>

    </div>

</div>

</body>
</html>