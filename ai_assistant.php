<?php
// Memanggil auth_check.php agar halaman Nara AI hanya bisa diakses oleh user yang sudah login.
require_once __DIR__ . "/auth_check.php";

// Menentukan lokasi file koneksi database. Sistem mengecek folder config terlebih dahulu.
$pathKoneksi = __DIR__ . "/config/koneksi.php";
// Jika koneksi tidak ada di folder config, sistem memakai koneksi.php di folder utama.
// Mengecek apakah file koneksi database tersedia sebelum digunakan.
if (!file_exists($pathKoneksi)) {
    $pathKoneksi = __DIR__ . "/koneksi.php";
}

if (!file_exists($pathKoneksi)) {
    die("File koneksi tidak ditemukan. Pastikan ada config/koneksi.php atau koneksi.php");
}

// Memanggil file koneksi agar variabel conn dapat digunakan.
require_once $pathKoneksi;

// Mengecek apakah variabel koneksi database sudah tersedia dari file koneksi.php.
if (!isset($conn)) {
    die("Variabel conn tidak tersedia. Cek file koneksi.php");
}

// Menentukan lokasi file konfigurasi AI yang berisi API key dan model provider.
$pathAiConfig = __DIR__ . "/config/ai_config.php";

// Mengecek apakah file konfigurasi AI tersedia sebelum Nara AI digunakan.
if (!file_exists($pathAiConfig)) {
    die("File config/ai_config.php belum ada.");
}

// Memanggil konfigurasi AI untuk membaca provider, API key, dan nama model.
require_once $pathAiConfig;

// Menentukan provider AI default. Jika tidak diatur, sistem menggunakan Gemini.
$aiProvider = $aiProvider ?? "gemini";

// Menyiapkan konfigurasi Gemini agar variabel tetap aman walaupun belum diisi di config.
$geminiApiKey = $geminiApiKey ?? "";
$geminiModel = $geminiModel ?? "gemini-2.5-flash-lite";

// Menyiapkan konfigurasi OpenAI sebagai opsi provider tambahan.
$openaiApiKey = $openaiApiKey ?? "";
$openaiModel = $openaiModel ?? "gpt-5.5";

// Menyimpan nama aplikasi yang ditampilkan pada sidebar dan title halaman.
$appName = "NadiOS";
// Menyimpan nama asisten AI yang digunakan di tampilan dan prompt.
$assistantName = "Nara AI";

// Mengambil data user dari session login.
$fullName = $_SESSION["full_name"] ?? "User";
$emailUser = $_SESSION["email"] ?? "";
// User ID dipakai agar Nara AI hanya membaca data milik akun yang sedang login.
$userId = (int) ($_SESSION["user_id"] ?? 0);
// Mengambil pertanyaan atau cerita user dari form.
$question = trim($_POST["question"] ?? "");
// Mengambil mode percakapan. Default-nya adalah mode produktivitas.
$mode = trim($_POST["mode"] ?? "productivity");
// Validasi mode agar hanya menerima productivity atau friend.
if (!in_array($mode, array("productivity", "friend"))) {
    $mode = "productivity";
}
$answer = "";
$error = "";

// Fungsi untuk mengamankan output HTML agar data tidak menyebabkan XSS.
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ""), ENT_QUOTES, "UTF-8");
}
// Fungsi untuk membersihkan output AI dari format Markdown yang tidak dibutuhkan di tampilan.
function cleanAiOutput($text): string
{
    $text = (string) ($text ?? "");

    $text = str_replace("**", "", $text);
    $text = str_replace("__", "", $text);
    $text = preg_replace('/^#{1,6}\s*/m', '', $text);
    $text = preg_replace('/^\s*[-*]\s+/m', '- ', $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);

    return trim($text);
}

// Fungsi untuk mengubah kode mode menjadi label yang mudah dibaca user.
function modeLabel($mode): string
{
    // Jika user memilih mode teman cerita, prompt dibuat lebih suportif dan santai.
    if ($mode === "friend") {
        return "Teman Cerita";
    }

    return "Produktivitas";
}

