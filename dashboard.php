<?php
// Mengecek session. Jika session belum aktif, maka session akan dimulai agar data login user bisa dibaca.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Mengecek apakah user sudah login. Jika belum login, user diarahkan kembali ke halaman login.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php?message=login_required");
    exit;
}

// Menentukan lokasi file koneksi database MySQL.
$pathKoneksi = __DIR__ . "/koneksi.php";

// Mengecek apakah file koneksi benar-benar tersedia.
if (!file_exists($pathKoneksi)) {
    die("File koneksi tidak ditemukan di: " . $pathKoneksi);
}

// Memanggil file koneksi agar variabel koneksi database dapat digunakan.
require_once $pathKoneksi;

// Mengecek apakah variabel koneksi database sudah tersedia dari file koneksi.php.
if (!isset($conn)) {
    die("Variabel conn tidak tersedia. Cek isi file koneksi.php");
}

// Menyimpan nama aplikasi yang akan ditampilkan pada title, sidebar, dan bagian dashboard.
$appName = "NadiOS";
// Mengambil nama lengkap dan email user dari session login.
$fullName = $_SESSION["full_name"] ?? "User";
$email = $_SESSION["email"] ?? "";
// User ID dipakai agar dashboard hanya menampilkan data milik akun yang sedang login.
$userId = (int) ($_SESSION["user_id"] ?? 0);

// Fungsi untuk mengamankan output HTML agar data tidak langsung dicetak tanpa proses escaping.
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ""), ENT_QUOTES, "UTF-8");
}

// Fungsi untuk mengecek apakah sebuah tabel tersedia di database.
function tableExists($conn, $tableName): bool
{
    $safeTable = mysqli_real_escape_string($conn, $tableName);
    $query = "SHOW TABLES LIKE '" . $safeTable . "'";
    $result = mysqli_query($conn, $query);

    return $result && mysqli_num_rows($result) > 0;
}

// Fungsi untuk menghitung total data pada tabel tertentu.
function countTable($conn, $tableName, $userId): int
{
    if (!tableExists($conn, $tableName)) {
        return 0;
    }

    $safeTable = mysqli_real_escape_string($conn, $tableName);
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM `$safeTable` WHERE user_id = ?");

    if (!$stmt) {
        return 0;
    }

    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result) {
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return (int) ($row["total"] ?? 0);
    }

    mysqli_stmt_close($stmt);
    return 0;
}

// Fungsi untuk menghitung data berdasarkan status tertentu pada tabel tertentu.
function countByStatus($conn, $tableName, $status, $userId): int
{
    if (!tableExists($conn, $tableName)) {
        return 0;
    }

    $safeTable = mysqli_real_escape_string($conn, $tableName);
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM `$safeTable` WHERE status = ? AND user_id = ?");

    if (!$stmt) {
        return 0;
    }

    mysqli_stmt_bind_param($stmt, "si", $status, $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result) {
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return (int) ($row["total"] ?? 0);
    }

    mysqli_stmt_close($stmt);
    return 0;
}

// Fungsi untuk mengubah format tanggal database menjadi format tanggal yang lebih mudah dibaca.
function formatTanggal($date): string
{
    if ($date === null || $date === "" || $date === "0000-00-00") {
        return "-";
    }

    return date("d M Y", strtotime($date));
}

// Fungsi untuk menentukan warna badge berdasarkan status data.
function badgeClass($status): string
{
    if ($status === "Active" || $status === "In Progress" || $status === "Doing") {
        return "badge-green";
    }

    if ($status === "Done" || $status === "Completed") {
        return "badge-blue";
    }

    if ($status === "Paused" || $status === "Archived") {
        return "badge-yellow";
    }

    if ($status === "Urgent") {
        return "badge-red";
    }

    return "badge-gray";
}

// Mengambil jumlah total data dari masing-masing tabel utama untuk ditampilkan pada kartu statistik dashboard.
$totalGoals = countTable($conn, "goals", $userId);
$totalProjects = countTable($conn, "projects", $userId);
$totalTasks = countTable($conn, "tasks", $userId);
$totalHabits = countTable($conn, "habits", $userId);
$totalJournals = countTable($conn, "journals", $userId);

// Mengambil ringkasan data berdasarkan status tertentu untuk bagian summary dashboard.
$completedGoals = countByStatus($conn, "goals", "Completed", $userId);
$activeProjects = countByStatus($conn, "projects", "Active", $userId);
$doneTasks = countByStatus($conn, "tasks", "Done", $userId);

