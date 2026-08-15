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

$doctorName = trim($_POST["doctor_name"] ?? "");
$specialization = $_POST["specialization"] ?? "";
$provider = $_POST["provider"] ?? "";
$searched = $_SERVER["REQUEST_METHOD"] === "POST";

$sql = "SELECT doctors.id, users.name, doctors.specialization,
               providers.name AS provider_name
        FROM doctors
        JOIN users ON doctors.user_id = users.id
        JOIN providers ON doctors.provider_id = providers.id
        WHERE 1";

if ($searched) {
    if ($doctorName !== "") {
        $nameSafe = $conn->real_escape_string($doctorName);
        $sql .= " AND users.name LIKE '%$nameSafe%'";
    }

    if ($specialization !== "") {
        $specializationSafe = $conn->real_escape_string($specialization);
        $sql .= " AND doctors.specialization = '$specializationSafe'";
    }

    if ($provider !== "") {
        $providerSafe = $conn->real_escape_string($provider);
        $sql .= " AND providers.name = '$providerSafe'";
    }
}

$sql .= " ORDER BY users.name";
$result = $conn->query($sql);

include("../includes/header.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Doctor | OneCare</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            color: #263238;
            background:
                radial-gradient(circle at top left, rgba(30,136,229,.10), transparent 32%),
                linear-gradient(135deg, #eef7ff, #f8fbff 55%, #edf6ff);
        }

        .page-wrapper {
            width: min(1180px, 92%);
            margin: 38px auto 60px;
        }

        .hero {
            position: relative;
            overflow: hidden;
            padding: 42px;
            margin-bottom: 28px;
            border-radius: 28px;
            color: white;
            background: linear-gradient(135deg, #0d47a1, #1976d2, #42a5f5);
            box-shadow: 0 16px 40px rgba(21,101,192,.24);
        }

        .hero::before {
            content: "";
            position: absolute;
            width: 270px;
            height: 270px;
            right: -70px;
            top: -125px;
            border-radius: 50%;
            background: rgba(255,255,255,.10);
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .hero-badge {
            display: inline-block;
            padding: 9px 15px;
            margin-bottom: 14px;
            border-radius: 999px;
            font-size: 16px;
            font-weight: bold;
            background: rgba(255,255,255,.18);
            border: 1px solid rgba(255,255,255,.28);
        }

        .hero h1 {
            margin: 0;
            font-size: clamp(36px, 5vw, 48px);
        }

        .hero p {
            max-width: 720px;
            margin: 14px 0 0;
            font-size: 20px;
            line-height: 1.7;
            color: rgba(255,255,255,.92);
        }

        .search-box {
            padding: 32px;
            margin-bottom: 34px;
            border-radius: 24px;
            background: white;
            border: 1px solid #e2ecf5;
            box-shadow: 0 10px 28px rgba(21,101,192,.08);
        }

        .search-box h2 {
            margin: 0 0 8px;
            color: #16324f;
            font-size: 30px;
        }

        .search-box p {
            margin: 0 0 24px;
            color: #78909c;
            font-size: 18px;
        }

        .search-form {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            align-items: end;
        }

        label {
            display: block;
            margin-bottom: 9px;
            color: #455a64;
            font-size: 18px;
            font-weight: bold;
        }

        input,
        select {
            width: 100%;
            height: 58px;
            padding: 0 16px;
            border: 2px solid #d8e4ee;
            border-radius: 14px;
            background: #f8fbfe;
            color: #37474f;
            font-size: 18px;
            outline: none;
        }

        input:focus,
        select:focus {
            background: white;
            border-color: #42a5f5;
            box-shadow: 0 0 0 4px rgba(66,165,245,.12);
        }

        .search-btn {
            grid-column: 1 / -1;
            min-height: 58px;
            border: none;
            border-radius: 14px;
            color: white;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
            background: linear-gradient(135deg, #1565c0, #1e88e5);
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 18px;
        }

        .section-heading h2 {
            margin: 0;
            color: #16324f;
            font-size: 30px;
        }

        .result-count {
            padding: 10px 16px;
            border-radius: 999px;
            color: #1565c0;
            background: #e3f2fd;
            font-size: 16px;
            font-weight: bold;
        }

        .doctors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 24px;
        }

        .doctor-card {
            position: relative;
            overflow: hidden;
            padding: 30px;
            border-radius: 24px;
            background: white;
            border: 1px solid #e2ecf5;
            box-shadow: 0 10px 28px rgba(21,101,192,.08);
            transition: .25s;
        }

        .doctor-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 36px rgba(21,101,192,.13);
        }

        .doctor-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 7px;
            background: linear-gradient(90deg, #1e88e5, #64b5f6);
        }

        .doctor-top {
            display: flex;
            align-items: center;
            gap: 17px;
            margin-bottom: 22px;
        }

        .doctor-icon {
            width: 72px;
            height: 72px;
            min-width: 72px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e3f2fd;
            font-size: 33px;
        }

        .doctor-card h3 {
            margin: 0 0 7px;
            color: #1565c0;
            font-size: 27px;
        }

        .doctor-card p {
            margin: 8px 0;
            color: #455a64;
            font-size: 19px;
            line-height: 1.5;
        }

        .book-btn {
            display: block;
            margin-top: 22px;
            padding: 17px;
            border-radius: 14px;
            color: white;
            text-align: center;
            text-decoration: none;
            font-size: 20px;
            font-weight: bold;
            background: #43a047;
        }

        .book-btn:hover {
            background: #2e7d32;
        }

        .empty {
            grid-column: 1 / -1;
            padding: 45px;
            border-radius: 24px;
            background: white;
            text-align: center;
            color: #607d8b;
            font-size: 21px;
            box-shadow: 0 10px 28px rgba(21,101,192,.08);
        }

        @media (max-width: 850px) {
            .search-form {
                grid-template-columns: 1fr;
            }

            .doctors-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 600px) {
            .page-wrapper {
                width: 94%;
                margin-top: 24px;
            }

            .hero,
            .search-box,
            .doctor-card {
                padding: 25px;
            }

            .section-heading {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<div class="page-wrapper">

    <section class="hero">
        <div class="hero-content">
            <span class="hero-badge">OneCare Senior Portal</span>

            <h1>Search Doctor</h1>

            <p>
                Find a suitable doctor and continue to appointment booking.
            </p>
        </div>
    </section>

    <section class="search-box">

        <h2>Doctor Search</h2>
        <p>Select one or more search options.</p>

        <form method="post" class="search-form">

            <div>
                <label>Doctor Name</label>

                <input
                    type="text"
                    name="doctor_name"
                    placeholder="Example: RAWAD"
                    value="<?php echo htmlspecialchars($doctorName); ?>"
                >
            </div>

            <div>
                <label>Specialization</label>

                <select name="specialization">
                    <option value="">All Specializations</option>
                    <option value="Family Medicine" <?php if ($specialization === "Family Medicine") echo "selected"; ?>>Family Medicine</option>
                    <option value="Pediatrics" <?php if ($specialization === "Pediatrics") echo "selected"; ?>>Pediatrics</option>
                    <option value="Cardiology" <?php if ($specialization === "Cardiology") echo "selected"; ?>>Cardiology</option>
                    <option value="Dermatology" <?php if ($specialization === "Dermatology") echo "selected"; ?>>Dermatology</option>
                    <option value="Orthopedics" <?php if ($specialization === "Orthopedics") echo "selected"; ?>>Orthopedics</option>
                    <option value="Neurology" <?php if ($specialization === "Neurology") echo "selected"; ?>>Neurology</option>
                    <option value="Ophthalmology" <?php if ($specialization === "Ophthalmology") echo "selected"; ?>>Ophthalmology</option>
                    <option value="ENT" <?php if ($specialization === "ENT") echo "selected"; ?>>Ear, Nose and Throat</option>
                    <option value="Gynecology" <?php if ($specialization === "Gynecology") echo "selected"; ?>>Gynecology</option>
                    <option value="Dentistry" <?php if ($specialization === "Dentistry") echo "selected"; ?>>Dentistry</option>
                    <option value="Psychiatry" <?php if ($specialization === "Psychiatry") echo "selected"; ?>>Psychiatry</option>
                    <option value="Gastroenterology" <?php if ($specialization === "Gastroenterology") echo "selected"; ?>>Gastroenterology</option>
                    <option value="Endocrinology" <?php if ($specialization === "Endocrinology") echo "selected"; ?>>Endocrinology</option>
                    <option value="Urology" <?php if ($specialization === "Urology") echo "selected"; ?>>Urology</option>
                    <option value="General Surgery" <?php if ($specialization === "General Surgery") echo "selected"; ?>>General Surgery</option>
                </select>
            </div>

            <div>
                <label>Healthcare Provider</label>

                <select name="provider">
                    <option value="">All Providers</option>
                    <option value="Clalit" <?php if ($provider === "Clalit") echo "selected"; ?>>Clalit</option>
                    <option value="Maccabi" <?php if ($provider === "Maccabi") echo "selected"; ?>>Maccabi</option>
                    <option value="Meuhedet" <?php if ($provider === "Meuhedet") echo "selected"; ?>>Meuhedet</option>
                    <option value="Leumit" <?php if ($provider === "Leumit") echo "selected"; ?>>Leumit</option>
                    <option value="Private Clinic" <?php if ($provider === "Private Clinic") echo "selected"; ?>>Private Clinic</option>
                </select>
            </div>

            <button class="search-btn" type="submit">
                Search Doctors
            </button>

        </form>

    </section>

    <?php if ($searched): ?>

        <div class="section-heading">
            <h2>Search Results</h2>

            <span class="result-count">
                <?php echo $result ? $result->num_rows : 0; ?> Results
            </span>
        </div>

        <section class="doctors-grid">

            <?php if ($result && $result->num_rows > 0): ?>

                <?php while ($row = $result->fetch_assoc()): ?>

                    <article class="doctor-card">

                        <div class="doctor-top">

                            <div class="doctor-icon">🩺</div>

                            <div>
                                <h3>
                                    Dr. <?php echo htmlspecialchars($row["name"]); ?>
                                </h3>

                                <p>
                                    <?php echo htmlspecialchars($row["specialization"] ?: "Not specified"); ?>
                                </p>
                            </div>

                        </div>

                        <p>
                            <strong>Specialization:</strong>
                            <?php echo htmlspecialchars($row["specialization"] ?: "Not specified"); ?>
                        </p>

                        <p>
                            <strong>Healthcare Provider:</strong>
                            <?php echo htmlspecialchars($row["provider_name"] ?: "Not specified"); ?>
                        </p>

                        <a
                            class="book-btn"
                            href="book.php?doctor_id=<?php echo (int) $row["id"]; ?>"
                        >
                            View Calendar and Book
                        </a>

                    </article>

                <?php endwhile; ?>

            <?php else: ?>

                <div class="empty">
                    No doctors match your selected filters.
                </div>

            <?php endif; ?>

        </section>

    <?php endif; ?>

</div>

</body>
</html>