<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "admin") {
    header("Location: ../login.php");
    exit();
}

$message = "";
$messageType = "success";

$specializations = [
    "Family Medicine", "Internal Medicine", "Cardiology", "Dermatology",
    "Neurology", "Orthopedics", "Pediatrics", "Gynecology",
    "Ophthalmology", "Psychiatry", "Urology", "Oncology",
    "Endocrinology", "Gastroenterology", "Pulmonology", "Nephrology",
    "Rheumatology", "Hematology", "Allergy and Immunology",
    "Ear, Nose and Throat", "General Surgery", "Cardiothoracic Surgery",
    "Neurosurgery", "Plastic Surgery", "Vascular Surgery",
    "Emergency Medicine", "Geriatrics", "Infectious Diseases",
    "Pain Management", "Sports Medicine",
    "Physical Medicine and Rehabilitation", "Dental Medicine",
    "Oral and Maxillofacial Surgery", "Clinical Psychology",
    "Physiotherapy", "Nutrition and Dietetics"
];

$professions = [
    "General Practitioner", "Family Doctor", "Specialist Doctor",
    "Surgeon", "Dentist", "Psychiatrist", "Psychologist",
    "Physiotherapist", "Occupational Therapist", "Speech Therapist",
    "Dietitian", "Registered Nurse", "Midwife", "Pharmacist",
    "Social Worker", "Radiologist", "Optometrist", "Chiropractor",
    "Medical Consultant", "Laboratory Technician",
    "Radiology Technician", "Paramedic"
];

$cities = [
    "Acre", "Haifa", "Nahariya", "Karmiel", "Sakhnin", "Shefa-Amr",
    "Nazareth", "Nof HaGalil", "Tiberias", "Safed", "Kiryat Shmona",
    "Kiryat Ata", "Kiryat Bialik", "Kiryat Motzkin", "Kiryat Yam",
    "Tirat Carmel", "Yokneam Illit", "Afula", "Hadera", "Netanya",
    "Herzliya", "Raanana", "Kfar Saba", "Petah Tikva", "Ramat Gan",
    "Givatayim", "Bnei Brak", "Tel Aviv", "Jaffa", "Holon",
    "Bat Yam", "Rishon LeZion", "Rehovot", "Modiin", "Jerusalem",
    "Beit Shemesh", "Ashdod", "Ashkelon", "Kiryat Gat",
    "Beersheba", "Dimona", "Arad", "Eilat"
];

/* Add Provider */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_provider"])) {
    $providerName = trim($_POST["provider_name"] ?? "");
    $providerType = $_POST["provider_type"] ?? "";

    if ($providerName === "") {
        $message = "Provider name is required.";
        $messageType = "error";
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO providers (name, type) VALUES (?, ?)"
        );

        $stmt->bind_param("ss", $providerName, $providerType);

        if ($stmt->execute()) {
            $message = "Provider added successfully.";
        } else {
            $message = "Could not add provider.";
            $messageType = "error";
        }
    }
}

/* Add Doctor */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_doctor"])) {
    $name = trim($_POST["doctor_name"] ?? "");
    $email = trim($_POST["doctor_email"] ?? "");
    $plainPassword = $_POST["doctor_password"] ?? "";
    $providerId = (int) ($_POST["provider_id"] ?? 0);
    $specialization = $_POST["specialization"] ?? "";
    $profession = $_POST["profession"] ?? "";
    $city = $_POST["city"] ?? "";

    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();

    if ($check->get_result()->num_rows > 0) {
        $message = "Doctor email already exists.";
        $messageType = "error";
    } else {
        $password = password_hash($plainPassword, PASSWORD_DEFAULT);

        try {
            $conn->begin_transaction();

            $userStmt = $conn->prepare(
                "INSERT INTO users (name, email, password, role)
                 VALUES (?, ?, ?, 'doctor')"
            );

            $userStmt->bind_param("sss", $name, $email, $password);
            $userStmt->execute();

            $newUserId = $conn->insert_id;

            $doctorStmt = $conn->prepare(
                "INSERT INTO doctors
                (user_id, provider_id, specialization, profession, city)
                VALUES (?, ?, ?, ?, ?)"
            );

            $doctorStmt->bind_param(
                "iisss",
                $newUserId,
                $providerId,
                $specialization,
                $profession,
                $city
            );

            $doctorStmt->execute();
            $newDoctorId = $conn->insert_id;

            $scheduleStmt = $conn->prepare(
                "INSERT INTO doctor_schedule
                (doctor_id, day_of_week, start_time, end_time)
                VALUES (?, ?, '08:00:00', '18:00:00')"
            );

            for ($day = 0; $day <= 4; $day++) {
                $scheduleStmt->bind_param("ii", $newDoctorId, $day);
                $scheduleStmt->execute();
            }

            $conn->commit();
            $message = "Doctor account added successfully.";
        } catch (Throwable $error) {
            $conn->rollback();
            $message = "Could not add doctor.";
            $messageType = "error";
        }
    }
}

