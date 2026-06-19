<?php
// Mengecek session. Jika session belum aktif, maka session akan dimulai agar data login user bisa dibaca.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Mengecek apakah user sudah login. Jika belum login, user diarahkan ke halaman login.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php?message=login_required");
    exit;
}

// Menentukan lokasi file koneksi database MySQL.
$pathKoneksi = __DIR__ . "/koneksi.php";

// Mengecek apakah file koneksi database tersedia.
if (!file_exists($pathKoneksi)) {
    die("File koneksi tidak ditemukan di: " . $pathKoneksi);
}

// Memanggil file koneksi agar variabel conn dapat digunakan.
require_once $pathKoneksi;

// Mengecek apakah variabel koneksi database sudah tersedia dari file koneksi.php.
if (!isset($conn)) {
    die("Variabel conn tidak tersedia. Cek isi file koneksi.php");
}

// Menyimpan nama aplikasi yang akan ditampilkan pada title dan sidebar.
$appName = "NadiOS";
// Menyiapkan variabel pesan error dan sukses untuk feedback ke user.
$error = "";
$success = "";
// Mengambil nama dan email user dari session login.
$fullName = $_SESSION["full_name"] ?? "User";
$emailUser = $_SESSION["email"] ?? "";
// User ID dipakai untuk memastikan data habits hanya milik akun yang sedang login.
$userId = (int) ($_SESSION["user_id"] ?? 0);
// Menyimpan tanggal hari ini untuk kebutuhan check/uncheck habit harian.
$today = date("Y-m-d");

// Fungsi untuk mengamankan output HTML agar data tidak menyebabkan XSS.
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ""), ENT_QUOTES, "UTF-8");
}

// Fungsi untuk mengubah format tanggal database menjadi format yang lebih mudah dibaca.
function formatTanggal($date): string
{
    if ($date === null || $date === "" || $date === "0000-00-00") {
        return "-";
    }

    return date("d M Y", strtotime($date));
}

// Fungsi untuk mengecek apakah tabel tertentu tersedia di database.
function tableExists($conn, $tableName): bool
{
    $safeTable = mysqli_real_escape_string($conn, $tableName);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '" . $safeTable . "'");
    return $result && mysqli_num_rows($result) > 0;
}

// Fungsi untuk menentukan warna badge berdasarkan frekuensi habit.
function frequencyBadgeClass($frequency): string
{
    if ($frequency === "Daily") {
        return "badge-blue";
    }

    return "badge-yellow";
}

// Fungsi untuk menentukan warna badge berdasarkan status aktif habit.
function activeBadgeClass($isActive): string
{
    if ((int) $isActive === 1) {
        return "badge-green";
    }

    return "badge-red";
}

// Mengecek apakah tabel habits dan habit_logs sudah tersedia sebelum fitur dijalankan.
if (!tableExists($conn, "habits") || !tableExists($conn, "habit_logs")) {
    $error = "Tabel habits atau habit_logs belum ada. Jalankan file habits_table.sql di phpMyAdmin.";
}

// Menyiapkan mode edit dan data default untuk form habit.
$editMode = false;
$editData = array(
    "id" => "",
    "name" => "",
    "frequency" => "Daily",
    "target_per_week" => 7,
    "is_active" => 1
);

// Proses menghapus habit berdasarkan id dari parameter URL.
if ($error === "" && isset($_GET["delete"])) {
    $deleteId = (int) $_GET["delete"];

    if ($deleteId > 0) {
        // Query untuk menghapus habit menggunakan prepared statement.
        $deleteStmt = mysqli_prepare($conn, "DELETE FROM habits WHERE id = ? AND user_id = ?");

        if ($deleteStmt) {
            mysqli_stmt_bind_param($deleteStmt, "ii", $deleteId, $userId);

            if (mysqli_stmt_execute($deleteStmt)) {
                header("Location: habits.php?message=deleted");
                exit;
            } else {
                $error = "Habit gagal dihapus.";
            }

            mysqli_stmt_close($deleteStmt);
        }
    }
}

