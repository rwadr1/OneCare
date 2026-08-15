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

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: home.php");
    exit();
}

$doctorUserId = (int) $_SESSION["user_id"];
$appointmentId = (int) ($_POST["appointment_id"] ?? 0);

$fields = [
    "complaint",
    "diagnosis",
    "findings",
    "treatment",
    "medications",
    "allergies",
    "background_diseases",
    "recommendations",
    "follow_up",
    "notes"
];

$data = [];

foreach ($fields as $field) {
    $data[$field] = trim($_POST[$field] ?? "");
}

$checkAppointment = $conn->prepare("
    SELECT appointments.id
    FROM appointments
    JOIN doctors ON appointments.doctor_id = doctors.id
    WHERE appointments.id = ?
      AND doctors.user_id = ?
");

$checkAppointment->bind_param(
    "ii",
    $appointmentId,
    $doctorUserId
);

$checkAppointment->execute();

if ($checkAppointment->get_result()->num_rows === 0) {
    die("Appointment not found.");
}

$checkSummary = $conn->prepare("
    SELECT id
    FROM visit_summaries
    WHERE appointment_id = ?
");

$checkSummary->bind_param("i", $appointmentId);
$checkSummary->execute();

if ($checkSummary->get_result()->num_rows > 0) {
    $stmt = $conn->prepare("
        UPDATE visit_summaries
        SET complaint = ?, diagnosis = ?, findings = ?,
            treatment = ?, medications = ?, allergies = ?,
            background_diseases = ?, recommendations = ?,
            follow_up = ?, notes = ?
        WHERE appointment_id = ?
    ");

    $stmt->bind_param(
        "ssssssssssi",
        $data["complaint"],
        $data["diagnosis"],
        $data["findings"],
        $data["treatment"],
        $data["medications"],
        $data["allergies"],
        $data["background_diseases"],
        $data["recommendations"],
        $data["follow_up"],
        $data["notes"],
        $appointmentId
    );
} else {
    $stmt = $conn->prepare("
        INSERT INTO visit_summaries
        (
            appointment_id, complaint, diagnosis, findings,
            treatment, medications, allergies,
            background_diseases, recommendations,
            follow_up, notes
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "issssssssss",
        $appointmentId,
        $data["complaint"],
        $data["diagnosis"],
        $data["findings"],
        $data["treatment"],
        $data["medications"],
        $data["allergies"],
        $data["background_diseases"],
        $data["recommendations"],
        $data["follow_up"],
        $data["notes"]
    );
}

$stmt->execute();

$complete = $conn->prepare("
    UPDATE appointments
    SET status = 'completed'
    WHERE id = ?
");

$complete->bind_param("i", $appointmentId);
$complete->execute();

header(
    "Location: appointment_details.php?id=" .
    $appointmentId .
    "&saved=1"
);

exit();
?>