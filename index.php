<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>OneCare — Book your doctor</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">

<style>
    :root{
        --bg: #F3F7FE;
        --surface: #FFFFFF;
        --ink: #16213F;
        --muted: #5F718B;
        --teal: #2962D9;
        --teal-deep: #16296B;
        --teal-tint: #E7F0FF;
        --amber: #F2A94B;
        --line: #DCE6F7;
        --shadow: 0 24px 50px -20px rgba(22, 41, 107, .28);
    }

    *{ box-sizing: border-box; }

    body{
        margin: 0;
        font-family: 'Inter', sans-serif;
        color: var(--ink);
        background: var(--bg);
        -webkit-font-smoothing: antialiased;
    }

    a{ color: inherit; }

    .mono{ font-family: 'IBM Plex Mono', monospace; letter-spacing: .02em; }

    /* ---------- topbar ---------- */
    .topbar{
        display: flex;
        align-items: center;
        justify-content: space-between;
        max-width: 1280px;
        margin: 0 auto;
        padding: 28px 7% 0;
    }

    .brand{
        display: flex;
        align-items: center;
        gap: 10px;
        font-family: 'Sora', sans-serif;
        font-weight: 700;
        font-size: 20px;
        color: var(--teal-deep);
    }

    .brand-mark{
        width: 34px;
        height: 34px;
        border-radius: 10px;
        background: var(--teal);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 18px;
        transform: rotate(-6deg);
    }

    .topbar nav{
        display: flex;
        align-items: center;
        gap: 22px;
    }

    .nav-link{
        text-decoration: none;
        font-weight: 600;
        font-size: 15px;
        color: var(--ink);
    }

    .nav-cta{
        text-decoration: none;
        font-weight: 600;
        font-size: 15px;
        color: white;
        background: var(--teal-deep);
        padding: 10px 20px;
        border-radius: 999px;
    }

    /* ---------- hero ---------- */
    .hero{
        position: relative;
        max-width: 1280px;
        margin: 0 auto;
        padding: 64px 7% 40px;
        display: grid;
        grid-template-columns: 1.05fr .95fr;
        gap: 60px;
        align-items: center;
        overflow: hidden;
    }

    .glow{
        position: absolute;
        width: 560px;
        height: 560px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(41,98,217,.10), transparent 70%);
        top: -180px;
        left: 40%;
        z-index: 0;
        pointer-events: none;
    }

    .eyebrow{
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 7px 14px;
        border-radius: 999px;
        background: var(--teal-tint);
        color: var(--teal-deep);
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 22px;
    }

    .eyebrow::before{
        content: "";
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--amber);
    }

    .hero-copy h1{
        font-family: 'Sora', sans-serif;
        font-weight: 800;
        font-size: 56px;
        line-height: 1.06;
        letter-spacing: -.015em;
        margin: 0;
        color: var(--teal-deep);
        max-width: 640px;
    }

    .hero-copy p.lead{
        margin: 22px 0 0;
        font-size: 18px;
        line-height: 1.7;
        color: var(--muted);
        max-width: 480px;
    }

    .cta-row{
        display: flex;
        flex-wrap: wrap;
        gap: 14px;
        margin-top: 32px;
    }

    .btn{
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 15px 28px;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 600;
        font-size: 16px;
        border: 2px solid transparent;
        transition: transform .18s ease, box-shadow .18s ease;
    }

    .btn:hover{ transform: translateY(-2px); }
    .btn:focus-visible{ outline: 3px solid var(--amber); outline-offset: 2px; }

    .btn-primary{
        color: white;
        background: var(--teal);
        box-shadow: 0 14px 26px -10px rgba(41,98,217,.55);
    }

    .btn-ghost{
        color: var(--teal-deep);
        background: transparent;
        border-color: var(--line);
    }

    /* ecg divider */
    .ecg{
        margin-top: 38px;
        width: 100%;
        max-width: 420px;
        height: 34px;
        color: var(--teal);
        opacity: .85;
    }

    .ecg path{
        stroke-dasharray: 340;
        stroke-dashoffset: 340;
        animation: draw 3.2s ease-in-out infinite;
    }

    @keyframes draw{
        0%{ stroke-dashoffset: 340; }
        55%{ stroke-dashoffset: 0; }
        100%{ stroke-dashoffset: -340; }
    }

    @media (prefers-reduced-motion: reduce){
        .ecg path{ animation: none; stroke-dashoffset: 0; }
    }

    .stats-row{
        display: flex;
        gap: 34px;
        margin-top: 30px;
        flex-wrap: wrap;
    }

    .stat b{
        display: block;
        font-family: 'IBM Plex Mono', monospace;
        font-size: 22px;
        font-weight: 600;
        color: var(--teal-deep);
    }

    .stat span{
        font-size: 13px;
        color: var(--muted);
    }

    /* ---------- ticket stack ---------- */
    .hero-visual{
        position: relative;
        z-index: 1;
        height: 460px;
    }

    .ticket-stack{
        position: relative;
        width: 100%;
        max-width: 360px;
        height: 100%;
        margin: 0 auto;
    }

    .ticket{
        position: absolute;
        width: 100%;
        left: 0;
        background: var(--surface);
        border: 1px solid var(--line);
        border-radius: 20px;
        padding: 22px;
        box-shadow: var(--shadow);
    }

    .ticket-label{
        font-family: 'IBM Plex Mono', monospace;
        font-size: 12px;
        color: var(--teal);
        font-weight: 600;
        letter-spacing: .04em;
    }

    .ticket h3{
        font-family: 'Sora', sans-serif;
        font-size: 18px;
        margin: 6px 0 12px;
        color: var(--ink);
    }

    .ticket-1{
        top: 0;
        transform: rotate(-7deg);
        z-index: 1;
    }

    .ticket-1 .chip{
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: var(--teal-tint);
        color: var(--teal-deep);
        border-radius: 999px;
        padding: 8px 14px;
        font-size: 13px;
        font-weight: 600;
    }

    .ticket-2{
        top: 70px;
        transform: rotate(5deg);
        z-index: 2;
    }

    .slot-row{
        display: flex;
        gap: 8px;
    }

    .slot{
        flex: 1;
        text-align: center;
        padding: 9px 4px;
        border-radius: 8px;
        background: var(--teal-tint);
        font-family: 'IBM Plex Mono', monospace;
        font-size: 12px;
        color: var(--teal-deep);
        font-weight: 600;
    }

    .slot.picked{
        background: var(--teal);
        color: white;
    }

    .ticket-3{
        top: 150px;
        transform: rotate(0deg);
        z-index: 3;
        display: flex;
        gap: 16px;
        align-items: center;
    }

    .doctor-frame{
        flex-shrink: 0;
        width: 108px;
        height: 108px;
        border-radius: 50%;
        overflow: hidden;
        border: 3px solid var(--teal-tint);
        outline: 2px solid var(--teal);
        outline-offset: 3px;
    }

    .doctor-frame img{
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: 50% 12%;
        display: block;
    }

    .ticket-3 .confirmed{
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 6px;
        font-size: 12px;
        font-weight: 600;
        color: var(--teal-deep);
    }

    .ticket-3 .confirmed::before{
        content: "✓";
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: var(--teal);
        color: white;
        font-size: 10px;
    }

    /* ---------- points strip ---------- */
    .points{
        max-width: 1280px;
        margin: 30px auto 0;
        padding: 0 7%;
        display: flex;
        flex-wrap: wrap;
        gap: 26px;
    }

    .point{
        display: flex;
        align-items: center;
        gap: 9px;
        font-size: 15px;
        font-weight: 600;
        color: var(--teal-deep);
    }

    .point-icon{
        width: 30px;
        height: 30px;
        border-radius: 9px;
        background: var(--teal-tint);
        color: var(--teal);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
    }

    /* ---------- cta band ---------- */
    .cta-band{
        max-width: 1080px;
        margin: 90px auto 60px;
        padding: 54px 7%;
        text-align: center;
        background: linear-gradient(135deg, var(--teal-deep), var(--teal));
        border-radius: 28px;
        color: white;
    }

    .cta-band h2{
        font-family: 'Sora', sans-serif;
        font-size: 34px;
        margin: 0 0 12px;
    }

    .cta-band p{
        color: rgba(255,255,255,.82);
        font-size: 16px;
        margin: 0 auto 26px;
        max-width: 440px;
    }

    .cta-band .btn-primary{
        background: white;
        color: var(--teal-deep);
        box-shadow: none;
    }

    /* ---------- responsive ---------- */
    @media (max-width: 1000px){
        .hero{ grid-template-columns: 1fr; padding-top: 40px; }
        .hero-copy h1{ font-size: 42px; max-width: 100%; }
        .hero-copy p.lead{ max-width: 100%; }
        .hero-visual{ height: 420px; margin-top: 10px; }
    }

    @media (max-width: 560px){
        .topbar{ padding: 22px 6% 0; }
        .hero{ padding: 30px 6% 10px; }
        .hero-copy h1{ font-size: 33px; }
        .btn{ width: 100%; justify-content: center; }
        .stats-row{ gap: 22px; }
        .ticket-stack{ max-width: 300px; }
        .ticket{ padding: 18px; }
        .cta-band{ margin: 60px 5% 40px; padding: 40px 6%; border-radius: 22px; }
        .cta-band h2{ font-size: 26px; }
    }