// Proses check atau uncheck habit hari ini berdasarkan id habit dari URL.
if ($error === "" && isset($_GET["toggle"])) {
    $habitId = (int) $_GET["toggle"];

    // Jika habit_id lebih dari 0, sistem menjalankan proses update habit.
        if ($habitId > 0) {
        $ownerStmt = mysqli_prepare($conn, "SELECT id FROM habits WHERE id = ? AND user_id = ? LIMIT 1");
        $isOwnerHabit = false;

        if ($ownerStmt) {
            mysqli_stmt_bind_param($ownerStmt, "ii", $habitId, $userId);
            mysqli_stmt_execute($ownerStmt);
            mysqli_stmt_store_result($ownerStmt);
            $isOwnerHabit = mysqli_stmt_num_rows($ownerStmt) > 0;
            mysqli_stmt_close($ownerStmt);
        }

        if (!$isOwnerHabit) {
            $error = "Habit tidak ditemukan untuk akun ini.";
        } else {
        // Mengecek apakah habit sudah dicentang pada tanggal hari ini.
        $checkStmt = mysqli_prepare($conn, "SELECT id FROM habit_logs WHERE habit_id = ? AND log_date = ? LIMIT 1");

        if ($checkStmt) {
            mysqli_stmt_bind_param($checkStmt, "is", $habitId, $today);
            mysqli_stmt_execute($checkStmt);
            mysqli_stmt_store_result($checkStmt);

            if (mysqli_stmt_num_rows($checkStmt) > 0) {
                mysqli_stmt_close($checkStmt);

                // Jika habit sudah dicentang, data log hari ini akan dihapus untuk membatalkan centang.
                $deleteLogStmt = mysqli_prepare($conn, "DELETE FROM habit_logs WHERE habit_id = ? AND log_date = ?");
                if ($deleteLogStmt) {
                    mysqli_stmt_bind_param($deleteLogStmt, "is", $habitId, $today);
                    mysqli_stmt_execute($deleteLogStmt);
                    mysqli_stmt_close($deleteLogStmt);
                }

                header("Location: habits.php?message=unchecked");
                exit;
            } else {
                mysqli_stmt_close($checkStmt);

                // Jika habit belum dicentang, sistem menambahkan log habit untuk tanggal hari ini.
                $insertLogStmt = mysqli_prepare($conn, "INSERT INTO habit_logs (habit_id, log_date) VALUES (?, ?)");
                if ($insertLogStmt) {
                    mysqli_stmt_bind_param($insertLogStmt, "is", $habitId, $today);
                    mysqli_stmt_execute($insertLogStmt);
                    mysqli_stmt_close($insertLogStmt);
                }

                header("Location: habits.php?message=checked");
                exit;
            }
        }
        }
    }
}

// Proses mengambil data habit yang akan diedit berdasarkan id dari URL.
if ($error === "" && isset($_GET["edit"])) {
    $editId = (int) $_GET["edit"];

    if ($editId > 0) {
        // Query untuk mengambil detail habit yang akan dimasukkan ke form edit.
        $editStmt = mysqli_prepare($conn, "SELECT * FROM habits WHERE id = ? AND user_id = ?");

        if ($editStmt) {
            mysqli_stmt_bind_param($editStmt, "ii", $editId, $userId);
            mysqli_stmt_execute($editStmt);
            $editResult = mysqli_stmt_get_result($editStmt);

            if ($editResult && mysqli_num_rows($editResult) > 0) {
                $editMode = true;
                $editData = mysqli_fetch_assoc($editResult);
            }

            mysqli_stmt_close($editStmt);
        }
    }
}

