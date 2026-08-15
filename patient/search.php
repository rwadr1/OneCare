<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

$doctor_name = "";
$specialization = "";
$profession = "";
$city = "";
$provider = "";
$searched = false;

$sql = "SELECT doctors.id, users.name, doctors.specialization,
               doctors.profession, doctors.city,
               providers.name AS provider_name
        FROM doctors
        JOIN users ON doctors.user_id = users.id
        JOIN providers ON doctors.provider_id = providers.id
        WHERE 1";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $searched = true;

    $doctor_name = trim($_POST["doctor_name"] ?? "");
    $specialization = $_POST["specialization"] ?? "";
    $profession = $_POST["profession"] ?? "";
    $city = $_POST["city"] ?? "";
    $provider = $_POST["provider"] ?? "";

    if ($doctor_name !== "") {
        $doctor_name_safe = $conn->real_escape_string($doctor_name);
        $sql .= " AND users.name LIKE '%$doctor_name_safe%'";
    }

    if ($specialization !== "") {
        $specialization_safe = $conn->real_escape_string($specialization);
        $sql .= " AND doctors.specialization = '$specialization_safe'";
    }

    if ($profession !== "") {
        $profession_safe = $conn->real_escape_string($profession);
        $sql .= " AND doctors.profession = '$profession_safe'";
    }

    if ($city !== "") {
        $city_safe = $conn->real_escape_string($city);
        $sql .= " AND doctors.city = '$city_safe'";
    }

    if ($provider !== "") {
        $provider_safe = $conn->real_escape_string($provider);
        $sql .= " AND providers.name = '$provider_safe'";
    }
}

$sql .= " ORDER BY users.name ASC";

$result = $conn->query($sql);