// Fungsi untuk mengecek apakah tabel tertentu tersedia di database.
function tableExists($conn, $tableName): bool
{
    $safeTable = mysqli_real_escape_string($conn, $tableName);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '" . $safeTable . "'");
    return $result && mysqli_num_rows($result) > 0;
}

// Fungsi untuk mengubah format tanggal database menjadi teks yang lebih mudah dibaca.
function formatTanggal($date): string
{
    if ($date === null || $date === "" || $date === "0000-00-00") {
        return "Tidak ada deadline";
    }

    return date("d M Y", strtotime($date));
}

// Fungsi untuk mengambil task aktif yang belum selesai sebagai konteks Nara AI.
function loadOpenTasks($conn, $userId): array
{
    $tasks = array();

    if (!tableExists($conn, "tasks")) {
        return $tasks;
    }

    if (tableExists($conn, "projects")) {
        $query = "
            SELECT tasks.*, projects.name AS project_name
            FROM tasks
            LEFT JOIN projects ON tasks.project_id = projects.id AND projects.user_id = tasks.user_id
            WHERE tasks.user_id = ?
            AND tasks.status NOT IN ('Done', 'Canceled')
            ORDER BY
                FIELD(tasks.priority, 'Urgent', 'High', 'Medium', 'Low'),
                tasks.due_date IS NULL,
                tasks.due_date ASC,
                tasks.created_at DESC
            LIMIT 10
        ";
    } else {
        $query = "
            SELECT tasks.*, NULL AS project_name
            FROM tasks
            WHERE tasks.user_id = ?
            AND tasks.status NOT IN ('Done', 'Canceled')
            ORDER BY
                FIELD(tasks.priority, 'Urgent', 'High', 'Medium', 'Low'),
                tasks.due_date IS NULL,
                tasks.due_date ASC,
                tasks.created_at DESC
            LIMIT 10
        ";
    }

    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $tasks[] = $row;
            }
        }

        mysqli_stmt_close($stmt);
    }

    return $tasks;
}

// Fungsi untuk mengambil goal aktif sebagai konteks Nara AI.
function loadActiveGoals($conn, $userId): array
{
    $goals = array();

    if (!tableExists($conn, "goals")) {
        return $goals;
    }

    $query = "
        SELECT * FROM goals
        WHERE user_id = ?
        AND status IN ('Not Started', 'In Progress')
        ORDER BY target_date IS NULL, target_date ASC, created_at DESC
        LIMIT 8
    ";

    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $goals[] = $row;
            }
        }

        mysqli_stmt_close($stmt);
    }

    return $goals;
}

// Fungsi untuk mengambil habit aktif yang belum dicentang hari ini sebagai konteks Nara AI.
function loadUncheckedHabits($conn, $userId): array
{
    $habits = array();
    $today = date("Y-m-d");

    if (!tableExists($conn, "habits") || !tableExists($conn, "habit_logs")) {
        return $habits;
    }

    $query = "
        SELECT habits.*
        FROM habits
        LEFT JOIN habit_logs
            ON habits.id = habit_logs.habit_id
            AND habit_logs.log_date = ?
        WHERE habits.user_id = ?
        AND habits.is_active = 1
        AND habit_logs.id IS NULL
        ORDER BY habits.created_at DESC
        LIMIT 8
    ";

    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "si", $today, $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $habits[] = $row;
            }
        }

        mysqli_stmt_close($stmt);
    }

    return $habits;
}