// Menampilkan pesan sukses berdasarkan query string setelah aksi CRUD atau toggle habit.
if (isset($_GET["message"])) {
    if ($_GET["message"] === "created") {
        $success = "Habit berhasil ditambahkan.";
    }

    if ($_GET["message"] === "updated") {
        $success = "Habit berhasil diperbarui.";
    }

    if ($_GET["message"] === "deleted") {
        $success = "Habit berhasil dihapus.";
    }

    if ($_GET["message"] === "checked") {
        $success = "Habit hari ini berhasil dicentang.";
    }

    if ($_GET["message"] === "unchecked") {
        $success = "Centang habit hari ini berhasil dibatalkan.";
    }
}

// Proses create atau update habit ketika form dikirim menggunakan method POST.
if ($error === "" && $_SERVER["REQUEST_METHOD"] === "POST") {
    // Mengambil input dari form dan membersihkan nilai awalnya.
    $habitId = (int) ($_POST["habit_id"] ?? 0);
    $name = trim($_POST["name"] ?? "");
    $frequency = trim($_POST["frequency"] ?? "Daily");
    $targetPerWeek = (int) ($_POST["target_per_week"] ?? 7);
    $isActive = (int) ($_POST["is_active"] ?? 1);

    // Validasi input form habit agar data yang masuk tetap sesuai aturan.
    if ($name === "") {
        $error = "Nama habit wajib diisi.";
    } elseif (!in_array($frequency, array("Daily", "Weekly"))) {
        $error = "Frekuensi habit tidak valid.";
    } elseif ($targetPerWeek < 1 || $targetPerWeek > 7) {
        $error = "Target per minggu harus 1 sampai 7.";
    } elseif (!in_array($isActive, array(0, 1))) {
        $error = "Status aktif tidak valid.";
    } else {
        if ($habitId > 0) {
            // Query untuk memperbarui data habit berdasarkan id.
            $updateQuery = "UPDATE habits SET name = ?, frequency = ?, target_per_week = ?, is_active = ? WHERE id = ? AND user_id = ?";
            $updateStmt = mysqli_prepare($conn, $updateQuery);

            if ($updateStmt) {
                mysqli_stmt_bind_param($updateStmt, "ssiiii", $name, $frequency, $targetPerWeek, $isActive, $habitId, $userId);

                if (mysqli_stmt_execute($updateStmt)) {
                    header("Location: habits.php?message=updated");
                    exit;
                } else {
                    $error = "Habit gagal diperbarui.";
                }

                mysqli_stmt_close($updateStmt);
            }
        } else {
            // Query untuk menambahkan habit baru ke database.
            $insertQuery = "INSERT INTO habits (user_id, name, frequency, target_per_week, is_active) VALUES (?, ?, ?, ?, ?)";
            $insertStmt = mysqli_prepare($conn, $insertQuery);

            if ($insertStmt) {
                mysqli_stmt_bind_param($insertStmt, "issii", $userId, $name, $frequency, $targetPerWeek, $isActive);

                if (mysqli_stmt_execute($insertStmt)) {
                    header("Location: habits.php?message=created");
                    exit;
                } else {
                    $error = "Habit gagal ditambahkan.";
                }

                mysqli_stmt_close($insertStmt);
            }
        }
    }
}

// Menyiapkan array untuk menampung data habits dan log habit hari ini.
$habits = array();
$todayLogs = array();

// Mengambil semua habit yang sudah dicentang pada tanggal hari ini.
if (tableExists($conn, "habit_logs")) {
    $logStmt = mysqli_prepare($conn, "SELECT habit_logs.habit_id FROM habit_logs INNER JOIN habits ON habit_logs.habit_id = habits.id WHERE habit_logs.log_date = ? AND habits.user_id = ?");
    if ($logStmt) {
        mysqli_stmt_bind_param($logStmt, "si", $today, $userId);
        mysqli_stmt_execute($logStmt);
        $logResult = mysqli_stmt_get_result($logStmt);

        if ($logResult) {
            while ($row = mysqli_fetch_assoc($logResult)) {
                $todayLogs[] = (int) $row["habit_id"];
            }
        }

        mysqli_stmt_close($logStmt);
    }
}