include("../includes/header.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Find a Doctor | OneCare</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background:
                radial-gradient(
                    circle at top left,
                    rgba(30, 136, 229, 0.10),
                    transparent 32%
                ),
                linear-gradient(
                    135deg,
                    #eef7ff,
                    #f8fbff 55%,
                    #edf6ff
                );
            margin: 0;
            min-height: 100vh;
            color: #263238;
        }

        .page-wrapper {
            width: min(1180px, 92%);
            margin: 38px auto 60px;
        }

        /*
        |--------------------------------------------------------------------------
        | Hero
        |--------------------------------------------------------------------------
        */
        .hero {
            position: relative;
            overflow: hidden;
            background:
                linear-gradient(
                    135deg,
                    #0d47a1,
                    #1976d2,
                    #42a5f5
                );
            padding: 38px;
            border-radius: 28px;
            box-shadow: 0 16px 40px rgba(21, 101, 192, 0.24);
            margin-bottom: 26px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 30px;
            color: white;
        }

        .hero::before {
            content: "";
            position: absolute;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.10);
            right: -75px;
            top: -120px;
        }

        .hero::after {
            content: "";
            position: absolute;
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
            right: 125px;
            bottom: -125px;
        }

        .hero-content,
        .hero-actions {
            position: relative;
            z-index: 1;
        }

        .hero-badge {
            display: inline-block;
            padding: 7px 13px;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: 999px;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 13px;
        }

        .hero h1 {
            margin: 0;
            font-size: clamp(30px, 4vw, 42px);
            letter-spacing: -1px;
        }

        .hero p {
            margin: 12px 0 0;
            color: rgba(255, 255, 255, 0.90);
            font-size: 16px;
            line-height: 1.7;
            max-width: 650px;
        }

        .hero-actions {
            min-width: 190px;
        }

        .hero-btn {
            display: block;
            text-align: center;
            padding: 13px 20px;
            border-radius: 13px;
            text-decoration: none;
            font-weight: bold;
            background: white;
            color: #1565c0;
            transition: 0.25s ease;
        }

        .hero-btn:hover {
            transform: translateY(-2px);
        }

        /*
        |--------------------------------------------------------------------------
        | Search Form
        |--------------------------------------------------------------------------
        */
        .search-box {
            background: white;
            padding: 28px;
            border-radius: 23px;
            border: 1px solid #e2ecf5;
            box-shadow: 0 10px 28px rgba(21, 101, 192, 0.08);
            margin-bottom: 35px;
        }

        .search-heading {
            margin-bottom: 23px;
        }

        .search-heading h2 {
            color: #16324f;
            margin: 0 0 7px;
            font-size: 25px;
        }

        .search-heading p {
            color: #78909c;
            margin: 0;
            font-size: 14px;
            line-height: 1.6;
        }

        .search-form {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            align-items: end;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #455a64;
            font-size: 13px;
            font-weight: bold;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            height: 48px;
            padding: 0 14px;
            border: 1px solid #d8e4ee;
            border-radius: 13px;
            background: #f8fbfe;
            color: #37474f;
            font-size: 14px;
            outline: none;
            transition: 0.2s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            background: white;
            border-color: #42a5f5;
            box-shadow: 0 0 0 4px rgba(66, 165, 245, 0.12);
        }

        .search-btn {
            grid-column: 1 / -1;
            min-height: 49px;
            border: none;
            border-radius: 13px;
            background:
                linear-gradient(
                    135deg,
                    #1565c0,
                    #1e88e5
                );
            color: white;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.25s ease;
        }

        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 9px 20px rgba(21, 101, 192, 0.22);
        }

        /*
        |--------------------------------------------------------------------------
        | Results
        |--------------------------------------------------------------------------
        */
        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 18px;
        }

        .section-heading h2 {
            color: #16324f;
            margin: 0;
            font-size: 25px;
        }

        .section-heading p {
            color: #78909c;
            margin: 6px 0 0;
            font-size: 14px;
        }

        .result-count {
            background: #e3f2fd;
            color: #1565c0;
            padding: 8px 13px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: bold;
            white-space: nowrap;
        }

        .doctors-grid {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(390px, 1fr));
            gap: 23px;
        }

        .doctor-card {
            position: relative;
            overflow: hidden;
            background: white;
            padding: 25px;
            border-radius: 23px;
            border: 1px solid #e2ecf5;
            box-shadow: 0 10px 28px rgba(21, 101, 192, 0.08);
            transition: 0.25s ease;
        }

        .doctor-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 36px rgba(21, 101, 192, 0.13);
        }

        .doctor-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            height: 6px;
            width: 100%;
            background:
                linear-gradient(
                    90deg,
                    #1e88e5,
                    #64b5f6
                );
        }

        .doctor-top {
            display: flex;
            align-items: center;
            gap: 16px;
            padding-top: 5px;
            margin-bottom: 22px;
        }

        .doctor-icon {
            width: 64px;
            height: 64px;
            min-width: 64px;
            border-radius: 18px;
            background: #e3f2fd;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 29px;
        }

        .doctor-heading {
            flex: 1;
            min-width: 0;
        }

        .doctor-label {
            color: #90a4ae;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .doctor-heading h3 {
            color: #1565c0;
            margin: 6px 0 5px;
            font-size: 21px;
        }

        .doctor-heading p {
            color: #607d8b;
            margin: 0;
            font-size: 14px;
        }

        .doctor-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 13px;
            background: #f8fbfe;
            border-radius: 14px;
            border: 1px solid #edf3f8;
        }

        .detail-item:last-child {
            grid-column: 1 / -1;
        }

        .detail-icon {
            width: 37px;
            height: 37px;
            min-width: 37px;
            border-radius: 11px;
            background: white;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 3px 9px rgba(0, 0, 0, 0.06);
        }

        .detail-label {
            display: block;
            color: #90a4ae;
            font-size: 11px;
            margin-bottom: 4px;
        }

        .detail-item strong {
            display: block;
            color: #37474f;
            font-size: 13px;
            line-height: 1.4;
        }

        .doctor-footer {
            border-top: 1px solid #edf2f7;
            margin-top: 21px;
            padding-top: 18px;
        }

        .book-btn {
            display: block;
            width: 100%;
            padding: 13px 18px;
            border-radius: 12px;
            background: #43a047;
            color: white;
            text-decoration: none;
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            transition: 0.2s ease;
        }

        .book-btn:hover {
            background: #2e7d32;
            transform: translateY(-2px);
        }

        /*
        |--------------------------------------------------------------------------
        | Empty State
        |--------------------------------------------------------------------------
        */
        .empty-state {
            grid-column: 1 / -1;
            background: white;
            padding: 55px 30px;
            border-radius: 24px;
            text-align: center;
            border: 1px solid #e3edf7;
            box-shadow: 0 10px 28px rgba(21, 101, 192, 0.08);
        }

        .empty-icon {
            width: 84px;
            height: 84px;
            margin: 0 auto 18px;
            border-radius: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e3f2fd;
            font-size: 38px;
        }

        .empty-state h3 {
            color: #16324f;
            font-size: 23px;
            margin: 0 0 10px;
        }

        .empty-state p {
            color: #78909c;
            margin: 0;
            line-height: 1.7;
        }

        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */
        @media (max-width: 900px) {
            .search-form {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 760px) {
            .page-wrapper {
                width: 94%;
                margin-top: 24px;
            }

            .hero {
                flex-direction: column;
                align-items: flex-start;
                padding: 28px 23px;
            }

            .hero-actions {
                width: 100%;
            }

            .search-form {
                grid-template-columns: 1fr;
            }

            .doctors-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 520px) {
            .search-box,
            .doctor-card {
                padding: 21px;
            }

            .doctor-details {
                grid-template-columns: 1fr;
            }

            .detail-item:last-child {
                grid-column: auto;
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
            <span class="hero-badge">
                OneCare Patient Portal
            </span>

            <h1>Find a Doctor</h1>

            <p>
                Search for a doctor by name, specialization,
                profession, city, or healthcare provider.
            </p>
        </div>

    </section>

    <section class="search-box">

        <div class="search-heading">
            <h2>Doctor Search</h2>

            <p>
                Select one or more filters to find a suitable doctor.
            </p>
        </div>

        <form method="post" class="search-form">

            <div class="form-group">
                <label>Doctor Name</label>

                <input
                    type="text"
                    name="doctor_name"
                    placeholder="Example: RAWAD"
                    value="<?php echo htmlspecialchars($doctor_name); ?>"
                >
            </div>

            <div class="form-group">
                <label>Specialization</label>

                <select name="specialization">
                    <option value="">All Specializations</option>

                    <option value="Family Medicine"
                        <?php if ($specialization === "Family Medicine") echo "selected"; ?>>
                        Family Medicine
                    </option>

                    <option value="Pediatrics"
                        <?php if ($specialization === "Pediatrics") echo "selected"; ?>>
                        Pediatrics
                    </option>

                    <option value="Cardiology"
                        <?php if ($specialization === "Cardiology") echo "selected"; ?>>
                        Cardiology
                    </option>

                    <option value="Dermatology"
                        <?php if ($specialization === "Dermatology") echo "selected"; ?>>
                        Dermatology
                    </option>

                    <option value="Orthopedics"
                        <?php if ($specialization === "Orthopedics") echo "selected"; ?>>
                        Orthopedics
                    </option>

                    <option value="Neurology"
                        <?php if ($specialization === "Neurology") echo "selected"; ?>>
                        Neurology
                    </option>

                    <option value="Ophthalmology"
                        <?php if ($specialization === "Ophthalmology") echo "selected"; ?>>
                        Ophthalmology
                    </option>

                    <option value="ENT"
                        <?php if ($specialization === "ENT") echo "selected"; ?>>
                        Ear, Nose and Throat
                    </option>

                    <option value="Gynecology"
                        <?php if ($specialization === "Gynecology") echo "selected"; ?>>
                        Gynecology
                    </option>

                    <option value="Dentistry"
                        <?php if ($specialization === "Dentistry") echo "selected"; ?>>
                        Dentistry
                    </option>

                    <option value="Psychiatry"
                        <?php if ($specialization === "Psychiatry") echo "selected"; ?>>
                        Psychiatry
                    </option>

                    <option value="Gastroenterology"
                        <?php if ($specialization === "Gastroenterology") echo "selected"; ?>>
                        Gastroenterology
                    </option>

                    <option value="Endocrinology"
                        <?php if ($specialization === "Endocrinology") echo "selected"; ?>>
                        Endocrinology
                    </option>

                    <option value="Urology"
                        <?php if ($specialization === "Urology") echo "selected"; ?>>
                        Urology
                    </option>

                    <option value="General Surgery"
                        <?php if ($specialization === "General Surgery") echo "selected"; ?>>
                        General Surgery
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label>Profession</label>

                <select name="profession">
                    <option value="">All Professions</option>

                    <option value="Family Doctor"
                        <?php if ($profession === "Family Doctor") echo "selected"; ?>>
                        Family Doctor
                    </option>

                    <option value="Pediatrician"
                        <?php if ($profession === "Pediatrician") echo "selected"; ?>>
                        Pediatrician
                    </option>

                    <option value="Cardiologist"
                        <?php if ($profession === "Cardiologist") echo "selected"; ?>>
                        Cardiologist
                    </option>

                    <option value="Dermatologist"
                        <?php if ($profession === "Dermatologist") echo "selected"; ?>>
                        Dermatologist
                    </option>

                    <option value="Orthopedic Doctor"
                        <?php if ($profession === "Orthopedic Doctor") echo "selected"; ?>>
                        Orthopedic Doctor
                    </option>

                    <option value="Neurologist"
                        <?php if ($profession === "Neurologist") echo "selected"; ?>>
                        Neurologist
                    </option>

                    <option value="Ophthalmologist"
                        <?php if ($profession === "Ophthalmologist") echo "selected"; ?>>
                        Ophthalmologist
                    </option>

                    <option value="ENT Doctor"
                        <?php if ($profession === "ENT Doctor") echo "selected"; ?>>
                        ENT Doctor
                    </option>

                    <option value="Gynecologist"
                        <?php if ($profession === "Gynecologist") echo "selected"; ?>>
                        Gynecologist
                    </option>

                    <option value="Dentist"
                        <?php if ($profession === "Dentist") echo "selected"; ?>>
                        Dentist
                    </option>

                    <option value="Psychiatrist"
                        <?php if ($profession === "Psychiatrist") echo "selected"; ?>>
                        Psychiatrist
                    </option>

                    <option value="Gastroenterologist"
                        <?php if ($profession === "Gastroenterologist") echo "selected"; ?>>
                        Gastroenterologist
                    </option>

                    <option value="Endocrinologist"
                        <?php if ($profession === "Endocrinologist") echo "selected"; ?>>
                        Endocrinologist
                    </option>

                    <option value="Urologist"
                        <?php if ($profession === "Urologist") echo "selected"; ?>>
                        Urologist
                    </option>

                    <option value="Surgeon"
                        <?php if ($profession === "Surgeon") echo "selected"; ?>>
                        Surgeon
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label>City</label>

                <select name="city">
                    <option value="">All Cities</option>

                    <option value="Haifa"
                        <?php if ($city === "Haifa") echo "selected"; ?>>
                        Haifa
                    </option>

                    <option value="Acre"
                        <?php if ($city === "Acre") echo "selected"; ?>>
                        Acre
                    </option>

                    <option value="Nazareth"
                        <?php if ($city === "Nazareth") echo "selected"; ?>>
                        Nazareth
                    </option>

                    <option value="Tel Aviv"
                        <?php if ($city === "Tel Aviv") echo "selected"; ?>>
                        Tel Aviv
                    </option>

                    <option value="Jerusalem"
                        <?php if ($city === "Jerusalem") echo "selected"; ?>>
                        Jerusalem
                    </option>

                    <option value="Karmiel"
                        <?php if ($city === "Karmiel") echo "selected"; ?>>
                        Karmiel
                    </option>

                    <option value="Nahariya"
                        <?php if ($city === "Nahariya") echo "selected"; ?>>
                        Nahariya
                    </option>

                    <option value="Tiberias"
                        <?php if ($city === "Tiberias") echo "selected"; ?>>
                        Tiberias
                    </option>

                    <option value="Sakhnin"
                        <?php if ($city === "Sakhnin") echo "selected"; ?>>
                        Sakhnin
                    </option>

                    <option value="Tamra"
                        <?php if ($city === "Tamra") echo "selected"; ?>>
                        Tamra
                    </option>

                    <option value="Shefa-Amr"
                        <?php if ($city === "Shefa-Amr") echo "selected"; ?>>
                        Shefa-Amr
                    </option>

                    <option value="Kafr Yasif"
                        <?php if ($city === "Kafr Yasif") echo "selected"; ?>>
                        Kafr Yasif
                    </option>

                    <option value="Rameh"
                        <?php if ($city === "Rameh") echo "selected"; ?>>
                        Rameh
                    </option>

                    <option value="Beersheba"
                        <?php if ($city === "Beersheba") echo "selected"; ?>>
                        Beersheba
                    </option>

                    <option value="Eilat"
                        <?php if ($city === "Eilat") echo "selected"; ?>>
                        Eilat
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label>Healthcare Provider</label>

                <select name="provider">
                    <option value="">All Providers</option>

                    <option value="Clalit"
                        <?php if ($provider === "Clalit") echo "selected"; ?>>
                        Clalit
                    </option>

                    <option value="Maccabi"
                        <?php if ($provider === "Maccabi") echo "selected"; ?>>
                        Maccabi
                    </option>

                    <option value="Meuhedet"
                        <?php if ($provider === "Meuhedet") echo "selected"; ?>>
                        Meuhedet
                    </option>

                    <option value="Leumit"
                        <?php if ($provider === "Leumit") echo "selected"; ?>>
                        Leumit
                    </option>

                    <option value="Private Clinic"
                        <?php if ($provider === "Private Clinic") echo "selected"; ?>>
                        Private Clinic
                    </option>
                </select>
            </div>

            <button class="search-btn" type="submit">
                Search Doctors
            </button>

        </form>

    </section>

    <?php if ($searched): ?>

        <section>

            <div class="section-heading">

                <div>
                    <h2>Search Results</h2>

                    <p>
                        Doctors matching your selected search filters.
                    </p>
                </div>

                <span class="result-count">
                    <?php
                    echo $result ? $result->num_rows : 0;
                    ?>
                    Results
                </span>

            </div>

            <div class="doctors-grid">

                <?php if ($result && $result->num_rows > 0): ?>

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <article class="doctor-card">

                            <div class="doctor-top">

                                <div class="doctor-icon">
                                    🩺
                                </div>

                                <div class="doctor-heading">

                                    <span class="doctor-label">
                                        OneCare Doctor
                                    </span>

                                    <h3>
                                        Dr.
                                        <?php
                                        echo htmlspecialchars(
                                            $row["name"]
                                        );
                                        ?>
                                    </h3>

                                    <p>
                                        <?php
                                        echo htmlspecialchars(
                                            $row["specialization"]
                                            ?: "Not specified"
                                        );
                                        ?>
                                    </p>

                                </div>

                            </div>

                            <div class="doctor-details">

                                <div class="detail-item">

                                    <div class="detail-icon">
                                        🩺
                                    </div>

                                    <div>
                                        <span class="detail-label">
                                            Specialization
                                        </span>

                                        <strong>
                                            <?php
                                            echo htmlspecialchars(
                                                $row["specialization"]
                                                ?: "Not specified"
                                            );
                                            ?>
                                        </strong>
                                    </div>

                                </div>

                                <div class="detail-item">

                                    <div class="detail-icon">
                                        👨‍⚕️
                                    </div>

                                    <div>
                                        <span class="detail-label">
                                            Profession
                                        </span>

                                        <strong>
                                            <?php
                                            echo htmlspecialchars(
                                                $row["profession"]
                                                ?: "Not specified"
                                            );
                                            ?>
                                        </strong>
                                    </div>

                                </div>

                                <div class="detail-item">

                                    <div class="detail-icon">
                                        📍
                                    </div>

                                    <div>
                                        <span class="detail-label">
                                            City
                                        </span>

                                        <strong>
                                            <?php
                                            echo htmlspecialchars(
                                                $row["city"]
                                                ?: "Not specified"
                                            );
                                            ?>
                                        </strong>
                                    </div>

                                </div>

                                <div class="detail-item">

                                    <div class="detail-icon">
                                        🏥
                                    </div>

                                    <div>
                                        <span class="detail-label">
                                            Healthcare Provider
                                        </span>

                                        <strong>
                                            <?php
                                            echo htmlspecialchars(
                                                $row["provider_name"]
                                                ?: "Not specified"
                                            );
                                            ?>
                                        </strong>
                                    </div>

                                </div>

                            </div>

                            <div class="doctor-footer">

                                <a
                                    class="book-btn"
                                    href="doctor_calendar.php?doctor_id=<?php echo (int) $row["id"]; ?>"
                                >
                                    View Calendar and Book
                                </a>

                            </div>

                        </article>

                    <?php endwhile; ?>

                <?php else: ?>

                    <div class="empty-state">

                        <div class="empty-icon">
                            🔍
                        </div>

                        <h3>No Doctors Found</h3>

                        <p>
                            No doctors match your selected filters.
                            Try changing or removing one of the filters.
                        </p>

                    </div>

                <?php endif; ?>

            </div>

        </section>

    <?php endif; ?>

</div>

</body>
</html>