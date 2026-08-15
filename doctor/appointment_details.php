<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "doctor") {
    header("Location: ../login.php");
    exit();
}

$doctorUserId = (int) $_SESSION["user_id"];
$appointmentId = (int) ($_GET["id"] ?? 0);
$editMode = isset($_GET["edit"]);

if ($appointmentId <= 0) die("No appointment selected.");

$sql = "SELECT a.id,a.date,a.time,a.status,
               u.name patient_name,u.email patient_email,
               v.id summary_id,v.complaint,v.diagnosis,v.findings,
               v.treatment,v.medications,v.allergies,
               v.background_diseases,v.recommendations,
               v.follow_up,v.notes
        FROM appointments a
        JOIN users u ON a.patient_id=u.id
        JOIN doctors d ON a.doctor_id=d.id
        LEFT JOIN visit_summaries v ON a.id=v.appointment_id
        WHERE a.id=? AND d.user_id=?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $appointmentId, $doctorUserId);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();

if (!$appointment) die("Appointment not found.");

$status = $appointment["status"];
$hasSummary = !empty($appointment["summary_id"]);
$locked = in_array($status, ["missed", "cancelled"]);
$showForm = $editMode && !$locked;

$fields = [
    "Reason for Visit" => "complaint",
    "Diagnosis" => "diagnosis",
    "Examination Findings" => "findings",
    "Treatment" => "treatment",
    "Medications" => "medications",
    "Background Diseases" => "background_diseases",
    "Recommendations" => "recommendations",
    "Follow-Up" => "follow_up",
    "Doctor Notes" => "notes"
];