// Mengambil semua data habits dari database untuk ditampilkan pada tabel.
if (tableExists($conn, "habits")) {
    $listStmt = mysqli_prepare($conn, "SELECT * FROM habits WHERE user_id = ? ORDER BY created_at DESC");
    if ($listStmt) {
        mysqli_stmt_bind_param($listStmt, "i", $userId);
        mysqli_stmt_execute($listStmt);
        $listResult = mysqli_stmt_get_result($listStmt);

        if ($listResult) {
            while ($row = mysqli_fetch_assoc($listResult)) {
                $habits[] = $row;
            }
        }

        mysqli_stmt_close($listStmt);
    }
}

// Menghitung total habits dan statistik pendukung untuk kartu ringkasan.
$totalHabits = count($habits);
$totalActive = 0;
$totalInactive = 0;
$totalCheckedToday = count($todayLogs);
$totalDaily = 0;

// Menghitung jumlah habit aktif, tidak aktif, dan habit harian.
foreach ($habits as $habit) {
    if ((int) $habit["is_active"] === 1) {
        $totalActive++;
    } else {
        $totalInactive++;
    }

    if ($habit["frequency"] === "Daily") {
        $totalDaily++;
    }
}

// Mengambil nama depan user untuk sapaan pada bagian hero.
$fullNameParts = preg_split('/\s+/', trim($fullName));
$firstName = $fullNameParts[0] ?? "User";

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Habits - <?php echo e($appName); ?></title>

    <style>
        /* Reset dasar agar ukuran elemen lebih mudah dikontrol. */
        * { box-sizing: border-box; }

        /* Variabel warna, shadow, radius, dan tema visual halaman habits. */
        :root {
            --bg: #f7f8ff;
            --panel: rgba(255, 255, 255, 0.88);
            --ink: #172033;
            --muted: #6b7280;
            --line: #e3e8f2;
            --nav: #111827;
            --blue: #2563eb;
            --blue-soft: #eaf1ff;
            --green-soft: #e9fbf1;
            --yellow-soft: #fff7df;
            --pink-soft: #fff1f7;
            --purple-soft: #f1edff;
            --red-soft: #fff1f1;
            --shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
            --radius: 24px;
        }

        /* Pengaturan dasar halaman, background, dan font utama. */
        html, body {
            margin: 0;
            min-height: 100%;
            font-family: Arial, Helvetica, sans-serif;
            background:
                radial-gradient(circle at 15% 12%, rgba(37, 99, 235, 0.12), transparent 28%),
                radial-gradient(circle at 85% 8%, rgba(168, 85, 247, 0.10), transparent 26%),
                radial-gradient(circle at 75% 90%, rgba(34, 197, 94, 0.09), transparent 30%),
                var(--bg);
            color: var(--ink);
            overflow-x: hidden;
        }

        a { color: inherit; text-decoration: none; }
        input, textarea, select, button { font-family: inherit; }

        /* Container utama yang membagi sidebar dan konten halaman. */
        .app { min-height: 100vh; display: flex; }

        /* Sidebar kiri untuk branding, data user, navigasi, dan logout. */
        .sidebar {
            width: 280px;
            min-height: 100vh;
            position: fixed;
            inset: 0 auto 0 0;
            padding: 22px;
            color: #ffffff;
            background:
                linear-gradient(180deg, rgba(17, 24, 39, 0.98), rgba(30, 41, 59, 0.98)),
                #111827;
            overflow-y: auto;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
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
            box-shadow: 0 12px 30px rgba(37, 99, 235, 0.25);
        }

        .logo {
            margin: 0;
            font-size: 26px;
            font-weight: 900;
            letter-spacing: -0.05em;
        }

        .sidebar-subtitle {
            margin: 3px 0 0;
            color: #cbd5e1;
            font-size: 12px;
            line-height: 1.5;
        }

        .user-card {
            margin: 18px 0 20px;
            padding: 16px;
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.10);
        }

        .user-avatar {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 15px;
            background: #ffffff;
            color: #111827;
            font-weight: 900;
            margin-bottom: 10px;
        }

        .user-card strong {
            display: block;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .user-card span {
            color: #d1d5db;
            font-size: 12px;
            word-break: break-all;
        }

        .nav { display: grid; gap: 7px; }

        .nav a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 13px;
            border-radius: 16px;
            color: #d1d5db;
            font-size: 14px;
            font-weight: 800;
            transition: 0.18s ease;
        }

        .nav a:hover,
        .nav a.active {
            background: rgba(255, 255, 255, 0.11);
            color: #ffffff;
            transform: translateX(3px);
        }

        .logout-box { margin-top: 22px; }

        .logout-link {
            display: block;
            width: 100%;
            padding: 13px 14px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.10);
            color: #ffffff;
            font-weight: 900;
            text-align: center;
        }

        /* Area konten utama di sebelah kanan sidebar. */
        .content {
            margin-left: 280px;
            width: calc(100% - 280px);
            max-width: calc(100% - 280px);
            padding: 28px;
            overflow-x: hidden;
        }

        /* Bagian hero untuk judul halaman dan tombol aksi cepat. */
        .hero {
            display: grid;
            grid-template-columns: 1.25fr 0.8fr;
            gap: 18px;
            margin-bottom: 18px;
        }

        .hero-main,
        .hero-side,
        .panel,
        .card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            backdrop-filter: blur(14px);
        }

        .hero-main {
            padding: 24px;
            position: relative;
            overflow: hidden;
        }

        .hero-main::after {
            content: "";
            position: absolute;
            right: -50px;
            top: -45px;
            width: 180px;
            height: 180px;
            border-radius: 999px;
            background: linear-gradient(135deg, rgba(96, 165, 250, 0.23), rgba(34, 197, 94, 0.18));
        }

        .eyebrow {
            display: inline-flex;
            width: fit-content;
            padding: 8px 12px;
            border-radius: 999px;
            background: var(--green-soft);
            color: #166534;
            font-size: 12px;
            font-weight: 900;
            margin-bottom: 14px;
        }

        .hero h1 {
            margin: 0;
            font-size: clamp(34px, 5vw, 56px);
            letter-spacing: -0.07em;
            line-height: 0.98;
        }

        .hero p {
            margin: 14px 0 0;
            max-width: 680px;
            color: var(--muted);
            line-height: 1.7;
            font-size: 15px;
        }

        .hero-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 15px;
            padding: 12px 15px;
            font-size: 14px;
            font-weight: 900;
            border: none;
            cursor: pointer;
            transition: 0.18s ease;
        }

        .btn:hover { transform: translateY(-1px); }

        .btn-primary {
            background: var(--blue);
            color: #ffffff;
            box-shadow: 0 14px 28px rgba(37, 99, 235, 0.22);
        }

        .btn-secondary {
            background: #ffffff;
            color: var(--ink);
            border: 1px solid var(--line);
        }

        .btn-warning {
            background: #fff7df;
            color: #92400e;
        }

        .btn-danger {
            background: #fff1f1;
            color: #991b1b;
        }

        .btn-success {
            background: #e9fbf1;
            color: #166534;
        }

        .hero-side {
            padding: 22px;
            display: grid;
            align-content: center;
            gap: 14px;
        }

        .tip-card {
            padding: 16px;
            border-radius: 20px;
            background: linear-gradient(135deg, #e9fbf1, #eaf1ff);
            border: 1px solid rgba(34, 197, 94, 0.13);
        }

        .tip-card strong {
            display: block;
            font-size: 18px;
            letter-spacing: -0.03em;
            margin-bottom: 6px;
        }

        .tip-card span {
            color: #6b7280;
            line-height: 1.5;
            font-size: 13px;
        }

        /* Grid statistik untuk menampilkan jumlah habit berdasarkan kategori. */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid var(--line);
            border-radius: 22px;
            padding: 17px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.055);
            min-width: 0;
        }

        .stat-icon {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            margin-bottom: 12px;
            font-size: 12px;
            font-weight: 900;
        }

        .stat-card:nth-child(1) .stat-icon { background: var(--purple-soft); }
        .stat-card:nth-child(2) .stat-icon { background: var(--green-soft); }
        .stat-card:nth-child(3) .stat-icon { background: var(--red-soft); }
        .stat-card:nth-child(4) .stat-icon { background: var(--blue-soft); }
        .stat-card:nth-child(5) .stat-icon { background: var(--yellow-soft); }

        .stat-card h2 {
            margin: 0;
            font-size: 34px;
            line-height: 1;
            letter-spacing: -0.06em;
        }

        .stat-card p {
            margin: 7px 0 0;
            color: var(--muted);
            font-size: 13px;
            font-weight: 800;
        }

        /* Layout utama yang membagi form habit dan daftar habits. */
        .grid {
            display: grid;
            grid-template-columns: minmax(330px, 430px) minmax(0, 1fr);
            gap: 18px;
            align-items: start;
        }

        .card {
            padding: 20px;
            min-width: 0;
        }

        .section-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 14px;
        }

        .section-head h2,
        .card h2 {
            margin: 0;
            font-size: 22px;
            letter-spacing: -0.04em;
        }

        .section-head p {
            margin: 6px 0 0;
            color: var(--muted);
            line-height: 1.55;
            font-size: 14px;
        }

        .alert {
            padding: 13px 14px;
            border-radius: 16px;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 16px;
            font-weight: 800;
        }

        .alert-error {
            background: #fff1f1;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #e9fbf1;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        /* Pengaturan layout form tambah/edit habit. */
        .form {
            display: grid;
            gap: 14px;
        }

        .form-group {
            display: grid;
            gap: 8px;
        }

        .form-group label {
            font-size: 13px;
            font-weight: 900;
            color: var(--ink);
        }

        .form-control {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 13px 14px;
            outline: none;
            font-size: 14px;
            color: var(--ink);
            background: #ffffff;
        }

        .form-control:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.11);
        }

        .form-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* Wrapper tabel agar tabel tetap bisa digeser horizontal pada layar kecil. */
        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 820px;
            border-collapse: collapse;
        }

        th, td {
            padding: 14px 12px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: top;
            font-size: 14px;
        }

        th {
            color: var(--muted);
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .habit-title {
            font-weight: 900;
            color: var(--ink);
        }

        .habit-meta {
            margin-top: 5px;
            color: var(--muted);
            line-height: 1.45;
        }

        .badge {
            display: inline-flex;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 900;
            white-space: nowrap;
        }

        .badge-green { background: #dcfce7; color: #166534; }
        .badge-blue { background: #dbeafe; color: #1d4ed8; }
        .badge-yellow { background: #fef3c7; color: #92400e; }
        .badge-red { background: #fee2e2; color: #991b1b; }

        .action-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .empty {
            padding: 24px;
            border: 1px dashed var(--line);
            border-radius: 18px;
            color: var(--muted);
            text-align: center;
            line-height: 1.6;
            background: #fbfcff;
        }

        /* Responsive layout untuk layar tablet dan ukuran sedang. */
        @media (max-width: 1180px) {
            .hero,
            .grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        /* Responsive layout untuk layar mobile. */
        @media (max-width: 780px) {
            .app { display: block; }

            .sidebar {
                position: static;
                width: 100%;
                min-height: auto;
            }

            .content {
                margin-left: 0;
                width: 100%;
                max-width: 100%;
                padding: 18px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<!-- Container utama aplikasi. -->
<div class="app">

    <!-- Sidebar berisi logo, profil user, menu navigasi, dan tombol logout. -->
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-mark">NO</div>
            <div>
                <h1 class="logo"><?php echo e($appName); ?></h1>
                <p class="sidebar-subtitle">Personal Productivity System</p>
            </div>
        </div>

        <div class="user-card">
            <div class="user-avatar"><?php echo strtoupper(substr(e($fullName), 0, 1)); ?></div>
            <strong><?php echo e($fullName); ?></strong>
            <span><?php echo e($emailUser); ?></span>
        </div>

        <!-- Menu navigasi ke setiap fitur utama NadiOS. -->
        <nav class="nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="goals.php">Goals</a>
            <a href="projects.php">Projects</a>
            <a href="tasks.php">Tasks</a>
            <a href="habits.php" class="active">Habits</a>
            <a href="journals.php">Journal</a>
            <a href="ai_assistant.php">Nara AI</a>
            <a href="index.php">Index</a>
        </nav>

        <div class="logout-box">
            <a href="logout.php" class="logout-link">Logout</a>
        </div>
    </aside>

    <!-- Konten utama halaman Habits. -->
    <main class="content">

        <!-- Hero halaman Habits berisi sapaan dan tombol aksi. -->
        <section class="hero">
            <div class="hero-main">
                <span class="eyebrow">Habits workspace</span>
                <h1>Bangun ritme kecil, <?php echo e($firstName); ?>.</h1>
                <p>
                    Gunakan halaman ini untuk mencatat kebiasaan yang ingin kamu bangun, menentukan target mingguan, dan menandai habit yang sudah kamu lakukan hari ini.
                </p>

                <div class="hero-actions">
                    <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
                    <a href="habits.php" class="btn btn-primary">Refresh</a>
                </div>
            </div>

            <div class="hero-side">
                <div class="tip-card">
                    <strong>Habit yang realistis</strong>
                    <span>
                       Pilih kebiasaan yang sederhana dan realistis. Lebih baik konsisten sedikit demi sedikit daripada membuat target besar tapi sulit dijalankan.
                    </span>
                </div>

                <div class="tip-card">
                    <strong>Mini reminder</strong>
                    <span>
                        Setiap habit yang kamu centang akan membantu NadiOS membaca progres harianmu di dashboard dan Nara AI.
                </div>
            </div>
        </section>

        <!-- Kartu statistik jumlah habit berdasarkan status dan kategori. -->
        <section class="stats-grid">
            <div class="stat-card"><div class="stat-icon">TH</div><h2><?php echo $totalHabits; ?></h2><p>Total Habits</p></div>
            <div class="stat-card"><div class="stat-icon">AC</div><h2><?php echo $totalActive; ?></h2><p>Active</p></div>
            <div class="stat-card"><div class="stat-icon">IN</div><h2><?php echo $totalInactive; ?></h2><p>Inactive</p></div>
            <div class="stat-card"><div class="stat-icon">CT</div><h2><?php echo $totalCheckedToday; ?></h2><p>Checked Today</p></div>
            <div class="stat-card"><div class="stat-icon">DY</div><h2><?php echo $totalDaily; ?></h2><p>Daily Habits</p></div>
        </section>

        <!-- Area utama berisi form tambah/edit habit dan tabel daftar habits. -->
        <section class="grid">
            <div class="card">
                <div class="section-head">
                    <div>
                        <h2><?php echo $editMode ? "Edit Habit" : "Tambah Habit"; ?></h2>
                        <p>Masukkan kebiasaan yang ingin kamu bangun.</p>
                    </div>
                </div>

                <?php if ($error !== "") { ?>
                    <div class="alert alert-error"><?php echo e($error); ?></div>
                <?php } ?>

                <?php if ($success !== "") { ?>
                    <div class="alert alert-success"><?php echo e($success); ?></div>
                <?php } ?>

                <!-- Form untuk menambah atau memperbarui data habit. -->
                <form class="form" action="habits.php" method="POST">
                    <input type="hidden" name="habit_id" value="<?php echo e($editData["id"]); ?>">

                    <div class="form-group">
                        <label for="name">Nama Habit</label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            class="form-control"
                            placeholder="Contoh: Membaca 10 menit"
                            value="<?php echo e($editData["name"]); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="frequency">Frekuensi</label>
                        <select id="frequency" name="frequency" class="form-control" required>
                            <option value="Daily" <?php if ($editData["frequency"] === "Daily") { echo "selected"; } ?>>Daily</option>
                            <option value="Weekly" <?php if ($editData["frequency"] === "Weekly") { echo "selected"; } ?>>Weekly</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="target_per_week">Target per Minggu</label>
                        <input
                            type="number"
                            id="target_per_week"
                            name="target_per_week"
                            class="form-control"
                            min="1"
                            max="7"
                            value="<?php echo e($editData["target_per_week"]); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="is_active">Status Aktif</label>
                        <select id="is_active" name="is_active" class="form-control" required>
                            <option value="1" <?php if ((int) $editData["is_active"] === 1) { echo "selected"; } ?>>Active</option>
                            <option value="0" <?php if ((int) $editData["is_active"] === 0) { echo "selected"; } ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $editMode ? "Update Habit" : "Tambah Habit"; ?>
                        </button>

                        <?php if ($editMode) { ?>
                            <a href="habits.php" class="btn btn-secondary">Batal Edit</a>
                        <?php } ?>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="section-head">
                    <div>
                        <h2>Daftar Habits</h2>
                        <p>Semua habit yang sudah kamu simpan di NadiOS.</p>
                    </div>
                </div>

                <?php if (count($habits) === 0) { ?>
                    <div class="empty">Belum ada habit. Tambahkan habit pertama melalui form.</div>
                <?php } else { ?>
                    <!-- Tabel daftar habits yang sudah tersimpan di database. -->
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Habit</th>
                                    <th>Frekuensi</th>
                                    <th>Status</th>
                                    <th>Hari Ini</th>
                                    <th>Dibuat</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($habits as $habit) { ?>
                                    <?php $checkedToday = in_array((int) $habit["id"], $todayLogs); ?>
                                    <tr>
                                        <td>
                                            <div class="habit-title"><?php echo e($habit["name"]); ?></div>
                                            <div class="habit-meta">Target: <?php echo e($habit["target_per_week"]); ?>x/minggu</div>
                                        </td>

                                        <td>
                                            <span class="badge <?php echo frequencyBadgeClass($habit["frequency"]); ?>">
                                                <?php echo e($habit["frequency"]); ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="badge <?php echo activeBadgeClass($habit["is_active"]); ?>">
                                                <?php echo ((int) $habit["is_active"] === 1) ? "Active" : "Inactive"; ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?php if ($checkedToday) { ?>
                                                <span class="badge badge-green">Done Today</span>
                                            <?php } else { ?>
                                                <span class="badge badge-red">Not Yet</span>
                                            <?php } ?>
                                        </td>

                                        <td><?php echo formatTanggal($habit["created_at"]); ?></td>

                                        <td>
                                            <div class="action-row">
                                                <a href="habits.php?toggle=<?php echo $habit["id"]; ?>" class="btn btn-success">
                                                    <?php echo $checkedToday ? "Uncheck" : "Check"; ?>
                                                </a>
                                                <a href="habits.php?edit=<?php echo $habit["id"]; ?>" class="btn btn-warning">Edit</a>
                                                <a
                                                    href="habits.php?delete=<?php echo $habit["id"]; ?>"
                                                    class="btn btn-danger"
                                                    onclick="return confirm('Yakin ingin menghapus habit ini?');"
                                                >
                                                    Delete
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </div>
        </section>

    </main>

</div>

</body>
</html>