// Fungsi untuk mengubah data database menjadi teks konteks yang dapat dipahami oleh AI.
function arrayToContextText($title, $items, $type): string
{
    $text = $title . ":\n";

    if (count($items) === 0) {
        return $text . "- Tidak ada data.\n";
    }

    $number = 1;
    foreach ($items as $item) {
        if ($type === "task") {
            $projectName = $item["project_name"] ? " | Project: " . $item["project_name"] : "";
            $text .= $number . ". " . $item["title"] . " | Priority: " . $item["priority"] . " | Status: " . $item["status"] . " | Deadline: " . formatTanggal($item["due_date"]) . $projectName . "\n";
        }

        if ($type === "goal") {
            $text .= $number . ". " . $item["title"] . " | Status: " . $item["status"] . " | Target: " . formatTanggal($item["target_date"]) . "\n";
        }

        if ($type === "habit") {
            $text .= $number . ". " . $item["name"] . " | Frequency: " . $item["frequency"] . " | Target: " . $item["target_per_week"] . "x/minggu\n";
        }

        $number++;
    }

    return $text;
}

// Fungsi untuk menyusun prompt akhir berdasarkan pertanyaan, mode, dan data NadiOS milik user.
function buildPrompt($question, $tasks, $goals, $habits, $mode, $fullName): string
{
    $firstNameParts = preg_split('/\s+/', trim($fullName));
    $firstName = $firstNameParts[0] ?? "User";

    $baseRules = "Kamu adalah Nara AI, asisten di aplikasi NadiOS. Jawab dalam bahasa Indonesia yang natural, hangat, dan mudah dipahami. Jangan gunakan format Markdown. Jangan gunakan tanda bintang ganda seperti **. Jangan gunakan heading Markdown seperti ###. Jangan terdengar kaku atau seperti robot. Tulis dalam paragraf pendek.\n\n";

    $lifeosData = "KONTEKS DATA LIFEOS USER:\n";
    $lifeosData .= arrayToContextText("GOALS AKTIF", $goals, "goal") . "\n";
    $lifeosData .= arrayToContextText("TASK AKTIF", $tasks, "task") . "\n";
    $lifeosData .= arrayToContextText("HABIT BELUM DICENTANG HARI INI", $habits, "habit") . "\n";

    if ($mode === "friend") {
        $context = $baseRules;
        $context .= "Mode jawaban saat ini: TEMAN CERITA.\n";
        $context .= "Peranmu adalah menjadi teman ngobrol yang suportif untuk " . $firstName . ". Dengarkan cerita user, validasi perasaannya secara wajar, beri respons yang lembut dan tidak menghakimi. Jangan langsung menggurui. Jangan terlalu panjang. Jika cocok, ajukan satu pertanyaan lanjutan yang ringan agar percakapan terasa hidup.\n";
        $context .= "Kamu boleh memakai data NadiOS hanya jika relevan, misalnya saat user ingin menghubungkan cerita dengan task, habit, goal, atau journal. Jika tidak relevan, fokus pada percakapan dan perasaan user.\n";
        $context .= "Kamu bukan psikolog, dokter, atau layanan darurat. Jika user menunjukkan niat menyakiti diri sendiri atau orang lain, arahkan user untuk segera menghubungi orang terdekat yang dipercaya atau layanan darurat setempat.\n\n";
        $context .= $lifeosData . "\n";
        $context .= "Cerita atau pertanyaan user: " . $question . "\n\n";
        $context .= "Balas seperti teman yang peduli: hangat, sederhana, tidak berlebihan, dan tidak terlalu formal.";
        return $context;
    }

    $context = $baseRules;
    $context .= "Mode jawaban saat ini: PRODUKTIVITAS.\n";
    $context .= "Gunakan data user berikut sebagai konteks. Jangan mengarang data yang tidak ada. Berikan rekomendasi yang bisa langsung dikerjakan. Jika diminta jadwal, buat time-block sederhana. Jika diminta prioritas, urutkan berdasarkan urgensi dan deadline.\n\n";
    $context .= $lifeosData . "\n";
    $context .= "Pertanyaan user: " . $question . "\n\n";
    $context .= "Jawab dengan gaya natural, praktis, dan tidak terlalu kaku.";
    return $context;
}