</style>
</head>
<body>

<header class="topbar">
    <div class="brand">
        <span class="brand-mark">+</span>
        OneCare
    </div>
    <nav>
        <a class="nav-link" href="login.php">Login</a>
        <a class="nav-cta" href="signup.php">Sign up</a>
    </nav>
</header>

<main class="hero">
    <div class="glow"></div>

    <section class="hero-copy">
        <div class="eyebrow">OneCare · Smart Healthcare Platform</div>

        <h1>Healthcare appointments made simple</h1>

        <p class="lead">
            Search for doctors by specialization, provider and location,
            choose an available appointment time and manage your medical visits
            through one simple platform.
        </p>

        <svg class="ecg" viewBox="0 0 340 34" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M0 17 H110 L122 4 L134 30 L146 17 H340" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>

        <div class="stats-row">
        <div class="stat">
            <b>Easy Access</b>
            <span>Standard and senior-friendly experience</span>
        </div>

        <div class="stat">
            <b>Doctor Search</b>
            <span>Search by specialization and provider</span>
        </div>

        <div class="stat">
            <b>OneCare</b>
            <span>Appointments and visit summaries</span>
        </div>
    </div>
    </section>

    <section class="hero-visual">
        <div class="ticket-stack">

            <article class="ticket ticket-1">
                <div class="ticket-label">01 — SEARCH</div>
                <h3>Find your specialist</h3>
                <span class="chip">🩺 Search by specialization and provider</span>
            </article>

            <article class="ticket ticket-2">
                <div class="ticket-label">02 — HOLD YOUR SLOT</div>
                <h3>Choose an available time</h3>
                <div class="slot-row">
                    <div class="slot">10:15</div>
                    <div class="slot picked">11:00</div>
                    <div class="slot">14:30</div>
                </div>
            </article>

            <article class="ticket ticket-3">
                <div class="doctor-frame">
                    <img src="assets/images/doctor.png" alt="Your doctor">
                </div>
                <div>
                    <div class="ticket-label">03 — MEET YOUR DOCTOR</div>
                    <h3 style="margin-bottom:0;">Appointment Confirmed</h3>
                <div class="confirmed">Your visit is successfully booked</div>
                </div>
            </article>

        </div>
    </section>
</main>

<div class="points">
    <div class="point"><span class="point-icon">✓</span>Secure and private</div>
    <div class="point"><span class="point-icon">⚡</span>Quick booking</div>
    <div class="point"><span class="point-icon">＋</span>Trusted doctors</div>
</div>

<footer class="cta-band">
    <h2>Ready when you are</h2>
    <p>Create your account and book your first visit in under two minutes.</p>
    <a class="btn btn-primary" href="signup.php">Create your account</a>
</footer>

</body>
</html>