// Menyiapkan array untuk menampung data goals terbaru.
$recentGoals = array();
// Mengambil data goals terbaru jika tabel goals tersedia.
if (tableExists($conn, "goals")) {
    $goalStmt = mysqli_prepare($conn, "SELECT id, title, status, target_date FROM goals WHERE user_id = ? ORDER BY created_at DESC LIMIT 4");
    if ($goalStmt) {
        mysqli_stmt_bind_param($goalStmt, "i", $userId);
        mysqli_stmt_execute($goalStmt);
        $goalResult = mysqli_stmt_get_result($goalStmt);

        if ($goalResult) {
            while ($row = mysqli_fetch_assoc($goalResult)) {
                $recentGoals[] = $row;
            }
        }

        mysqli_stmt_close($goalStmt);
    }
}

// Menyiapkan array untuk menampung data projects terbaru.
$recentProjects = array();
// Mengambil data projects terbaru jika tabel projects tersedia.
if (tableExists($conn, "projects")) {
    $projectStmt = mysqli_prepare($conn, "SELECT id, name, status, due_date FROM projects WHERE user_id = ? ORDER BY created_at DESC LIMIT 4");
    if ($projectStmt) {
        mysqli_stmt_bind_param($projectStmt, "i", $userId);
        mysqli_stmt_execute($projectStmt);
        $projectResult = mysqli_stmt_get_result($projectStmt);

        if ($projectResult) {
            while ($row = mysqli_fetch_assoc($projectResult)) {
                $recentProjects[] = $row;
            }
        }

        mysqli_stmt_close($projectStmt);
    }
}

// Menyiapkan array untuk menyimpan semua rencana yang akan ditampilkan pada kalender dashboard.
$calendarPlans = array();

// Fungsi untuk menambahkan task, goal, atau project ke daftar kalender jika memiliki tanggal valid.
function addCalendarPlan(&$calendarPlans, $type, $title, $date, $status, $details): void
{
    if ($date === null || $date === "" || $date === "0000-00-00") {
        return;
    }

    $calendarPlans[] = array(
        "type" => $type,
        "title" => $title,
        "date" => $date,
        "status" => $status,
        "details" => $details
    );
}

// Fungsi untuk membuat link Google Calendar berdasarkan judul, tanggal, dan detail agenda.
function googleCalendarUrl($title, $date, $details): string
{
    if ($date === null || $date === "" || $date === "0000-00-00") {
        return "#";
    }

    $start = date("Ymd", strtotime($date));
    $end = date("Ymd", strtotime($date . " +1 day"));

    $params = array(
        "action" => "TEMPLATE",
        "text" => $title,
        "dates" => $start . "/" . $end,
        "details" => $details,
        "ctz" => "Asia/Jakarta"
    );

    return "https://calendar.google.com/calendar/render?" . http_build_query($params);
}

// Mengambil task aktif yang memiliki deadline untuk dimasukkan ke kalender.
if (tableExists($conn, "tasks")) {
    $taskCalendarStmt = mysqli_prepare(
        $conn,
        "SELECT title, priority, status, due_date
         FROM tasks
         WHERE user_id = ?
         AND due_date IS NOT NULL
         AND due_date <> '0000-00-00'
         AND status NOT IN ('Done', 'Canceled')
         ORDER BY due_date ASC, created_at DESC
         LIMIT 8"
    );

    if ($taskCalendarStmt) {
        mysqli_stmt_bind_param($taskCalendarStmt, "i", $userId);
        mysqli_stmt_execute($taskCalendarStmt);
        $taskCalendarResult = mysqli_stmt_get_result($taskCalendarStmt);

        if ($taskCalendarResult) {
            while ($row = mysqli_fetch_assoc($taskCalendarResult)) {
                addCalendarPlan(
                    $calendarPlans,
                    "Task",
                    $row["title"],
                    $row["due_date"],
                    $row["status"],
                    "NadiOS Task | Priority: " . ($row["priority"] ?? "-") . " | Status: " . ($row["status"] ?? "-")
                );
            }
        }

        mysqli_stmt_close($taskCalendarStmt);
    }
}