// Fungsi untuk mengirim prompt ke Gemini API dan mengambil jawaban AI.
function callGeminiApi($apiKey, $model, $prompt): string
{
    if ($apiKey === "" || strpos($apiKey, "ISI_API_KEY") !== false) {
        return "API key Gemini belum diisi. Buka config/ai_config.php lalu isi \$geminiApiKey.";
    }

    if (!function_exists("curl_init")) {
        return "Ekstensi cURL PHP belum aktif. Aktifkan extension=curl di php.ini.";
    }

    $url = "https://generativelanguage.googleapis.com/v1beta/models/" . rawurlencode($model) . ":generateContent?key=" . urlencode($apiKey);

    $payload = array(
        "contents" => array(
            array(
                "parts" => array(
                    array("text" => $prompt)
                )
            )
        )
    );

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);

    if ($response === false) {
        $curlError = curl_error($ch);
        curl_close($ch);
        return "Gagal menghubungi Gemini API: " . $curlError;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($httpCode === 503) {
    return "Nara AI sedang penuh karena model Gemini mengalami lonjakan permintaan. Coba kirim ulang beberapa saat lagi, atau gunakan model yang lebih ringan seperti gemini-2.5-flash-lite.";
}

if ($httpCode < 200 || $httpCode >= 300) {
    return "Nara AI belum bisa menjawab saat ini. Kode error Gemini: HTTP " . $httpCode . ".";
}

    return $data["candidates"][0]["content"]["parts"][0]["text"] ?? "Gemini tidak mengembalikan teks.";
}

// Fungsi untuk mengirim prompt ke OpenAI API dan mengambil jawaban AI.
function callOpenAiApi($apiKey, $model, $prompt): string
{
    if ($apiKey === "" || strpos($apiKey, "ISI_API_KEY") !== false) {
        return "API key OpenAI belum diisi. Buka config/ai_config.php lalu isi \$openaiApiKey.";
    }

    if (!function_exists("curl_init")) {
        return "Ekstensi cURL PHP belum aktif. Aktifkan extension=curl di php.ini.";
    }

    $url = "https://api.openai.com/v1/responses";

    $payload = array(
        "model" => $model,
        "input" => $prompt
    );

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Content-Type: application/json",
        "Authorization: Bearer " . $apiKey
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);

    if ($response === false) {
        $curlError = curl_error($ch);
        curl_close($ch);
        return "Gagal menghubungi OpenAI API: " . $curlError;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($httpCode < 200 || $httpCode >= 300) {
        return "OpenAI API error HTTP " . $httpCode . ": " . $response;
    }

    if (isset($data["output_text"])) {
        return $data["output_text"];
    }

    if (isset($data["output"][0]["content"][0]["text"])) {
        return $data["output"][0]["content"][0]["text"];
    }

    return "OpenAI tidak mengembalikan teks.";
}

// Mengambil data task, goal, dan habit dari database sebagai konteks sebelum AI menjawab.
$openTasks = loadOpenTasks($conn, $userId);
$activeGoals = loadActiveGoals($conn, $userId);
$uncheckedHabits = loadUncheckedHabits($conn, $userId);

