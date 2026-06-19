<?php
// Mengecek session. Jika session belum aktif, maka session akan dimulai agar status login user bisa dibaca.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Menyimpan nama aplikasi yang akan ditampilkan pada title, navbar, footer, dan konten landing page.
$appName = "NadiOS";
// Mengecek apakah user sudah login atau belum untuk menentukan tombol dan teks yang tampil.
$isLoggedIn = isset($_SESSION["user_id"]);
// Mengambil nama lengkap user dari session. Jika belum tersedia, sistem memakai nama default User.
$fullName = $_SESSION["full_name"] ?? "User";

// Fungsi untuk mengamankan output HTML agar data tidak menyebabkan XSS.
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

// Mengambil nama depan user agar sapaan di hero section terasa lebih personal.
$fullNameParts = preg_split('/\s+/', trim($fullName));
$firstName = $fullNameParts[0] ?? "User";

// Menentukan lokasi file video demo yang akan ditampilkan pada landing page.
$demoVideoUrl = "assets/Demo Aplikasi NadiOS.mp4";
// Membuat path fisik video untuk mengecek apakah file video demo tersedia di folder assets.
$demoVideoPath = __DIR__ . "/" . $demoVideoUrl;
// Mengecek ketersediaan video. Jika tersedia, video ditampilkan; jika tidak, placeholder ditampilkan.
$hasDemoVideo = file_exists($demoVideoPath);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($appName); ?> - Personal Productivity System</title>

    <style>
        /* Reset dasar agar ukuran elemen lebih mudah dikontrol. */
        * {
            box-sizing: border-box;
        }

        /* Variabel warna, shadow, radius, dan tema visual landing page. */
        :root {
            --bg: #f7f8ff;
            --panel: rgba(255, 255, 255, 0.88);
            --ink: #172033;
            --muted: #6b7280;
            --line: #e3e8f2;
            --blue: #2563eb;
            --blue-dark: #1d4ed8;
            --blue-soft: #eaf1ff;
            --green-soft: #e9fbf1;
            --yellow-soft: #fff7df;
            --pink-soft: #fff1f7;
            --purple-soft: #f1edff;
            --shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
            --radius: 24px;
        }

        /* Pengaturan dasar halaman, background, dan font utama. */
        html,
        body {
            margin: 0;
            min-height: 100%;
            overflow-x: hidden;
            font-family: Arial, Helvetica, sans-serif;
            background:
                radial-gradient(circle at 14% 8%, rgba(37, 99, 235, 0.12), transparent 30%),
                radial-gradient(circle at 86% 6%, rgba(168, 85, 247, 0.10), transparent 28%),
                radial-gradient(circle at 76% 90%, rgba(34, 197, 94, 0.10), transparent 32%),
                var(--bg);
            color: var(--ink);
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        /* Container halaman utama landing page. */
        .page {
            min-height: 100vh;
        }

        /* Pembatas lebar konten agar layout tetap rapi di layar besar. */
        .container {
            width: min(1080px, calc(100% - 40px));
            margin: 0 auto;
        }

        /* Navbar atas untuk logo dan tombol login/register/dashboard. */
        .navbar {
            padding: 22px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        /* Area branding aplikasi pada navbar. */
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-mark {
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            border-radius: 18px;
            background: linear-gradient(135deg, #60a5fa, #7c3aed);
            color: #ffffff;
            font-weight: 900;
            box-shadow: 0 12px 30px rgba(37, 99, 235, 0.22);
        }

        .brand-title {
            margin: 0;
            font-size: 26px;
            font-weight: 900;
            letter-spacing: -0.05em;
        }

        .brand-subtitle {
            margin: 3px 0 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.5;
            font-weight: 700;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* Style umum untuk semua tombol aksi. */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 15px;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 900;
            border: none;
            cursor: pointer;
            transition: 0.18s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            background: var(--blue);
            color: #ffffff;
            box-shadow: 0 14px 28px rgba(37, 99, 235, 0.22);
        }

        .btn-primary:hover {
            background: var(--blue-dark);
        }

        .btn-secondary {
            background: #ffffff;
            color: var(--ink);
            border: 1px solid var(--line);
        }

        .btn-secondary:hover {
            background: #f8fafc;
        }

        /* Hero section untuk memperkenalkan aplikasi dan CTA utama. */
        .hero {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 18px;
            align-items: stretch;
            padding: 28px 0 18px;
        }

        .hero-main,
        .hero-side,
        .section-card,
        .feature-card,
        .flow-card,
        .cta-card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            backdrop-filter: blur(14px);
        }

        .hero-main {
            padding: clamp(26px, 5vw, 44px);
            position: relative;
            overflow: hidden;
        }

        .hero-main::after {
            content: "";
            position: absolute;
            right: -60px;
            top: -60px;
            width: 220px;
            height: 220px;
            border-radius: 999px;
            background: linear-gradient(135deg, rgba(96, 165, 250, 0.25), rgba(168, 85, 247, 0.18));
        }

        .label {
            display: inline-flex;
            width: fit-content;
            padding: 8px 12px;
            border-radius: 999px;
            background: var(--blue-soft);
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 16px;
        }

        .hero h1 {
            margin: 0;
            max-width: 800px;
            font-size: clamp(42px, 8vw, 82px);
            line-height: 0.95;
            letter-spacing: -0.075em;
        }

        .hero p {
            margin: 18px 0 0;
            max-width: 720px;
            color: var(--muted);
            font-size: 16px;
            line-height: 1.75;
        }

        .hero-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 26px;
        }

        .hero-side {
            padding: 22px;
            display: grid;
            align-content: center;
            gap: 14px;
        }

        .info-mini {
            padding: 17px;
            border-radius: 20px;
            background: #ffffff;
            border: 1px solid var(--line);
        }

        .info-mini strong {
            display: block;
            font-size: 18px;
            letter-spacing: -0.03em;
            margin-bottom: 6px;
        }

        .info-mini span {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.55;
        }

        /* Grid ringkasan keunggulan utama aplikasi. */
        .stats-strip {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin: 18px auto;
        }

        .stat-box {
            display: grid;
            grid-template-columns: auto 1fr;
            align-items: start;
            gap: 14px;
            background: #ffffff;
            border: 1px solid var(--line);
            border-radius: 22px;
            padding: 18px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.055);
            min-height: 132px;
        }

        .stat-code {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 15px;
            background: var(--blue-soft);
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 900;
            margin: 0;
        }

        .stat-box:nth-child(2) .stat-code { background: var(--purple-soft); color: #5b21b6; }
        .stat-box:nth-child(3) .stat-code { background: var(--green-soft); color: #166534; }
        .stat-box:nth-child(4) .stat-code { background: var(--yellow-soft); color: #92400e; }

        .stat-box strong {
            display: block;
            font-size: 18px;
            letter-spacing: -0.03em;
            margin-bottom: 6px;
        }

        .stat-box span {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.55;
            max-width: 380px;
            display: block;
        }

        /* Spacing antar section landing page. */
        .section {
            padding: 14px 0;
        }

        .section-card {
            padding: 26px;
        }

        .section-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
            margin-bottom: 18px;
        }

        .section-head h2 {
            margin: 0;
            font-size: clamp(28px, 4vw, 42px);
            letter-spacing: -0.065em;
        }

        .section-head p {
            margin: 8px 0 0;
            color: var(--muted);
            line-height: 1.65;
            max-width: 720px;
        }

        /* Grid fitur utama NadiOS. */
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .feature-card {
            padding: 18px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.055);
            background: #ffffff;
        }

        .feature-code {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            border-radius: 15px;
            background: var(--blue-soft);
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 900;
            margin-bottom: 14px;
        }

        .feature-card:nth-child(2) .feature-code { background: var(--purple-soft); color: #5b21b6; }
        .feature-card:nth-child(3) .feature-code { background: var(--green-soft); color: #166534; }
        .feature-card:nth-child(4) .feature-code { background: var(--yellow-soft); color: #92400e; }
        .feature-card:nth-child(5) .feature-code { background: var(--pink-soft); color: #9d174d; }
        .feature-card:nth-child(6) .feature-code { background: var(--blue-soft); color: #1d4ed8; }

        .feature-card h3 {
            margin: 0 0 8px;
            font-size: 18px;
            letter-spacing: -0.035em;
        }

        .feature-card p {
            margin: 0;
            color: var(--muted);
            line-height: 1.65;
            font-size: 14px;
        }

        /* Grid alur kerja aplikasi dari goal sampai evaluasi. */
        .flow-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .flow-card {
            padding: 18px;
            background: #ffffff;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.055);
        }

        .flow-number {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            border-radius: 13px;
            background: #111827;
            color: #ffffff;
            font-size: 13px;
            font-weight: 900;
            margin-bottom: 12px;
        }

        .flow-card h3 {
            margin: 0 0 8px;
            font-size: 17px;
            letter-spacing: -0.03em;
        }

        .flow-card p {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
            font-size: 14px;
        }

        /* Card untuk menampilkan video demo aplikasi. */
        .video-card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            backdrop-filter: blur(14px);
            padding: 26px;
        }

        .video-layout {
            display: grid;
            grid-template-columns: 1fr;
            gap: 18px;
            align-items: stretch;
        }

        .video-frame {
            position: relative;
            width: 100%;
            overflow: hidden;
            border-radius: 22px;
            border: 1px solid var(--line);
            background: #0f172a;
            aspect-ratio: 16 / 9;
            box-shadow: 0 18px 42px rgba(15, 23, 42, 0.10);
        }

        .video-frame video {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            background: #0f172a;
        }

        .video-placeholder {
            min-height: 340px;
            display: grid;
            place-items: center;
            padding: 28px;
            text-align: center;
            color: #ffffff;
            background:
                radial-gradient(circle at top left, rgba(96, 165, 250, 0.32), transparent 32%),
                radial-gradient(circle at bottom right, rgba(168, 85, 247, 0.25), transparent 28%),
                #0f172a;
        }

        .video-placeholder-content {
            max-width: 520px;
        }

        .video-placeholder-content strong {
            display: block;
            font-size: 26px;
            letter-spacing: -0.05em;
            margin-bottom: 10px;
        }

        .video-placeholder-content span {
            display: block;
            color: #cbd5e1;
            line-height: 1.65;
            font-size: 14px;
        }
/* Section call to action di bagian akhir landing page. */
.cta {
            padding: 18px 0 42px;
        }

        .cta-card {
            padding: 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            background:
                linear-gradient(135deg, rgba(255,255,255,0.92), rgba(234,241,255,0.88));
        }

        .cta-card h2 {
            margin: 0;
            font-size: clamp(26px, 4vw, 40px);
            letter-spacing: -0.06em;
        }

        .cta-card p {
            margin: 8px 0 0;
            color: var(--muted);
            line-height: 1.6;
        }

        /* Footer landing page. */
        .footer {
            padding: 18px 0 28px;
            color: var(--muted);
            font-size: 13px;
            text-align: center;
        }



        /* Animasi halus untuk membuat landing page terasa lebih hidup. */
        /* Animasi elemen muncul dari bawah. */
        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(18px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Animasi dekorasi hero agar bergerak pelan. */
        @keyframes floatSoft {
            0%, 100% {
                transform: translate3d(0, 0, 0) scale(1);
            }
            50% {
                transform: translate3d(-12px, 14px, 0) scale(1.05);
            }
        }

        /* Animasi glow pada logo brand mark. */
        @keyframes glowPulse {
            0%, 100% {
                box-shadow: 0 12px 30px rgba(37, 99, 235, 0.22);
            }
            50% {
                box-shadow: 0 18px 42px rgba(124, 58, 237, 0.28);
            }
        }

        /* Animasi kilau halus pada video frame. */
        @keyframes lineShine {
            from {
                transform: translateX(-120%);
            }
            to {
                transform: translateX(120%);
            }
        }

        .brand-mark {
            animation: glowPulse 3.6s ease-in-out infinite;
        }

        .hero-main::after {
            animation: floatSoft 7s ease-in-out infinite;
        }

        .hero-main,
        .hero-side,
        .stat-box,
        .video-card,
        .section-card,
        .cta-card {
            animation: fadeUp 0.75s ease both;
        }

        .hero-side { animation-delay: 0.08s; }
        .stat-box:nth-child(1) { animation-delay: 0.10s; }
        .stat-box:nth-child(2) { animation-delay: 0.16s; }
        .stat-box:nth-child(3) { animation-delay: 0.22s; }
        .stat-box:nth-child(4) { animation-delay: 0.28s; }
        .video-card { animation-delay: 0.12s; }

        .info-mini,
        .feature-card,
        .flow-card,
        .stat-box,
        .cta-card,
        .video-card,
        .section-card {
            transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease;
        }

        .info-mini:hover,
        .feature-card:hover,
        .flow-card:hover,
        .stat-box:hover {
            transform: translateY(-4px);
            box-shadow: 0 18px 42px rgba(15, 23, 42, 0.09);
            border-color: rgba(37, 99, 235, 0.20);
        }

        .btn:active {
            transform: translateY(0) scale(0.98);
        }

        .label,
        .stat-code,
        .feature-code,
        .flow-number {
            transition: transform 0.22s ease;
        }

        .label:hover,
        .stat-box:hover .stat-code,
        .feature-card:hover .feature-code,
        .flow-card:hover .flow-number {
            transform: scale(1.06);
        }

        .video-frame::after {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            background: linear-gradient(110deg, transparent 0%, rgba(255, 255, 255, 0.16) 45%, transparent 70%);
            transform: translateX(-120%);
            animation: lineShine 4.8s ease-in-out infinite;
        }

        .js .reveal {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.65s ease, transform 0.65s ease;
        }

        .js .reveal.is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Mengurangi animasi untuk user yang mengaktifkan preferensi reduced motion. */
        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                scroll-behavior: auto !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* Responsive layout untuk tablet dan layar sedang. */
        @media (max-width: 980px) {
            .hero,
            .feature-grid,
            .flow-grid {
                grid-template-columns: 1fr;
            }

            .stats-strip {
                grid-template-columns: 1fr;
            }

            .stat-box {
                grid-template-columns: 1fr;
            }

            .video-layout {
                grid-template-columns: 1fr;
            }

            .cta-card {
                display: grid;
            }
        }

        /* Responsive layout untuk layar mobile. */
        @media (max-width: 620px) {
            .navbar {
                display: grid;
                align-items: start;
            }

            .nav-actions,
            .hero-actions {
                width: 100%;
            }

            .btn {
                width: 100%;
            }

            .stats-strip {
                grid-template-columns: 1fr;
            }

            .section-card,
            .cta-card {
                padding: 20px;
            }
        }


        /* Override tambahan khusus mobile agar hero tidak terlalu besar dan tidak terlihat terpotong di layar HP. */
        @media (max-width: 620px) {
            .container {
                width: calc(100% - 28px);
            }

            .navbar {
                padding: 18px 0 12px;
                gap: 14px;
            }

            .brand {
                align-items: flex-start;
            }

            .brand-mark {
                width: 44px;
                height: 44px;
                border-radius: 16px;
                flex: 0 0 auto;
            }

            .brand-title {
                font-size: 24px;
                letter-spacing: -0.04em;
            }

            .brand-subtitle {
                font-size: 12px;
                line-height: 1.35;
            }

            .nav-actions,
            .hero-actions {
                display: grid;
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .hero {
                padding: 18px 0 12px;
                gap: 14px;
            }

            .hero-main,
            .hero-side,
            .section-card,
            .video-card,
            .cta-card {
                border-radius: 22px;
            }

            .hero-main {
                padding: 24px 22px;
            }

            .hero-main::after {
                width: 150px;
                height: 150px;
                right: -78px;
                top: -58px;
                opacity: 0.82;
            }

            .label {
                max-width: 100%;
                white-space: normal;
                line-height: 1.25;
                font-size: 10.5px;
                letter-spacing: 0.055em;
                margin-bottom: 14px;
            }

            .hero h1 {
                max-width: 100%;
                font-size: clamp(32px, 10.5vw, 42px);
                line-height: 1.04;
                letter-spacing: -0.055em;
                overflow-wrap: break-word;
                word-break: normal;
            }

            .hero p {
                margin-top: 14px;
                font-size: 14px;
                line-height: 1.65;
            }

            .btn {
                min-height: 50px;
                padding: 13px 15px;
            }

            .hero-side {
                padding: 18px;
            }

            .stats-strip,
            .feature-grid,
            .flow-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .section {
                padding: 10px 0;
            }

            .section-head {
                display: grid;
                gap: 10px;
                margin-bottom: 14px;
            }

            .section-head h2,
            .cta-card h2 {
                font-size: clamp(26px, 8vw, 34px);
                letter-spacing: -0.05em;
            }

            .section-head p,
            .cta-card p {
                font-size: 14px;
                line-height: 1.6;
            }

            .video-card {
                padding: 20px;
            }

            .video-placeholder {
                min-height: 220px;
                padding: 22px;
            }

            .video-placeholder-content strong {
                font-size: 22px;
            }
        }

        /* Penyesuaian ekstra untuk HP yang lebarnya sangat kecil. */
        @media (max-width: 380px) {
            .container {
                width: calc(100% - 22px);
            }

            .hero-main {
                padding: 22px 18px;
            }

            .hero h1 {
                font-size: 31px;
                letter-spacing: -0.045em;
            }

            .brand-title {
                font-size: 22px;
            }
        }



        /* Fix final navbar mobile: tombol Register dan Login dibuat rapi 2 kolom di HP. */
        @media (max-width: 620px) {
            .navbar {
                display: grid;
                grid-template-columns: 1fr;
                row-gap: 16px;
                align-items: stretch;
                padding: 18px 0 16px;
            }

            .navbar .brand {
                width: 100%;
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .navbar .nav-actions {
                width: 100%;
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .navbar .nav-actions .btn {
                width: 100%;
                min-height: 48px;
                padding: 12px 10px;
                border-radius: 16px;
                font-size: 14px;
                line-height: 1.2;
            }

            .navbar .nav-actions .btn-primary {
                box-shadow: 0 12px 24px rgba(37, 99, 235, 0.18);
            }

            .hero-actions {
                width: 100%;
                display: grid;
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .hero-actions .btn {
                width: 100%;
            }
        }

        /* Untuk layar sangat sempit, tombol navbar dibuat turun agar tidak sesak. */
        @media (max-width: 340px) {
            .navbar .nav-actions {
                grid-template-columns: 1fr;
            }
        }

    </style>
</head>
<body>

<!-- Container utama landing page. -->
<div class="page">

    <!-- Header berisi navbar dan branding aplikasi. -->
    <header class="container">
        <!-- Navbar untuk menampilkan logo dan tombol autentikasi. -->
        <nav class="navbar">
            <div class="brand">
                <div class="brand-mark">NO</div>

                <div>
                    <h2 class="brand-title"><?php echo e($appName); ?></h2>
                    <p class="brand-subtitle">Personal Productivity System</p>
                </div>
            </div>

            <div class="nav-actions">
                <?php if ($isLoggedIn) { ?>
                    <a href="dashboard.php" class="btn btn-primary">Dashboard</a>
                    <a href="logout.php" class="btn btn-secondary">Logout</a>
                <?php } else { ?>
                    <a href="register.php" class="btn btn-primary">Register</a>
                    <a href="login.php" class="btn btn-secondary">Login</a>
                <?php } ?>
            </div>
        </nav>
    </header>

    <!-- Konten utama landing page. -->
    <main>
        <!-- Hero section untuk memperkenalkan NadiOS kepada user. -->
        <section class="container hero">
            <div class="hero-main">
                <span class="label">Personal Productivity System</span>

                <?php if ($isLoggedIn) { ?>
                    <h1>Halo, <?php echo e($firstName); ?>. Kelola hidupmu dengan lebih rapi.</h1>
                <?php } else { ?>
                    <h1>Bangun sistem produktivitas pribadi dalam satu tempat.</h1>
                <?php } ?>

                <p>
                    NadiOS adalah aplikasi web berbasis PHP dan MySQL untuk membantu pengguna
                    mengelola tujuan, project, tugas, kebiasaan, catatan harian, dan bantuan AI
                    dalam satu sistem yang terstruktur.
                </p>

                <div class="hero-actions">
                    <?php if ($isLoggedIn) { ?>
                        <a href="dashboard.php" class="btn btn-primary">Masuk Dashboard</a>
                        <a href="ai_assistant.php" class="btn btn-secondary">Tanya Nara AI</a>
                        <a href="#video-demo" class="btn btn-secondary">Lihat Video</a>
                    <?php } else { ?>
                        <a href="register.php" class="btn btn-primary">Mulai Register</a>
                        <a href="login.php" class="btn btn-secondary">Login</a>
                        <a href="#video-demo" class="btn btn-secondary">Lihat Video</a>
                    <?php } ?>
                </div>
            </div>

            <!-- Panel informasi singkat tentang aplikasi. -->
            <aside class="hero-side">
                <div class="info-mini">
                    <strong>Apa itu NadiOS?</strong>
                    <span>
                        NadiOS adalah ruang kerja digital untuk mengatur aktivitas pribadi agar lebih terarah,
                        bukan hanya daftar catatan biasa.
                    </span>
                </div>

                <div class="info-mini">
                    <strong>Untuk siapa?</strong>
                    <span>
                        Cocok untuk pengguna yang ingin menyusun target, mengelola tugas, membangun habit,
                        dan melihat progres harian secara sederhana.
                    </span>
                </div>

                <div class="info-mini">
                    <strong>Alur utama</strong>
                    <span>
                        Buat goal, pecah menjadi project, turunkan menjadi task, pantau habit,
                        lalu refleksikan melalui journal.
                    </span>
                </div>
            </aside>
        </section>

        <!-- Ringkasan nilai utama aplikasi. -->
        <section class="container stats-strip">
            <div class="stat-box">
                <div class="stat-code">01</div>
                <strong>Terstruktur</strong>
                <span>Data produktivitas dibagi ke Goals, Projects, Tasks, Habits, dan Journal.</span>
            </div>

            <div class="stat-box">
                <div class="stat-code">02</div>
                <strong>Personal</strong>
                <span>Setiap pengguna login dengan akun masing-masing untuk mengakses dashboard.</span>
            </div>

            <div class="stat-box">
                <div class="stat-code">03</div>
                <strong>Terukur</strong>
                <span>Dashboard menampilkan ringkasan aktivitas dan progres yang sudah tersimpan.</span>
            </div>

            <div class="stat-box">
                <div class="stat-code">04</div>
                <strong>Dibantu AI</strong>
                <span>Nara AI membantu membuat prioritas, jadwal, dan rekomendasi berdasarkan data NadiOS.</span>
            </div>
        </section>

        <!-- Section video demo penggunaan aplikasi. -->
        <section class="container section" id="video-demo">
            <div class="video-card">
                <div class="section-head">
                    <div>
                        <h2>Video NadiOS</h2>
                        <p>
                           Bagian ini digunakan untuk menampilkan video penggunaan NadiOS. Video dapat berisi alur register, login, dashboard, goals, projects, tasks, habits, journal, dan Nara AI.
                        </p>
                    </div>
                </div>

                <div class="video-layout">
                    <div class="video-frame">
                        <?php if ($hasDemoVideo) { ?>
                            <video controls preload="metadata">
                                <source src="<?php echo e($demoVideoUrl); ?>" type="video/mp4">
                                Browser kamu tidak mendukung pemutar video.
                            </video>
                        <?php } else { ?>
                            <div class="video-placeholder">
                                <div class="video-placeholder-content">
                                    <strong>Tempat Video Demo</strong>
                                    <span>
                                        Letakkan file video demo di folder <b>assets</b> dengan nama
                                        <b>nadios-demo.mp4</b>, lalu refresh halaman ini.
                                    </span>
                                </div>
                            </div>
                        <?php } ?>
                    </div>

                
                </div>
            </div>
        </section>


        <!-- Section fitur utama aplikasi. -->
        <section class="container section">
            <div class="section-card">
                <div class="section-head">
                    <div>
                        <h2>Fitur utama NadiOS</h2>
                        <p>
                            Setiap fitur dibuat untuk mendukung alur produktivitas pribadi dari rencana besar
                            sampai aksi kecil yang bisa dikerjakan setiap hari.
                        </p>
                    </div>
                </div>

                <div class="feature-grid">
                    <article class="feature-card">
                        <div class="feature-code">GL</div>
                        <h3>Goals</h3>
                        <p>
                            Goals adalah tujuan besar yang ingin dicapai. Contohnya menyelesaikan proyek,
                            meningkatkan skill, atau mencapai target tertentu dalam periode waktu tertentu.
                        </p>
                    </article>

                    <article class="feature-card">
                        <div class="feature-code">PR</div>
                        <h3>Projects</h3>
                        <p>
                            Projects adalah kumpulan pekerjaan yang mendukung pencapaian goal.
                            Project membantu membagi tujuan besar menjadi bagian kerja yang lebih jelas.
                        </p>
                    </article>

                    <article class="feature-card">
                        <div class="feature-code">TS</div>
                        <h3>Tasks</h3>
                        <p>
                            Tasks adalah tugas kecil yang bisa langsung dikerjakan. Task dapat diberi prioritas,
                            status, deadline, dan dihubungkan dengan project tertentu.
                        </p>
                    </article>

                    <article class="feature-card">
                        <div class="feature-code">HB</div>
                        <h3>Habits</h3>
                        <p>
                            Habits digunakan untuk membangun kebiasaan harian atau mingguan.
                            Pengguna dapat mencentang habit yang sudah dilakukan setiap hari.
                        </p>
                    </article>

                    <article class="feature-card">
                        <div class="feature-code">JR</div>
                        <h3>Journal</h3>
                        <p>
                            Journal menjadi tempat mencatat refleksi, mood, dan perkembangan pribadi.
                            Fitur ini membantu pengguna mengevaluasi apa yang sudah berjalan dan apa yang perlu diperbaiki.
                        </p>
                    </article>

                    <article class="feature-card">
                        <div class="feature-code">AI</div>
                        <h3>Nara AI</h3>
                        <p>
                            Nara AI adalah asisten produktivitas yang membaca data NadiOS untuk membantu menyusun
                            prioritas, jadwal harian, rekomendasi habit, dan prompt journal.
                        </p>
                    </article>
                </div>
            </div>
        </section>

        <section class="container section">
            <div class="section-card">
                <div class="section-head">
                    <div>
                        <h2>Cara kerja NadiOS</h2>
                        <p>
                            NadiOS bekerja seperti sistem kendali pribadi. Pengguna tidak hanya mencatat aktivitas,
                            tetapi juga menghubungkan rencana, tindakan, kebiasaan, dan evaluasi.
                        </p>
                    </div>
                </div>

                <div class="flow-grid">
                    <article class="flow-card">
                        <div class="flow-number">1</div>
                        <h3>Tentukan arah</h3>
                        <p>Buat goals sebagai tujuan utama yang ingin dicapai.</p>
                    </article>

                    <article class="flow-card">
                        <div class="flow-number">2</div>
                        <h3>Susun project</h3>
                        <p>Pecah goal menjadi project agar pengerjaan lebih terorganisir.</p>
                    </article>

                    <article class="flow-card">
                        <div class="flow-number">3</div>
                        <h3>Kerjakan task</h3>
                        <p>Buat task kecil dengan prioritas, status, dan deadline.</p>
                    </article>

                    <article class="flow-card">
                        <div class="flow-number">4</div>
                        <h3>Evaluasi progres</h3>
                        <p>Bangun habit, tulis journal, lalu gunakan dashboard dan Nara AI untuk melihat arah berikutnya.</p>
                    </article>
                </div>
            </div>
        </section>

        <!-- Section call to action untuk mengarahkan user register atau dashboard. -->
        <section class="container cta">
            <div class="cta-card">
                <div>
                    <?php if ($isLoggedIn) { ?>
                        <h2>Lanjutkan produktivitasmu.</h2>
                        <p>Masuk ke dashboard untuk melihat ringkasan data dan rencana terbaru.</p>
                    <?php } else { ?>
                        <h2>Mulai gunakan NadiOS.</h2>
                        <p>Register terlebih dahulu, lalu login untuk mengakses dashboard pribadi.</p>
                    <?php } ?>
                </div>

                <div class="nav-actions">
                    <?php if ($isLoggedIn) { ?>
                        <a href="dashboard.php" class="btn btn-primary">Dashboard</a>
                        <a href="logout.php" class="btn btn-secondary">Logout</a>
                    <?php } else { ?>
                        <a href="register.php" class="btn btn-primary">Register</a>
                        <a href="login.php" class="btn btn-secondary">Login</a>
                    <?php } ?>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer landing page. -->
    <footer class="container footer">
        NadiOS by Lintang Angrenggani Kusuma R.
    </footer>

</div>


<!-- Script animasi reveal saat elemen masuk ke viewport. -->
<script>
    // Menandai bahwa JavaScript aktif agar CSS reveal dapat digunakan.
    document.documentElement.classList.add('js');

    // Mengambil elemen-elemen yang akan diberi animasi reveal.
    const revealItems = document.querySelectorAll(
        '.navbar, .hero-main, .hero-side, .stat-box, .video-card, .section-card, .feature-card, .flow-card, .cta-card, .footer'
    );

    // Menambahkan class reveal dan delay animasi pada setiap elemen.
    revealItems.forEach((item, index) => {
        item.classList.add('reveal');
        item.style.transitionDelay = `${Math.min(index * 45, 360)}ms`;
    });

    // Observer untuk menampilkan elemen ketika masuk ke area layar.
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                revealObserver.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.14
    });

    // Mengaktifkan observer untuk setiap elemen reveal.
    revealItems.forEach((item) => revealObserver.observe(item));
</script>

</body>
</html>