// Mengambil goal aktif yang memiliki target date untuk dimasukkan ke kalender.
if (tableExists($conn, "goals")) {
    $goalCalendarStmt = mysqli_prepare(
        $conn,
        "SELECT title, status, target_date
         FROM goals
         WHERE user_id = ?
         AND target_date IS NOT NULL
         AND target_date <> '0000-00-00'
         AND status NOT IN ('Completed', 'Archived')
         ORDER BY target_date ASC, created_at DESC
         LIMIT 8"
    );

    if ($goalCalendarStmt) {
        mysqli_stmt_bind_param($goalCalendarStmt, "i", $userId);
        mysqli_stmt_execute($goalCalendarStmt);
        $goalCalendarResult = mysqli_stmt_get_result($goalCalendarStmt);

        if ($goalCalendarResult) {
            while ($row = mysqli_fetch_assoc($goalCalendarResult)) {
                addCalendarPlan(
                    $calendarPlans,
                    "Goal",
                    $row["title"],
                    $row["target_date"],
                    $row["status"],
                    "NadiOS Goal | Status: " . ($row["status"] ?? "-")
                );
            }
        }

        mysqli_stmt_close($goalCalendarStmt);
    }
}

// Mengambil project aktif yang memiliki due date untuk dimasukkan ke kalender.
if (tableExists($conn, "projects")) {
    $projectCalendarStmt = mysqli_prepare(
        $conn,
        "SELECT name, status, due_date
         FROM projects
         WHERE user_id = ?
         AND due_date IS NOT NULL
         AND due_date <> '0000-00-00'
         AND status NOT IN ('Done', 'Paused')
         ORDER BY due_date ASC, created_at DESC
         LIMIT 8"
    );

    if ($projectCalendarStmt) {
        mysqli_stmt_bind_param($projectCalendarStmt, "i", $userId);
        mysqli_stmt_execute($projectCalendarStmt);
        $projectCalendarResult = mysqli_stmt_get_result($projectCalendarStmt);

        if ($projectCalendarResult) {
            while ($row = mysqli_fetch_assoc($projectCalendarResult)) {
                addCalendarPlan(
                    $calendarPlans,
                    "Project",
                    $row["name"],
                    $row["due_date"],
                    $row["status"],
                    "NadiOS Project | Status: " . ($row["status"] ?? "-")
                );
            }
        }

        mysqli_stmt_close($projectCalendarStmt);
    }
}

// Mengurutkan agenda kalender berdasarkan tanggal paling dekat.
usort($calendarPlans, function ($a, $b) {
    return strtotime($a["date"]) <=> strtotime($b["date"]);
});

// Membatasi jumlah agenda kalender agar tampilan dashboard tetap ringan.
$calendarPlans = array_slice($calendarPlans, 0, 50);


// Mengambil nama depan user untuk sapaan pada hero dashboard.
$fullNameParts = preg_split('/\s+/', trim($fullName));
$firstName = $fullNameParts[0] ?? "User";

// Mengambil bulan dan tahun kalender dari URL. Jika tidak ada, sistem memakai bulan dan tahun saat ini.
$calendarMonth = (int) ($_GET["month"] ?? date("n"));
$calendarYear = (int) ($_GET["year"] ?? date("Y"));

// Validasi bulan agar nilainya tetap berada pada rentang 1 sampai 12.
if ($calendarMonth < 1 || $calendarMonth > 12) {
    $calendarMonth = (int) date("n");
}

// Validasi tahun agar nilai kalender tetap dalam rentang yang masuk akal.
if ($calendarYear < 2000 || $calendarYear > 2100) {
    $calendarYear = (int) date("Y");
}

// Menghitung informasi dasar kalender seperti tanggal awal, jumlah hari, dan posisi hari pertama bulan.
$calendarStart = sprintf("%04d-%02d-01", $calendarYear, $calendarMonth);
$calendarDaysInMonth = (int) date("t", strtotime($calendarStart));
$calendarFirstDayIndex = (int) date("w", strtotime($calendarStart));
$calendarToday = date("Y-m-d");
$calendarMonthTitle = date("F Y", strtotime($calendarStart));

// Membuat URL navigasi untuk bulan sebelumnya, bulan ini, dan bulan berikutnya.
$previousMonthTime = strtotime($calendarStart . " -1 month");
$nextMonthTime = strtotime($calendarStart . " +1 month");
$previousCalendarUrl = "dashboard.php?month=" . date("n", $previousMonthTime) . "&year=" . date("Y", $previousMonthTime) . "#lifeos-calendar";
$nextCalendarUrl = "dashboard.php?month=" . date("n", $nextMonthTime) . "&year=" . date("Y", $nextMonthTime) . "#lifeos-calendar";
$currentCalendarUrl = "dashboard.php?month=" . date("n") . "&year=" . date("Y") . "#lifeos-calendar";