/* Update Doctor */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_doctor"])) {
    $doctorId = (int) ($_POST["doctor_id"] ?? 0);
    $userId = (int) ($_POST["user_id"] ?? 0);
    $name = trim($_POST["doctor_name"] ?? "");
    $email = trim($_POST["doctor_email"] ?? "");
    $newPassword = trim($_POST["doctor_password"] ?? "");
    $providerId = (int) ($_POST["provider_id"] ?? 0);
    $specialization = $_POST["specialization"] ?? "";
    $profession = $_POST["profession"] ?? "";
    $city = $_POST["city"] ?? "";

    $emailCheck = $conn->prepare(
        "SELECT id FROM users WHERE email = ? AND id != ?"
    );

    $emailCheck->bind_param("si", $email, $userId);
    $emailCheck->execute();

    if ($emailCheck->get_result()->num_rows > 0) {
        $message = "This email is already used by another account.";
        $messageType = "error";
    } else {
        try {
            $conn->begin_transaction();

            if ($newPassword !== "") {
                $hashedPassword = password_hash(
                    $newPassword,
                    PASSWORD_DEFAULT
                );

                $userStmt = $conn->prepare(
                    "UPDATE users
                     SET name = ?, email = ?, password = ?
                     WHERE id = ?"
                );

                $userStmt->bind_param(
                    "sssi",
                    $name,
                    $email,
                    $hashedPassword,
                    $userId
                );
            } else {
                $userStmt = $conn->prepare(
                    "UPDATE users
                     SET name = ?, email = ?
                     WHERE id = ?"
                );

                $userStmt->bind_param(
                    "ssi",
                    $name,
                    $email,
                    $userId
                );
            }

            $userStmt->execute();

            $doctorStmt = $conn->prepare(
                "UPDATE doctors
                 SET provider_id = ?,
                     specialization = ?,
                     profession = ?,
                     city = ?
                 WHERE id = ?"
            );

            $doctorStmt->bind_param(
                "isssi",
                $providerId,
                $specialization,
                $profession,
                $city,
                $doctorId
            );

            $doctorStmt->execute();

            $conn->commit();
            $message = "Doctor updated successfully.";
        } catch (Throwable $error) {
            $conn->rollback();
            $message = "Could not update doctor.";
            $messageType = "error";
        }
    }
}

/* Load Data */
$usersResult = $conn->query(
    "SELECT id, name, email, role
     FROM users
     ORDER BY id ASC"
);

$providersResult = $conn->query(
    "SELECT id, name, type
     FROM providers
     ORDER BY name ASC"
);

$providers = [];

while ($provider = $providersResult->fetch_assoc()) {
    $providers[] = $provider;
}

$doctorsResult = $conn->query(
    "SELECT d.id, d.user_id,
            u.name AS doctor_name,
            u.email AS doctor_email,
            d.provider_id,
            d.specialization,
            d.profession,
            d.city,
            p.name AS provider_name
     FROM doctors d
     JOIN users u ON d.user_id = u.id
     JOIN providers p ON d.provider_id = p.id
     ORDER BY u.name ASC"
);