// Memproses pertanyaan user saat form Nara AI dikirim.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if ($question === "") {
        $answer = "Tulis pertanyaan terlebih dahulu.";
    } else {
        // Membuat prompt lengkap berdasarkan data NadiOS dan mode percakapan yang dipilih.
        $prompt = buildPrompt($question, $openTasks, $activeGoals, $uncheckedHabits, $mode, $fullName);

        // Memilih provider AI sesuai konfigurasi: Gemini atau OpenAI.
        if ($aiProvider === "gemini") {
    $answer = callGeminiApi($geminiApiKey, $geminiModel, $prompt);
} elseif ($aiProvider === "openai") {
    $answer = callOpenAiApi($openaiApiKey, $openaiModel, $prompt);
} else {
    $answer = "Provider AI tidak valid. Pilih 'gemini' atau 'openai' di config/ai_config.php.";
}

// Membersihkan jawaban AI sebelum ditampilkan ke user.
$answer = cleanAiOutput($answer);
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
    <title><?php echo e($assistantName); ?> - <?php echo e($appName); ?></title>

    <style>
        /* Reset dasar agar ukuran elemen lebih mudah dikontrol. */
        * { box-sizing: border-box; }

        /* Variabel warna, shadow, radius, dan tema visual halaman Nara AI. */
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
        textarea, button, input { font-family: inherit; }

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

        /* Area konten utama halaman Nara AI. */
        .content {
            margin-left: 280px;
            width: calc(100% - 280px);
            max-width: calc(100% - 280px);
            padding: 28px;
            overflow-x: hidden;
        }

        /* Bagian hero untuk memperkenalkan mode ngobrol Nara AI. */
        .hero {
            display: grid;
            grid-template-columns: 1.25fr 0.8fr;
            gap: 18px;
            margin-bottom: 18px;
        }

        .hero-main,
        .hero-side,
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
            background: linear-gradient(135deg, rgba(96, 165, 250, 0.23), rgba(168, 85, 247, 0.18));
        }

        .eyebrow {
            display: inline-flex;
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

        .hero-side {
            padding: 22px;
            display: grid;
            align-content: center;
            gap: 14px;
        }

        .mode-card {
            padding: 16px;
            border-radius: 20px;
            background: linear-gradient(135deg, #eaf1ff, #f1edff);
            border: 1px solid rgba(37, 99, 235, 0.12);
        }

        .mode-card strong {
            display: block;
            font-size: 18px;
            letter-spacing: -0.03em;
            margin-bottom: 6px;
        }

        .mode-card span {
            color: #6b7280;
            line-height: 1.5;
            font-size: 13px;
        }

        /* Kartu statistik konteks yang dibaca oleh Nara AI. */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
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

        .stat-card:nth-child(1) .stat-icon { background: var(--blue-soft); }
        .stat-card:nth-child(2) .stat-icon { background: var(--purple-soft); }
        .stat-card:nth-child(3) .stat-icon { background: var(--green-soft); }

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

        /* Layout utama untuk form pertanyaan dan panel jawaban AI. */
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

        /* Layout form pertanyaan Nara AI. */
        .form {
            display: grid;
            gap: 14px;
        }

        .textarea {
            width: 100%;
            min-height: 180px;
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 14px;
            outline: none;
            font-size: 14px;
            resize: vertical;
            color: var(--ink);
            background: #ffffff;
            line-height: 1.6;
        }

        .textarea:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.11);
        }

        /* Pilihan mode percakapan antara produktivitas dan teman cerita. */
        .mode-select {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .mode-option {
            position: relative;
        }

        .mode-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .mode-box {
            display: block;
            min-height: 78px;
            padding: 13px;
            border-radius: 18px;
            border: 1px solid var(--line);
            background: #ffffff;
            cursor: pointer;
            transition: 0.18s ease;
        }

        .mode-box strong {
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
            color: var(--ink);
        }

        .mode-box span {
            display: block;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.45;
        }

        .mode-option input:checked + .mode-box {
            border-color: rgba(37, 99, 235, 0.45);
            background: var(--blue-soft);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.08);
        }

        .mode-box:hover {
            transform: translateY(-1px);
        }

        @media (max-width: 560px) {
            .mode-select {
                grid-template-columns: 1fr;
            }
        }

        /* Tombol prompt cepat untuk membantu user bertanya lebih mudah. */
        .quick {
            display: grid;
            gap: 10px;
            margin-top: 14px;
        }

        .quick button {
            text-align: left;
            background: #ffffff;
            border: 1px solid var(--line);
            padding: 13px 14px;
            border-radius: 16px;
            cursor: pointer;
            color: var(--ink);
            font-weight: 800;
            transition: 0.18s ease;
        }

        .quick button:hover {
            background: #f8fafc;
            transform: translateY(-1px);
        }

        .note {
            margin-top: 14px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.6;
        }

        /* Box jawaban Nara AI. */
        .answer {
            white-space: pre-wrap;
            line-height: 1.85;
            color: #263244;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid var(--line);
            padding: 22px;
            border-radius: 20px;
            font-size: 15px;
            min-height: 240px;
        }

        .ai-header-card {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 18px;
        }

        .ai-avatar {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            background: var(--blue);
            color: #ffffff;
            display: grid;
            place-items: center;
            font-weight: 900;
            letter-spacing: -0.04em;
        }

        .ai-title {
            margin: 0;
            font-size: 22px;
            letter-spacing: -0.04em;
        }

        .ai-subtitle {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.5;
        }

        .ai-status {
            display: inline-flex;
            width: fit-content;
            margin-bottom: 14px;
            padding: 7px 10px;
            border-radius: 999px;
            background: var(--blue-soft);
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 900;
        }

        /* Responsive layout untuk layar tablet dan ukuran sedang. */
        @media (max-width: 1180px) {
            .hero,
            .grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
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
            <a href="habits.php">Habits</a>
            <a href="journals.php">Journal</a>
            <a href="ai_assistant.php" class="active"><?php echo e($assistantName); ?></a>
            <a href="index.php">Index</a>
        </nav>

        <div class="logout-box">
            <a href="logout.php" class="logout-link">Logout</a>
        </div>
    </aside>

    <!-- Konten utama halaman Nara AI. -->
    <main class="content">

        <!-- Hero halaman Nara AI berisi sapaan dan penjelasan singkat. -->
        <section class="hero">
            <div class="hero-main">
                <span class="eyebrow">Nara AI workspace</span>
                <h1>Hai Aku <?php echo e($assistantName); ?>, <?php echo e($firstName); ?>👋</h1>
                <p>
                    Nara bisa bantu kamu merapikan rencana, menyusun prioritas, atau sekadar menemani kamu cerita saat butuh teman ngobrol.
                </p>

                <div class="hero-actions">
                    <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
                    <a href="ai_assistant.php" class="btn btn-primary">Refresh</a>
                </div>
            </div>

            <div class="hero-side">
                <div class="mode-card">
                    <strong>Pilih cara Ngobrol</strong>
                    <span>Pakai mode produktivitas kalau mau bahas task, goal, habit, atau jadwal. Pakai mode teman cerita kalau kamu cuma ingin cerita santai.</span>
                </div>

                <div class="mode-card">
                    <strong>Tulis Santai aja</strong>
                    <span>
                        Nggak perlu pakai format khusus. Tulis seperti kamu sedang ngobrol biasa, nanti Nara bantu merespons dengan pelan-pelan.
                    </span>
                </div>
            </div>
        </section>

        <!-- Statistik data yang menjadi konteks Nara AI. -->
        <section class="stats-grid">
            <div class="stat-card"><div class="stat-icon">TS</div><h2><?php echo count($openTasks); ?></h2><p>Open Tasks</p></div>
            <div class="stat-card"><div class="stat-icon">GL</div><h2><?php echo count($activeGoals); ?></h2><p>Active Goals</p></div>
            <div class="stat-card"><div class="stat-icon">HB</div><h2><?php echo count($uncheckedHabits); ?></h2><p>Unchecked Habits</p></div>
        </section>

        <!-- Area utama berisi form pertanyaan dan jawaban Nara AI. -->
        <section class="grid">
            <div class="card">
                <div class="section-head">
                    <div>
                        <h2>Tanya Nara AI</h2>
                        <p>Pilih mode yang kamu butuhkan, lalu tulis apa yang ingin kamu bahas.</p>
                    </div>
                </div>

                <!-- Form untuk mengirim pertanyaan atau cerita ke Nara AI. -->
                <form class="form" action="ai_assistant.php" method="POST">
                    <textarea
                        class="textarea"
                        name="question"
                        placeholder="Contoh produktivitas: Buatkan prioritas hari ini.&#10;Contoh teman cerita: Aku lagi capek dan butuh teman ngobrol."
                    ><?php echo e($question); ?></textarea>

                    <div class="mode-select">
                        <label class="mode-option">
                            <input type="radio" name="mode" value="productivity" <?php if ($mode === "productivity") { echo "checked"; } ?>>
                            <span class="mode-box">
                                <strong>Produktivitas</strong>
                                <span>Untuk prioritas, jadwal, task, goal, dan habit.</span>
                            </span>
                        </label>

                        <label class="mode-option">
                            <input type="radio" name="mode" value="friend" <?php if ($mode === "friend") { echo "checked"; } ?>>
                            <span class="mode-box">
                                <strong>Teman Cerita</strong>
                                <span>Untuk ngobrol santai, refleksi, dan cerita harian.</span>
                            </span>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary">Kirim ke Nara AI</button>
                </form>

                <!-- Prompt cepat agar user bisa langsung mencoba fitur Nara AI. -->
                <div class="quick">
                    <form action="ai_assistant.php" method="POST">
                        <input type="hidden" name="mode" value="productivity">
                        <input type="hidden" name="question" value="Buatkan prioritas hari ini">
                        <button type="submit">Buatkan prioritas hari ini</button>
                    </form>

                    <form action="ai_assistant.php" method="POST">
                        <input type="hidden" name="mode" value="productivity">
                        <input type="hidden" name="question" value="Buatkan jadwal hari ini">
                        <button type="submit">Buatkan jadwal hari ini</button>
                    </form>

                    <form action="ai_assistant.php" method="POST">
                        <input type="hidden" name="mode" value="productivity">
                        <input type="hidden" name="question" value="Habit apa yang belum saya lakukan">
                        <button type="submit">Habit apa yang belum saya lakukan?</button>
                    </form>

                    <form action="ai_assistant.php" method="POST">
                        <input type="hidden" name="mode" value="friend">
                        <input type="hidden" name="question" value="Aku ingin refleksi hari ini, bantu aku mulai pelan-pelan">
                        <button type="submit">Bantu aku refleksi hari ini</button>
                    </form>

                    <form action="ai_assistant.php" method="POST">
                        <input type="hidden" name="mode" value="friend">
                        <input type="hidden" name="question" value="Aku lagi butuh teman cerita. Tolong dengarkan aku dulu.">
                        <button type="submit">Aku mau cerita</button>
                    </form>

                    <form action="ai_assistant.php" method="POST">
                        <input type="hidden" name="mode" value="friend">
                        <input type="hidden" name="question" value="Aku lagi capek. Bantu aku menenangkan pikiran dengan cara sederhana.">
                        <button type="submit">Bantu tenangkan pikiran</button>
                    </form>
                </div>

        
            </div>

            <div class="card">
                <!-- Header panel jawaban Nara AI. -->
                <div class="ai-header-card">
                    <div class="ai-avatar">NA</div>
                    <div>
                        <h2 class="ai-title"><?php echo e($assistantName); ?></h2>
                        <p class="ai-subtitle">Nara akan menyesuaikan jawaban dengan mode yang kamu pilih.</p>
                    </div>
                </div>

                <div class="ai-status">Mode: <?php echo e(modeLabel($mode)); ?> · <?php echo e(strtoupper($aiProvider)); ?></div>

                <?php if ($answer !== "") { ?>
                    <div class="answer"><?php echo e(cleanAiOutput($answer)); ?></div>
                <?php } else { ?>
                    <div class="answer">Belum ada obrolan. Pilih mode, lalu tulis apa yang ingin kamu bahas hari ini ya..😉</div>
                <?php } ?>
            </div>
        </section>

    </main>
</div>

</body>
</html>