// Fungsi untuk mengubah nama bulan dari bahasa Inggris ke bahasa Indonesia.
function calendarMonthNameId($monthTitle): string
{
    $map = array(
        "January" => "Januari",
        "February" => "Februari",
        "March" => "Maret",
        "April" => "April",
        "May" => "Mei",
        "June" => "Juni",
        "July" => "Juli",
        "August" => "Agustus",
        "September" => "September",
        "October" => "Oktober",
        "November" => "November",
        "December" => "Desember"
    );

    return strtr($monthTitle, $map);
}

// Fungsi untuk menentukan class CSS event kalender berdasarkan jenis data.
function calendarTypeClass($type): string
{
    if ($type === "Task") {
        return "event-task";
    }

    if ($type === "Goal") {
        return "event-goal";
    }

    if ($type === "Project") {
        return "event-project";
    }

    return "event-default";
}

// Mengelompokkan event kalender berdasarkan tanggal agar mudah ditampilkan pada kotak tanggal.
$calendarEventsByDate = array();
foreach ($calendarPlans as $plan) {
    $eventDate = date("Y-m-d", strtotime($plan["date"]));
    if (date("Y-m", strtotime($eventDate)) === sprintf("%04d-%02d", $calendarYear, $calendarMonth)) {
        if (!isset($calendarEventsByDate[$eventDate])) {
            $calendarEventsByDate[$eventDate] = array();
        }
        $calendarEventsByDate[$eventDate][] = $plan;
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo e($appName); ?></title>

    <style>
        /* Reset dasar agar ukuran elemen lebih mudah dikontrol. */
        * { box-sizing: border-box; }

        /* Variabel warna, shadow, dan radius untuk menjaga konsistensi desain dashboard. */
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
            --shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
            --radius: 24px;
        }

        /* Pengaturan dasar halaman, background, dan font utama aplikasi. */
        html, body {
            margin: 0;
            min-height: 100%;
            font-family: Arial, Helvetica, sans-serif;
            background:
                radial-gradient(circle at 15% 12%, rgba(37, 99, 235, 0.12), transparent 28%),
                radial-gradient(circle at 85% 8%, rgba(168, 85, 247, 0.11), transparent 26%),
                radial-gradient(circle at 75% 90%, rgba(34, 197, 94, 0.10), transparent 30%),
                var(--bg);
            color: var(--ink);
            overflow-x: hidden;
        }

        a { color: inherit; text-decoration: none; }

        /* Container utama yang membagi sidebar dan konten dashboard. */
        .app { min-height: 100vh; display: flex; }

        /* Sidebar kiri untuk navigasi utama aplikasi. */
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

        /* Area konten utama yang berada di sebelah kanan sidebar. */
        .content {
            margin-left: 280px;
            width: calc(100% - 280px);
            max-width: calc(100% - 280px);
            padding: 28px;
        }

        /* Bagian hero untuk sapaan user dan tombol aksi cepat. */
        .hero {
            display: grid;
            grid-template-columns: 1.4fr 0.9fr;
            gap: 18px;
            margin-bottom: 18px;
        }

        .hero-main, .hero-side, .panel {
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
            background: linear-gradient(135deg, rgba(96, 165, 250, 0.24), rgba(168, 85, 247, 0.20));
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            padding: 8px 12px;
            border-radius: 999px;
            background: var(--blue-soft);
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 900;
            margin-bottom: 14px;
        }

        .hero h1 {
            margin: 0;
            font-size: clamp(34px, 5vw, 58px);
            letter-spacing: -0.07em;
            line-height: 0.98;
        }

        .hero p {
            margin: 14px 0 0;
            max-width: 690px;
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

        .hero-side {
            padding: 22px;
            display: grid;
            align-content: center;
            gap: 14px;
        }

        .mood-card {
            padding: 16px;
            border-radius: 20px;
            background: linear-gradient(135deg, #fff7df, #fff1f7);
            border: 1px solid rgba(234, 179, 8, 0.18);
        }

        .mood-card strong {
            display: block;
            font-size: 18px;
            letter-spacing: -0.03em;
            margin-bottom: 6px;
        }

        .mood-card span {
            color: #6b7280;
            line-height: 1.5;
            font-size: 13px;
        }

        /* Grid statistik untuk menampilkan jumlah Goals, Projects, Tasks, Habits, dan Journals. */
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
            font-size: 14px;
            font-weight: 900;
        }

        .stat-card:nth-child(1) .stat-icon { background: var(--purple-soft); }
        .stat-card:nth-child(2) .stat-icon { background: var(--blue-soft); }
        .stat-card:nth-child(3) .stat-icon { background: var(--green-soft); }
        .stat-card:nth-child(4) .stat-icon { background: var(--yellow-soft); }
        .stat-card:nth-child(5) .stat-icon { background: var(--pink-soft); }

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

        .summary-card {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .mini-card {
            background: #ffffff;
            border: 1px solid var(--line);
            border-radius: 22px;
            padding: 18px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.055);
        }

        .mini-card strong {
            display: block;
            font-size: 28px;
            line-height: 1;
            letter-spacing: -0.06em;
            margin-bottom: 7px;
        }

        .mini-card span {
            color: var(--muted);
            font-size: 13px;
            font-weight: 800;
        }

        /* Card utama untuk kalender dan daftar data terbaru. */
        .calendar-card, .card {
            background: #ffffff;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
        }

        .calendar-card { margin-bottom: 18px; }

        .calendar-shell {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(300px, 360px);
            gap: 16px;
            align-items: start;
        }

        .calendar-topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }

        .calendar-month-title {
            margin: 0;
            font-size: 24px;
            letter-spacing: -0.045em;
        }

        .calendar-controls {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .calendar-control {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 0 13px;
            border-radius: 14px;
            border: 1px solid var(--line);
            background: #ffffff;
            color: var(--ink);
            font-size: 13px;
            font-weight: 900;
        }

        .calendar-control:hover {
            background: #f8fafc;
        }

        /* Tampilan kalender bulanan. */
        .month-calendar {
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 22px;
            background: #ffffff;
        }

        .calendar-weekdays {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            background: #f8fafc;
            border-bottom: 1px solid var(--line);
        }

        .calendar-weekday {
            padding: 12px 10px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 900;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .calendar-month-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
        }

        .calendar-day {
            min-height: 122px;
            padding: 10px;
            border-right: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.94);
            overflow: hidden;
        }

        .calendar-day:nth-child(7n) {
            border-right: none;
        }

        .calendar-day.is-empty {
            background: #fbfcff;
        }

        .calendar-day.is-today {
            background: linear-gradient(180deg, #ffffff, #eef2ff);
            box-shadow: inset 0 0 0 2px rgba(37, 99, 235, 0.18);
        }

        .day-number {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 900;
            color: var(--ink);
            margin-bottom: 8px;
        }

        .calendar-day.is-today .day-number {
            background: var(--blue);
            color: #ffffff;
        }

        .calendar-event-list {
            display: grid;
            gap: 5px;
        }

        .calendar-event {
            display: block;
            width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            border-radius: 9px;
            padding: 6px 8px;
            font-size: 11px;
            font-weight: 900;
            line-height: 1.15;
        }

        .calendar-event:hover {
            filter: brightness(0.98);
        }

        .event-task {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .event-goal {
            background: #dcfce7;
            color: #166534;
        }

        .event-project {
            background: #f3e8ff;
            color: #6b21a8;
        }

        .event-default {
            background: #f3f4f6;
            color: #374151;
        }

        .more-events {
            color: var(--muted);
            font-size: 11px;
            font-weight: 900;
            padding-left: 3px;
        }

        .calendar-agenda {
            background: #ffffff;
            border: 1px solid var(--line);
            border-radius: 22px;
            padding: 16px;
            min-width: 0;
            overflow: hidden;
        }

        .calendar-agenda h3 {
            margin: 0 0 10px;
            font-size: 18px;
            letter-spacing: -0.035em;
        }

        .calendar-agenda .calendar-item {
            grid-template-columns: 1fr;
            align-items: start;
            gap: 8px;
        }

        .calendar-agenda .calendar-link {
            width: 100%;
        }

        .calendar-agenda .calendar-title {
            word-break: break-word;
        }

        .calendar-legend {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .legend-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 900;
        }

        .legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 99px;
        }

        .legend-dot.task { background: #60a5fa; }
        .legend-dot.goal { background: #22c55e; }
        .legend-dot.project { background: #a855f7; }

        .section-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 14px;
        }

        .section-head h2, .card h2 {
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

        .calendar-list, .list {
            display: grid;
            gap: 10px;
        }

        .calendar-item, .item {
            border: 1px solid var(--line);
            border-radius: 18px;
            background: #ffffff;
        }

        .calendar-item {
            display: grid;
            grid-template-columns: 120px 1fr auto;
            gap: 14px;
            align-items: center;
            padding: 15px;
        }

        .calendar-date {
            font-weight: 900;
            color: var(--ink);
            font-size: 14px;
        }

        .calendar-type {
            display: inline-flex;
            width: fit-content;
            margin-bottom: 5px;
            padding: 5px 9px;
            border-radius: 999px;
            background: var(--blue-soft);
            color: #1d4ed8;
            font-size: 11px;
            font-weight: 900;
        }

        .calendar-title {
            margin: 0;
            color: var(--ink);
            font-weight: 900;
        }

        .calendar-meta {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 13px;
        }

        .calendar-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            padding: 10px 13px;
            background: #f8fafc;
            border: 1px solid var(--line);
            color: var(--ink);
            font-size: 13px;
            font-weight: 900;
            white-space: nowrap;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            align-items: start;
        }

        .item { padding: 15px; }

        .item-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
        }

        .item-title {
            margin: 0 0 6px;
            font-weight: 900;
            color: var(--ink);
        }

        .item-meta {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.45;
        }

        .badge {
            display: inline-flex;
            border-radius: 999px;
            padding: 5px 9px;
            font-size: 12px;
            font-weight: 900;
            white-space: nowrap;
        }

        .badge-green { background: #dcfce7; color: #166534; }
        .badge-blue { background: #dbeafe; color: #1d4ed8; }
        .badge-yellow { background: #fef3c7; color: #92400e; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .badge-gray { background: #f3f4f6; color: #374151; }

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
            .hero, .grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .summary-card { grid-template-columns: 1fr; }
            .calendar-shell { grid-template-columns: 1fr; }
            .calendar-item { grid-template-columns: 1fr; align-items: start; }
        }

        @media (max-width: 920px) {
            .calendar-day { min-height: 104px; padding: 8px; }
            .calendar-event { font-size: 10px; padding: 5px 6px; }
        }

        /* Responsive layout untuk layar mobile. */
        @media (max-width: 780px) {
            .app { display: block; }
            .sidebar { position: static; width: 100%; min-height: auto; }
            .content { margin-left: 0; width: 100%; max-width: 100%; padding: 18px; }
            .stats-grid { grid-template-columns: 1fr; }
            .btn { width: 100%; }
        }
    </style>
</head>
<body>

<!-- Container utama aplikasi dashboard. -->
<div class="app">

    <!-- Sidebar berisi branding, data user, menu navigasi, dan tombol logout. -->
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
            <span><?php echo e($email); ?></span>
        </div>

        <!-- Menu navigasi ke setiap fitur utama NadiOS. -->
        <nav class="nav">
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="goals.php">Goals</a>
            <a href="projects.php">Projects</a>
            <a href="tasks.php">Tasks</a>
            <a href="habits.php">Habits</a>
            <a href="journals.php">Journal</a>
            <a href="ai_assistant.php">Nara AI</a>
            <a href="index.php">Index</a>
        </nav>

        <div class="logout-box">
            <a href="logout.php" class="logout-link">Logout</a>
        </div>
    </aside>

    <!-- Konten utama dashboard. -->
    <main class="content">

        <!-- Hero dashboard berisi sapaan user dan tombol aksi cepat. -->
        <section class="hero">
            <div class="hero-main">
                <span class="eyebrow">Today’s mission control</span>
                <h1>Halo, <?php echo e($firstName); ?>.</h1>
                <p>
                    Selamat datang kembali di NadiOS. Di sini kamu bisa lihat target, project, tugas, kebiasaan, catatan, dan jadwal penting dalam satu tempat.
                </p>

                <div class="hero-actions">
                    <a href="goals.php" class="btn btn-primary">Tambah Goal</a>
                    <a href="projects.php" class="btn btn-secondary">Tambah Project</a>
                    <a href="tasks.php" class="btn btn-secondary">Tambah Task</a>
                    <a href="habits.php" class="btn btn-secondary">Tambah Habit</a>
                    <a href="journals.php" class="btn btn-secondary">Tulis Journal</a>
                    <a href="ai_assistant.php" class="btn btn-secondary">Tanya Nara AI</a>
                </div>
            </div>

            <div class="hero-side">
                <div class="mood-card">
                    <strong>Mulai dari yang Kecil</strong>
                    <span>
                        Pilih satu tugas yang paling mudah diselesaikan dulu. Kadang progres kecil bisa bikin ritme kerja jadi lebih enak.
                    </span>
                </div>

                <div class="mood-card">
                    <strong>Jangan Lupa Deadline!</strong>
                    <span>
                        Kalau task, goal, atau project punya tanggal, NadiOS akan bantu menampilkannya di kalender agar lebih mudah dipantau.
                    </span>
                </div>
            </div>
        </section>

        <!-- Statistik jumlah data dari setiap fitur utama. -->
        <section class="stats-grid">
            <div class="stat-card"><div class="stat-icon">G</div><h2><?php echo $totalGoals; ?></h2><p>Total Goals</p></div>
            <div class="stat-card"><div class="stat-icon">P</div><h2><?php echo $totalProjects; ?></h2><p>Total Projects</p></div>
            <div class="stat-card"><div class="stat-icon">T</div><h2><?php echo $totalTasks; ?></h2><p>Total Tasks</p></div>
            <div class="stat-card"><div class="stat-icon">H</div><h2><?php echo $totalHabits; ?></h2><p>Total Habits</p></div>
            <div class="stat-card"><div class="stat-icon">J</div><h2><?php echo $totalJournals; ?></h2><p>Total Journals</p></div>
        </section>

        <!-- Ringkasan progres berdasarkan status tertentu. -->
        <section class="summary-card">
            <div class="mini-card"><strong><?php echo $completedGoals; ?></strong><span>Goals Completed</span></div>
            <div class="mini-card"><strong><?php echo $activeProjects; ?></strong><span>Projects Active</span></div>
            <div class="mini-card"><strong><?php echo $doneTasks; ?></strong><span>Tasks Done</span></div>
        </section>

        <!-- Kalender bulanan yang merangkum deadline task, target goal, dan due date project. -->
        <section class="calendar-card" id="lifeos-calendar">
            <div class="section-head">
                <div>
                    <h2>NadiOS Calendar</h2>
                    <p>
                        Kalender ini merangkum jadwal dari Task, Goal, dan Project yang sudah kamu beri tanggal. Klik salah satu agenda untuk menambahkannya ke Google Calendar.
                    </p>
                    <div class="calendar-legend">
                        <span class="legend-pill"><span class="legend-dot task"></span>Task</span>
                        <span class="legend-pill"><span class="legend-dot goal"></span>Goal</span>
                        <span class="legend-pill"><span class="legend-dot project"></span>Project</span>
                    </div>
                </div>
            </div>

            <div class="calendar-shell">
                <div>
                    <div class="calendar-topbar">
                        <h3 class="calendar-month-title"><?php echo e(calendarMonthNameId($calendarMonthTitle)); ?></h3>
                        <div class="calendar-controls">
                            <a href="<?php echo e($previousCalendarUrl); ?>" class="calendar-control">‹</a>
                            <a href="<?php echo e($currentCalendarUrl); ?>" class="calendar-control">Bulan Ini</a>
                            <a href="<?php echo e($nextCalendarUrl); ?>" class="calendar-control">›</a>
                        </div>
                    </div>

                    <div class="month-calendar">
                        <div class="calendar-weekdays">
                            <div class="calendar-weekday">Min</div>
                            <div class="calendar-weekday">Sen</div>
                            <div class="calendar-weekday">Sel</div>
                            <div class="calendar-weekday">Rab</div>
                            <div class="calendar-weekday">Kam</div>
                            <div class="calendar-weekday">Jum</div>
                            <div class="calendar-weekday">Sab</div>
                        </div>

                        <div class="calendar-month-grid">
                            <?php for ($empty = 0; $empty < $calendarFirstDayIndex; $empty++) { ?>
                                <div class="calendar-day is-empty"></div>
                            <?php } ?>

                            <?php for ($day = 1; $day <= $calendarDaysInMonth; $day++) { ?>
                                <?php
                                    $currentDate = sprintf("%04d-%02d-%02d", $calendarYear, $calendarMonth, $day);
                                    $dayEvents = $calendarEventsByDate[$currentDate] ?? array();
                                    $visibleEvents = array_slice($dayEvents, 0, 3);
                                    $hiddenEventCount = max(0, count($dayEvents) - count($visibleEvents));
                                    $todayClass = ($currentDate === $calendarToday) ? " is-today" : "";
                                ?>
                                <div class="calendar-day<?php echo $todayClass; ?>">
                                    <div class="day-number"><?php echo $day; ?></div>
                                    <div class="calendar-event-list">
                                        <?php foreach ($visibleEvents as $event) { ?>
                                            <a
                                                href="<?php echo e(googleCalendarUrl($event["title"], $event["date"], $event["details"])); ?>"
                                                target="_blank"
                                                class="calendar-event <?php echo e(calendarTypeClass($event["type"])); ?>"
                                                title="<?php echo e($event["type"] . ': ' . $event["title"]); ?>"
                                            >
                                                <?php echo e($event["title"]); ?>
                                            </a>
                                        <?php } ?>

                                        <?php if ($hiddenEventCount > 0) { ?>
                                            <div class="more-events">+<?php echo $hiddenEventCount; ?> lainnya</div>
                                        <?php } ?>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <aside class="calendar-agenda">
                    <h3>Agenda Terdekat</h3>
                    <?php if (count($calendarPlans) === 0) { ?>
                        <div class="empty">
                            Belum ada rencana terjadwal. Tambahkan deadline pada Task, target date pada Goal,
                            atau due date pada Project agar muncul di kalender.
                        </div>
                    <?php } else { ?>
                        <div class="calendar-list">
                            <?php foreach (array_slice($calendarPlans, 0, 6) as $plan) { ?>
                                <article class="calendar-item">
                                    <div class="calendar-date"><?php echo formatTanggal($plan["date"]); ?></div>
                                    <div>
                                        <span class="calendar-type"><?php echo e($plan["type"]); ?></span>
                                        <p class="calendar-title"><?php echo e($plan["title"]); ?></p>
                                        <p class="calendar-meta">Status: <?php echo e($plan["status"]); ?></p>
                                    </div>
                                    <a href="<?php echo e(googleCalendarUrl($plan["title"], $plan["date"], $plan["details"])); ?>" target="_blank" class="calendar-link">
                                        Google Calendar
                                    </a>
                                </article>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </aside>
            </div>
        </section>

        <!-- Daftar Goals dan Projects terbaru. -->
        <section class="grid">
            <div class="card">
                <div class="section-head">
                    <div>
                        <h2>Goals Terbaru</h2>
                        <p>Target utama yang baru kamu buat.</p>
                    </div>
                </div>

                <?php if (count($recentGoals) === 0) { ?>
                    <div class="empty">Belum ada goal. Tambahkan goal pertama melalui halaman Goals.</div>
                <?php } else { ?>
                    <div class="list">
                        <?php foreach ($recentGoals as $goal) { ?>
                            <article class="item">
                                <div class="item-head">
                                    <div>
                                        <p class="item-title"><?php echo e($goal["title"]); ?></p>
                                        <p class="item-meta">Target: <?php echo formatTanggal($goal["target_date"]); ?></p>
                                    </div>
                                    <span class="badge <?php echo badgeClass($goal["status"]); ?>"><?php echo e($goal["status"]); ?></span>
                                </div>
                            </article>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>

            <div class="card">
                <div class="section-head">
                    <div>
                        <h2>Projects Terbaru</h2>
                        <p>Project yang sedang masuk radar NadiOS.</p>
                    </div>
                </div>

                <?php if (count($recentProjects) === 0) { ?>
                    <div class="empty">Belum ada project. Tambahkan project pertama melalui halaman Projects.</div>
                <?php } else { ?>
                    <div class="list">
                        <?php foreach ($recentProjects as $project) { ?>
                            <article class="item">
                                <div class="item-head">
                                    <div>
                                        <p class="item-title"><?php echo e($project["name"]); ?></p>
                                        <p class="item-meta">Deadline: <?php echo formatTanggal($project["due_date"]); ?></p>
                                    </div>
                                    <span class="badge <?php echo badgeClass($project["status"]); ?>"><?php echo e($project["status"]); ?></span>
                                </div>
                            </article>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        </section>

    </main>

</div>

</body>
</html>