include("../includes/header.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel | OneCare</title>

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

        .container {
            width: min(1350px, 92%);
            margin: 35px auto 60px;
        }

        .admin-hero {
            padding: 35px;
            margin-bottom: 28px;
            border-radius: 25px;
            color: white;
            background:
                linear-gradient(135deg, #0d47a1, #1976d2, #42a5f5);
            box-shadow: 0 15px 35px rgba(21, 101, 192, .22);
        }

        .admin-hero span {
            display: inline-block;
            padding: 7px 13px;
            margin-bottom: 10px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .18);
            font-weight: bold;
        }

        .admin-hero h1 {
            margin: 0;
            font-size: 38px;
        }

        .admin-hero p {
            margin: 10px 0 0;
            font-size: 17px;
            color: rgba(255, 255, 255, .92);
        }

        .message {
            padding: 15px;
            margin-bottom: 24px;
            border-radius: 13px;
            text-align: center;
            font-weight: bold;
        }

        .message.success {
            color: #2e7d32;
            background: #e8f5e9;
        }

        .message.error {
            color: #c62828;
            background: #ffebee;
        }

        .box {
            padding: 26px;
            margin-bottom: 28px;
            border: 1px solid #e3edf7;
            border-radius: 22px;
            background: white;
            box-shadow: 0 8px 24px rgba(21, 101, 192, .08);
        }

        .box-header {
            margin-bottom: 20px;
        }

        .box-header h2 {
            margin: 0;
            color: #1565c0;
            font-size: 25px;
        }

        .box-header p {
            margin: 7px 0 0;
            color: #607d8b;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
        }

        input,
        select {
            width: 100%;
            min-width: 0;
            padding: 12px;
            border: 1px solid #cfd8dc;
            border-radius: 10px;
            font: inherit;
            color: #37474f;
            background: #f9fcff;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #1e88e5;
            box-shadow: 0 0 0 3px rgba(30, 136, 229, .12);
        }

        button {
            padding: 12px 18px;
            border: none;
            border-radius: 10px;
            color: white;
            background: #1565c0;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #0d47a1;
        }

        .green-btn {
            background: #43a047;
        }

        .green-btn:hover {
            background: #2e7d32;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            margin-top: 12px;
            border-collapse: collapse;
            background: white;
        }

        th,
        td {
            padding: 12px;
            border-bottom: 1px solid #e5edf5;
            text-align: center;
            vertical-align: middle;
        }

        th {
            color: #1565c0;
            background: #eaf4ff;
            white-space: nowrap;
        }

        tr:hover td {
            background: #fbfdff;
        }

        .doctor-id {
            width: 70px;
            color: #607d8b;
            font-weight: bold;
        }

        .doctor-edit-form {
            min-width: 900px;
            display: grid;
            grid-template-columns:
                1.1fr 1.25fr 1.1fr 1fr 1.25fr 1.15fr 1fr auto;
            gap: 9px;
            align-items: center;
        }

        .password-note {
            display: block;
            margin-top: 12px;
            color: #78909c;
            font-size: 13px;
        }

        .role {
            display: inline-block;
            padding: 7px 11px;
            border-radius: 999px;
            color: #1565c0;
            background: #e3f2fd;
            font-size: 13px;
            font-weight: bold;
        }

        @media (max-width: 950px) {
            .form-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 650px) {
            .container {
                width: 94%;
            }

            .admin-hero,
            .box {
                padding: 22px;
            }

            .admin-hero h1 {
                font-size: 31px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <section class="admin-hero">
        <span>OneCare Administration</span>
        <h1>Admin Panel</h1>
        <p>
            Manage healthcare providers, doctor accounts and system users.
        </p>
    </section>

    <?php if ($message !== ""): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <section class="box">
        <div class="box-header">
            <h2>Add Provider</h2>
            <p>Add an HMO or private healthcare provider.</p>
        </div>

        <form method="post" class="form-grid">
            <input
                type="text"
                name="provider_name"
                placeholder="Provider Name"
                required
            >

            <select name="provider_type" required>
                <option value="HMO">HMO</option>
                <option value="private">Private</option>
            </select>

            <button type="submit" name="add_provider">
                Add Provider
            </button>
        </form>
    </section>

    <section class="box">
        <div class="box-header">
            <h2>Add Doctor Account</h2>
            <p>
                Create the doctor login account and professional profile.
            </p>
        </div>

        <form method="post" class="form-grid">
            <input
                type="text"
                name="doctor_name"
                placeholder="Doctor Name"
                required
            >

            <input
                type="email"
                name="doctor_email"
                placeholder="Doctor Email"
                required
            >

            <input
                type="password"
                name="doctor_password"
                placeholder="Login Password"
                minlength="6"
                required
            >

            <select name="provider_id" required>
                <option value="" disabled selected>
                    Select Provider
                </option>

                <?php foreach ($providers as $provider): ?>
                    <option value="<?php echo $provider["id"]; ?>">
                        <?php echo htmlspecialchars($provider["name"]); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="specialization" required>
                <option value="" disabled selected>
                    Select Specialization
                </option>

                <?php foreach ($specializations as $item): ?>
                    <option value="<?php echo htmlspecialchars($item); ?>">
                        <?php echo htmlspecialchars($item); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="profession" required>
                <option value="" disabled selected>
                    Select Profession
                </option>

                <?php foreach ($professions as $item): ?>
                    <option value="<?php echo htmlspecialchars($item); ?>">
                        <?php echo htmlspecialchars($item); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="city" required>
                <option value="" disabled selected>
                    Select City
                </option>

                <?php foreach ($cities as $item): ?>
                    <option value="<?php echo htmlspecialchars($item); ?>">
                        <?php echo htmlspecialchars($item); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button
                class="green-btn"
                type="submit"
                name="add_doctor"
            >
                Add Doctor
            </button>
        </form>
    </section>

    <section class="box">
        <div class="box-header">
            <h2>Doctor Accounts</h2>
            <p>
                Update doctor details or enter a new password when needed.
            </p>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Edit Doctor</th>
                    </tr>
                </thead>

                <tbody>
                <?php while ($row = $doctorsResult->fetch_assoc()): ?>
                    <tr>
                        <td class="doctor-id">
                            <?php echo $row["id"]; ?>
                        </td>

                        <td>
                            <form method="post" class="doctor-edit-form">
                                <input
                                    type="hidden"
                                    name="doctor_id"
                                    value="<?php echo $row["id"]; ?>"
                                >

                                <input
                                    type="hidden"
                                    name="user_id"
                                    value="<?php echo $row["user_id"]; ?>"
                                >

                                <input
                                    type="text"
                                    name="doctor_name"
                                    value="<?php echo htmlspecialchars($row["doctor_name"]); ?>"
                                    required
                                >

                                <input
                                    type="email"
                                    name="doctor_email"
                                    value="<?php echo htmlspecialchars($row["doctor_email"]); ?>"
                                    required
                                >

                                <input
                                    type="password"
                                    name="doctor_password"
                                    placeholder="New Password"
                                    minlength="6"
                                >

                                <select name="provider_id" required>
                                    <?php foreach ($providers as $provider): ?>
                                        <option
                                            value="<?php echo $provider["id"]; ?>"
                                            <?php
                                            echo $provider["id"] == $row["provider_id"]
                                                ? "selected"
                                                : "";
                                            ?>
                                        >
                                            <?php echo htmlspecialchars($provider["name"]); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <select name="specialization" required>
                                    <?php foreach ($specializations as $item): ?>
                                        <option
                                            value="<?php echo htmlspecialchars($item); ?>"
                                            <?php
                                            echo $row["specialization"] === $item
                                                ? "selected"
                                                : "";
                                            ?>
                                        >
                                            <?php echo htmlspecialchars($item); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <select name="profession" required>
                                    <?php foreach ($professions as $item): ?>
                                        <option
                                            value="<?php echo htmlspecialchars($item); ?>"
                                            <?php
                                            echo $row["profession"] === $item
                                                ? "selected"
                                                : "";
                                            ?>
                                        >
                                            <?php echo htmlspecialchars($item); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <select name="city" required>
                                    <?php foreach ($cities as $item): ?>
                                        <option
                                            value="<?php echo htmlspecialchars($item); ?>"
                                            <?php
                                            echo $row["city"] === $item
                                                ? "selected"
                                                : "";
                                            ?>
                                        >
                                            <?php echo htmlspecialchars($item); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <button
                                    type="submit"
                                    name="update_doctor"
                                >
                                    Update
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <span class="password-note">
            Leave the password field empty to keep the current password.
        </span>
    </section>

    <section class="box">
        <div class="box-header">
            <h2>System Users</h2>
            <p>View the accounts registered in OneCare.</p>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                    </tr>
                </thead>

                <tbody>
                <?php while ($row = $usersResult->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row["id"]; ?></td>

                        <td>
                            <?php echo htmlspecialchars($row["name"]); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($row["email"]); ?>
                        </td>

                        <td>
                            <span class="role">
                                <?php echo ucfirst(htmlspecialchars($row["role"])); ?>
                            </span>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="box">
        <div class="box-header">
            <h2>Healthcare Providers</h2>
            <p>View all providers available in the system.</p>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Provider Name</th>
                        <th>Type</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($providers as $provider): ?>
                    <tr>
                        <td><?php echo $provider["id"]; ?></td>

                        <td>
                            <?php echo htmlspecialchars($provider["name"]); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($provider["type"]); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>

</body>
</html>