include("../includes/header.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Appointment Details | OneCare</title>

<style>
*{box-sizing:border-box}
body{margin:0;font-family:Arial,sans-serif;color:#263238;background:linear-gradient(135deg,#eef7ff,#f8fbff)}
.wrapper{width:min(950px,90%);margin:38px auto 60px}
.hero{padding:34px;margin-bottom:24px;border-radius:25px;color:white;background:linear-gradient(135deg,#0d47a1,#1976d2,#42a5f5);box-shadow:0 14px 34px rgba(21,101,192,.22)}
.hero span{display:inline-block;padding:7px 13px;margin-bottom:10px;border-radius:999px;font-weight:bold;background:rgba(255,255,255,.18)}
.hero h1{margin:0;font-size:36px}.hero p{margin:10px 0 0}
.info,.content{padding:25px;margin-bottom:22px;border-radius:21px;background:white;border:1px solid #e2ecf5;box-shadow:0 9px 24px rgba(21,101,192,.08)}
.info{display:grid;grid-template-columns:1fr 1fr;gap:14px 24px;border-left:7px solid #1e88e5}
.content h2{margin:0 0 22px;color:#1565c0}
.group{margin-bottom:17px}
label,.view h3{display:block;margin:0 0 7px;color:#37474f;font-weight:bold}
textarea,select{width:100%;padding:12px;border:1px solid #cfd8dc;border-radius:11px;font:inherit;background:#f8fbfe}
textarea{min-height:85px;resize:vertical}
.view{padding:16px 0;border-bottom:1px solid #e5edf5}
.view p{margin:0;line-height:1.7;white-space:pre-wrap}
.notice{padding:18px;border-radius:14px;text-align:center;font-weight:bold;background:#fff3e0;color:#e65100}
.actions{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-top:22px}
.btn{display:block;padding:14px;border:0;border-radius:12px;text-align:center;text-decoration:none;color:white;font-size:16px;font-weight:bold;cursor:pointer}
.start{background:#1565c0}.edit{background:#fb8c00}.save{background:#1565c0}.print{background:#43a047}.back{background:#607d8b}
.success{padding:14px;margin-bottom:20px;border-radius:12px;text-align:center;font-weight:bold;color:#2e7d32;background:#e8f5e9}
@media(max-width:650px){.wrapper{width:94%}.info{grid-template-columns:1fr}}
@media print{
.navbar,.actions{display:none!important}
body{background:white}.wrapper{width:100%;margin:0}
.hero,.info,.content{box-shadow:none;border:none;border-radius:0;margin:0;padding:10px 0}
.hero{background:white;color:black;border-bottom:2px solid black}
.hero span{display:none}.hero h1{font-size:22px}
.view{padding:6px 0}.view h3{font-size:13px}.view p{font-size:11px}
}
</style>
</head>

<body>
<div class="wrapper">

<section class="hero">
    <span>OneCare Doctor Portal</span>
    <h1><?php echo $hasSummary && !$showForm ? "Medical Visit Summary" : "Appointment Details"; ?></h1>
    <p>Review the appointment and manage its medical summary.</p>
</section>

<?php if (isset($_GET["saved"])): ?>
    <div class="success">Visit summary saved successfully.</div>
<?php endif; ?>

<section class="info">
    <div><strong>Patient:</strong> <?php echo htmlspecialchars($appointment["patient_name"]); ?></div>
    <div><strong>Email:</strong> <?php echo htmlspecialchars($appointment["patient_email"]); ?></div>
    <div><strong>Date:</strong> <?php echo date("F j, Y", strtotime($appointment["date"])); ?></div>
    <div><strong>Time:</strong> <?php echo date("H:i", strtotime($appointment["time"])); ?></div>
    <div><strong>Status:</strong> <?php echo ucfirst(htmlspecialchars($status)); ?></div>
</section>

<section class="content">
    <h2>Medical Visit Summary</h2>

    <?php if ($locked): ?>

        <div class="notice">
            This appointment is <?php echo htmlspecialchars($status); ?>.
            A medical summary cannot be created or edited.
        </div>

        <div class="actions">
            <a class="btn back" href="day.php?date=<?php echo urlencode($appointment["date"]); ?>">
                Back to Day
            </a>
        </div>

    <?php elseif ($showForm): ?>

        <form action="save_visit_summary.php" method="post">
            <input type="hidden" name="appointment_id" value="<?php echo $appointmentId; ?>">
            <input type="hidden" name="edit_mode" value="1">

            <?php foreach ($fields as $title => $field): ?>
                <div class="group">
                    <label><?php echo $title; ?></label>
                    <textarea name="<?php echo $field; ?>"
                        <?php echo in_array($field, ["diagnosis","treatment"]) ? "required" : ""; ?>
                    ><?php echo htmlspecialchars($appointment[$field] ?? ""); ?></textarea>
                </div>
            <?php endforeach; ?>

            <div class="group">
                <label>Allergies</label>
                <select name="allergies">
                    <?php foreach (["", "None", "Penicillin", "Food Allergy", "Other"] as $allergy): ?>
                        <option value="<?php echo htmlspecialchars($allergy); ?>"
                            <?php echo ($appointment["allergies"] ?? "") === $allergy ? "selected" : ""; ?>>
                            <?php echo $allergy ?: "Select"; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="actions">
                <button class="btn save" type="submit">
                    <?php echo $hasSummary ? "Save Changes" : "Save Visit Summary"; ?>
                </button>

                <a class="btn back" href="appointment_details.php?id=<?php echo $appointmentId; ?>">
                    Cancel Editing
                </a>
            </div>
        </form>

    <?php elseif ($hasSummary): ?>

        <?php foreach ($fields as $title => $field): ?>
            <div class="view">
                <h3><?php echo $title; ?></h3>
                <p><?php echo htmlspecialchars($appointment[$field] ?: "Not provided."); ?></p>
            </div>
        <?php endforeach; ?>

        <div class="view">
            <h3>Allergies</h3>
            <p><?php echo htmlspecialchars($appointment["allergies"] ?: "Not provided."); ?></p>
        </div>

        <div class="actions">
            <a class="btn edit" href="appointment_details.php?id=<?php echo $appointmentId; ?>&edit=1">
                Edit Summary
            </a>

            <button class="btn print" onclick="window.print()">
                Print Summary
            </button>

            <a class="btn back" href="day.php?date=<?php echo urlencode($appointment["date"]); ?>">
                Back to Day
            </a>
        </div>

    <?php else: ?>

        <div class="notice">
            No medical visit summary has been created yet.
        </div>

        <div class="actions">
            <a class="btn start" href="appointment_details.php?id=<?php echo $appointmentId; ?>&edit=1">
                Start Visit Summary
            </a>

            <a class="btn back" href="day.php?date=<?php echo urlencode($appointment["date"]); ?>">
                Back to Day
            </a>
        </div>

    <?php endif; ?>
</section>

</div>
</body>
</html>