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

// Mengecek apakah file koneksi database tersedia sebelum digunakan.
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
// User ID dipakai untuk memastikan data tasks hanya milik akun yang sedang login.
$userId = (int) ($_SESSION["user_id"] ?? 0);

// Menentukan folder fisik dan URL untuk menyimpan lampiran task yang diupload.
$uploadDir = __DIR__ . "/uploads/tasks/";
$uploadUrl = "uploads/tasks/";

// Membuat folder upload tasks jika folder belum tersedia.
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

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

// Fungsi untuk menentukan class warna badge berdasarkan status task.
function statusBadgeClass($status): string
{
    if ($status === "Doing") {
        return "badge-green";
    }

    if ($status === "Done") {
        return "badge-blue";
    }

    if ($status === "Canceled") {
        return "badge-red";
    }

    return "badge-gray";
}

// Fungsi untuk menentukan class warna badge berdasarkan prioritas task.
function priorityBadgeClass($priority): string
{
    if ($priority === "Urgent") {
        return "badge-red";
    }

    if ($priority === "High") {
        return "badge-yellow";
    }

    if ($priority === "Medium") {
        return "badge-blue";
    }

    return "badge-gray";
}

// Fungsi untuk mengecek apakah tabel tertentu tersedia di database.
function tableExists($conn, $tableName): bool
{
    $safeTable = mysqli_real_escape_string($conn, $tableName);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '" . $safeTable . "'");
    return $result && mysqli_num_rows($result) > 0;
}

// Fungsi untuk mengecek apakah kolom tertentu tersedia pada tabel database.
function columnExists($conn, $tableName, $columnName): bool
{
    $safeTable = mysqli_real_escape_string($conn, $tableName);
    $safeColumn = mysqli_real_escape_string($conn, $columnName);
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `$safeTable` LIKE '" . $safeColumn . "'");
    return $result && mysqli_num_rows($result) > 0;
}

// Fungsi untuk memproses upload lampiran task, termasuk validasi ukuran dan format file.
function uploadAttachment($fieldName, $uploadDir, $uploadUrl, &$error)
{
    if (!isset($_FILES[$fieldName])) {
        return null;
    }

    if ($_FILES[$fieldName]["error"] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES[$fieldName]["error"] !== UPLOAD_ERR_OK) {
        $error = "Upload lampiran gagal.";
        return false;
    }

    if ($_FILES[$fieldName]["size"] > 5 * 1024 * 1024) {
        $error = "Ukuran lampiran maksimal 5 MB.";
        return false;
    }

    $extension = strtolower(pathinfo($_FILES[$fieldName]["name"], PATHINFO_EXTENSION));
    $allowedExtensions = array("pdf", "doc", "docx", "xls", "xlsx", "ppt", "pptx", "txt", "zip", "jpg", "jpeg", "png", "webp");

    if (!in_array($extension, $allowedExtensions)) {
        $error = "Format lampiran harus PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, ZIP, JPG, JPEG, PNG, atau WEBP.";
        return false;
    }

    $newFileName = "task_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $extension;
    $targetPath = $uploadDir . $newFileName;

    if (!move_uploaded_file($_FILES[$fieldName]["tmp_name"], $targetPath)) {
        $error = "Lampiran gagal disimpan ke folder uploads/tasks.";
        return false;
    }

    return $uploadUrl . $newFileName;
}

// Fungsi untuk menghapus file lampiran lama dari folder uploads/tasks.
function deleteAttachmentFile($attachmentPath): void
{
    if ($attachmentPath !== "") {
        $fullPath = __DIR__ . "/" . $attachmentPath;
        if (file_exists($fullPath) && is_file($fullPath)) {
            unlink($fullPath);
        }
    }
}

// Fungsi untuk mengambil nama file lampiran dari path penyimpanan.
function attachmentFileName($attachmentPath): string
{
    if ($attachmentPath === null || $attachmentPath === "") {
        return "-";
    }

    return basename($attachmentPath);
}

// Mengecek keberadaan tabel tasks sebelum fitur CRUD dijalankan.
if (!tableExists($conn, "tasks")) {
    $error = "Tabel tasks belum ada. Jalankan file tasks_table.sql di phpMyAdmin.";
// Menambahkan kolom attachment secara otomatis jika tabel tasks belum memilikinya.
} elseif (!columnExists($conn, "tasks", "attachment")) {
    mysqli_query($conn, "ALTER TABLE tasks ADD COLUMN attachment VARCHAR(255) NULL AFTER due_date");
}

// Menyiapkan array projects untuk pilihan project terkait pada form task.
$projects = array();
// Mengambil daftar projects agar task bisa dikaitkan dengan project tertentu.
if (tableExists($conn, "projects")) {
    $projectStmt = mysqli_prepare($conn, "SELECT id, name FROM projects WHERE user_id = ? ORDER BY name ASC");
    if ($projectStmt) {
        mysqli_stmt_bind_param($projectStmt, "i", $userId);
        mysqli_stmt_execute($projectStmt);
        $projectResult = mysqli_stmt_get_result($projectStmt);

        if ($projectResult) {
            while ($row = mysqli_fetch_assoc($projectResult)) {
                $projects[] = $row;
            }
        }

        mysqli_stmt_close($projectStmt);
    }
}

// Menyiapkan mode edit dan data default untuk form task.
$editMode = false;
$editData = array(
    "id" => "",
    "project_id" => "",
    "title" => "",
    "description" => "",
    "priority" => "Medium",
    "status" => "Todo",
    "due_date" => "",
    "attachment" => ""
);

// Proses menghapus task berdasarkan id dari parameter URL.
if ($error === "" && isset($_GET["delete"])) {
    $deleteId = (int) $_GET["delete"];

    if ($deleteId > 0) {
        $oldAttachment = "";

        // Mengambil lampiran lama sebelum task dihapus agar file lampiran juga bisa dihapus.
        $selectStmt = mysqli_prepare($conn, "SELECT attachment FROM tasks WHERE id = ? AND user_id = ?");
        if ($selectStmt) {
            mysqli_stmt_bind_param($selectStmt, "ii", $deleteId, $userId);
            mysqli_stmt_execute($selectStmt);
            $selectResult = mysqli_stmt_get_result($selectStmt);

            if ($selectResult && mysqli_num_rows($selectResult) > 0) {
                $row = mysqli_fetch_assoc($selectResult);
                $oldAttachment = $row["attachment"] ?? "";
            }

            mysqli_stmt_close($selectStmt);
        }

        // Query untuk menghapus data task menggunakan prepared statement.
        $deleteStmt = mysqli_prepare($conn, "DELETE FROM tasks WHERE id = ? AND user_id = ?");

        if ($deleteStmt) {
            mysqli_stmt_bind_param($deleteStmt, "ii", $deleteId, $userId);

            if (mysqli_stmt_execute($deleteStmt)) {
                deleteAttachmentFile($oldAttachment);
                header("Location: tasks.php?message=deleted");
                exit;
            } else {
                $error = "Task gagal dihapus.";
            }

            mysqli_stmt_close($deleteStmt);
        }
    }
}

// Proses menandai task sebagai Done berdasarkan id dari parameter URL.
if ($error === "" && isset($_GET["done"])) {
    $doneId = (int) $_GET["done"];

    if ($doneId > 0) {
        // Menentukan status akhir ketika tombol Done ditekan.
        $doneStatus = "Done";
        // Query untuk memperbarui status task menjadi Done.
        $doneStmt = mysqli_prepare($conn, "UPDATE tasks SET status = ? WHERE id = ? AND user_id = ?");

        if ($doneStmt) {
            mysqli_stmt_bind_param($doneStmt, "sii", $doneStatus, $doneId, $userId);

            if (mysqli_stmt_execute($doneStmt)) {
                header("Location: tasks.php?message=done");
                exit;
            } else {
                $error = "Task gagal ditandai selesai.";
            }

            mysqli_stmt_close($doneStmt);
        }
    }
}

// Proses mengambil data task yang akan diedit berdasarkan id dari URL.
if ($error === "" && isset($_GET["edit"])) {
    $editId = (int) $_GET["edit"];

    if ($editId > 0) {
        // Query untuk mengambil detail task yang akan dimasukkan ke form edit.
        $editStmt = mysqli_prepare($conn, "SELECT * FROM tasks WHERE id = ? AND user_id = ?");

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

// Menampilkan pesan sukses berdasarkan query string setelah create, update, delete, atau done.
if (isset($_GET["message"])) {
    if ($_GET["message"] === "created") {
        $success = "Task berhasil ditambahkan.";
    }

    if ($_GET["message"] === "updated") {
        $success = "Task berhasil diperbarui.";
    }

    if ($_GET["message"] === "deleted") {
        $success = "Task berhasil dihapus.";
    }

    if ($_GET["message"] === "done") {
        $success = "Task berhasil ditandai selesai.";
    }
}

// Proses create atau update task ketika form dikirim menggunakan method POST.
if ($error === "" && $_SERVER["REQUEST_METHOD"] === "POST") {
    // Mengambil input dari form dan membersihkan nilai awalnya.
    $taskId = (int) ($_POST["task_id"] ?? 0);
    $projectId = trim($_POST["project_id"] ?? "");
    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $priority = trim($_POST["priority"] ?? "Medium");
    $status = trim($_POST["status"] ?? "Todo");
    $dueDate = trim($_POST["due_date"] ?? "");
    $oldAttachment = trim($_POST["old_attachment"] ?? "");

    // Validasi input form agar judul, prioritas, dan status task sesuai aturan.
    if ($title === "") {
        $error = "Judul task wajib diisi.";
    } elseif (!in_array($priority, array("Low", "Medium", "High", "Urgent"))) {
        $error = "Prioritas task tidak valid.";
    } elseif (!in_array($status, array("Todo", "Doing", "Done", "Canceled"))) {
        $error = "Status task tidak valid.";
    } else {
        // Mengubah project_id menjadi null jika task tidak dikaitkan dengan project.
        $projectIdValue = null;
        if ($projectId !== "") {
            $projectIdValue = (int) $projectId;
        }

        if ($projectIdValue !== null) {
            $validProject = false;
            foreach ($projects as $project) {
                if ((int) $project["id"] === (int) $projectIdValue) {
                    $validProject = true;
                    break;
                }
            }

            if (!$validProject) {
                $error = "Project terkait tidak valid untuk akun ini.";
            }
        }

        if ($description === "") {
            $description = null;
        }

        if ($dueDate === "") {
            $dueDate = null;
        }

        if ($error !== "") {
            // Jika validasi project gagal, proses upload dan simpan data dihentikan.
        } else {
        // Memproses upload lampiran baru jika user memilih file lampiran.
        $uploadedAttachment = uploadAttachment("attachment", $uploadDir, $uploadUrl, $error);

        if ($uploadedAttachment !== false) {
            $finalAttachment = $oldAttachment;

            if ($uploadedAttachment !== null) {
                $finalAttachment = $uploadedAttachment;
            }

            // Jika task_id lebih dari 0, sistem menjalankan proses update task.
            if ($taskId > 0) {
                // Query untuk memperbarui data task berdasarkan id.
                $updateQuery = "UPDATE tasks SET project_id = ?, title = ?, description = ?, priority = ?, status = ?, due_date = ?, attachment = ? WHERE id = ? AND user_id = ?";
                $updateStmt = mysqli_prepare($conn, $updateQuery);

                if ($updateStmt) {
                    mysqli_stmt_bind_param($updateStmt, "issssssii", $projectIdValue, $title, $description, $priority, $status, $dueDate, $finalAttachment, $taskId, $userId);

                    if (mysqli_stmt_execute($updateStmt)) {
                        if ($uploadedAttachment !== null && $oldAttachment !== "") {
                            deleteAttachmentFile($oldAttachment);
                        }

                        header("Location: tasks.php?message=updated");
                        exit;
                    } else {
                        $error = "Task gagal diperbarui.";
                    }

                    mysqli_stmt_close($updateStmt);
                }
            } else {
                // Query untuk menambahkan task baru ke database.
                $insertQuery = "INSERT INTO tasks (user_id, project_id, title, description, priority, status, due_date, attachment) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $insertStmt = mysqli_prepare($conn, $insertQuery);

                if ($insertStmt) {
                    mysqli_stmt_bind_param($insertStmt, "iissssss", $userId, $projectIdValue, $title, $description, $priority, $status, $dueDate, $finalAttachment);

                    if (mysqli_stmt_execute($insertStmt)) {
                        header("Location: tasks.php?message=created");
                        exit;
                    } else {
                        $error = "Task gagal ditambahkan.";
                    }

                    mysqli_stmt_close($insertStmt);
                }
            }
        }
        }
    }
}

// Menyiapkan array untuk menampung seluruh data tasks dari database.
$tasks = array();

// Mengambil semua data tasks untuk ditampilkan pada tabel daftar tasks.
if (tableExists($conn, "tasks")) {
    $joinProjects = tableExists($conn, "projects");

    // Jika tabel projects tersedia, data task digabung dengan nama project terkait.
    if ($joinProjects) {
        $listQuery = "SELECT tasks.*, projects.name AS project_name FROM tasks LEFT JOIN projects ON tasks.project_id = projects.id AND projects.user_id = tasks.user_id WHERE tasks.user_id = ? ORDER BY tasks.created_at DESC";
    } else {
        $listQuery = "SELECT tasks.*, NULL AS project_name FROM tasks WHERE tasks.user_id = ? ORDER BY tasks.created_at DESC";
    }

    $listStmt = mysqli_prepare($conn, $listQuery);
    if ($listStmt) {
        mysqli_stmt_bind_param($listStmt, "i", $userId);
        mysqli_stmt_execute($listStmt);
        $listResult = mysqli_stmt_get_result($listStmt);

        if ($listResult) {
            while ($row = mysqli_fetch_assoc($listResult)) {
                $tasks[] = $row;
            }
        }

        mysqli_stmt_close($listStmt);
    }
}

// Menghitung total tasks dan statistik status/prioritas untuk kartu ringkasan.
$totalTasks = count($tasks);
$totalTodo = 0;
$totalDoing = 0;
$totalDone = 0;
$totalUrgent = 0;

// Melakukan perulangan untuk menghitung jumlah task berdasarkan status dan prioritas urgent.
foreach ($tasks as $task) {
    if ($task["status"] === "Todo") {
        $totalTodo++;
    }

    if ($task["status"] === "Doing") {
        $totalDoing++;
    }

    if ($task["status"] === "Done") {
        $totalDone++;
    }

    if ($task["priority"] === "Urgent") {
        $totalUrgent++;
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
    <title>Tasks - <?php echo e($appName); ?></title>

    <style>
        /* Reset dasar agar ukuran elemen lebih mudah dikontrol. */
        * { box-sizing: border-box; }

        /* Variabel warna, shadow, radius, dan tema visual halaman tasks. */
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

        /* Grid statistik untuk menampilkan jumlah task berdasarkan status dan prioritas. */
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
        .stat-card:nth-child(2) .stat-icon { background: var(--blue-soft); }
        .stat-card:nth-child(3) .stat-icon { background: var(--green-soft); }
        .stat-card:nth-child(4) .stat-icon { background: var(--yellow-soft); }
        .stat-card:nth-child(5) .stat-icon { background: var(--red-soft); }

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

        /* Layout utama yang membagi form task dan daftar tasks. */
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

        /* Pengaturan layout form tambah/edit task. */
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

        textarea.form-control {
            min-height: 118px;
            resize: vertical;
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
            min-width: 900px;
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

        .task-title {
            font-weight: 900;
            color: var(--ink);
        }

        .task-desc {
            margin-top: 5px;
            color: var(--muted);
            line-height: 1.45;
            max-width: 380px;
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
        .badge-gray { background: #f3f4f6; color: #374151; }

        /* Style link lampiran task yang sudah diupload. */
        .attachment-link {
            display: inline-flex;
            width: fit-content;
            padding: 7px 10px;
            border-radius: 12px;
            background: var(--blue-soft);
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 900;
            line-height: 1.4;
            margin-top: 8px;
        }

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
            <a href="tasks.php" class="active">Tasks</a>
            <a href="habits.php">Habits</a>
            <a href="journals.php">Journal</a>
            <a href="ai_assistant.php">Nara AI</a>
            <a href="index.php">Index</a>
        </nav>

        <div class="logout-box">
            <a href="logout.php" class="logout-link">Logout</a>
        </div>
    </aside>

    <!-- Konten utama halaman Tasks. -->
    <main class="content">

        <!-- Hero halaman Tasks berisi sapaan dan tombol aksi. -->
        <section class="hero">
            <div class="hero-main">
                <span class="eyebrow">Tasks workspace</span>
                <h1>Ubah rencana jadi aksi, <?php echo e($firstName); ?>.</h1>
                <p>
                    Catat tugas yang perlu kamu kerjakan, hubungkan dengan project terkait, lalu atur prioritas, status, dan deadline agar semuanya lebih mudah dipantau.
                </p>

                <div class="hero-actions">
                    <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
                    <a href="tasks.php" class="btn btn-primary">Refresh</a>
                </div>
            </div>

            <div class="hero-side">
                <div class="tip-card">
                    <strong>Tugas Kecil lebih Mudah Jalan</strong>
                    <span>
                        Buat task yang spesifik dan tidak terlalu besar. Semakin jelas tugasnya, semakin mudah untuk mulai mengerjakannya.
                    </span>
                </div>

                <div class="tip-card">
                    <strong>Prioritas Bantu Fokus</strong>
                    <span>
                        Gunakan prioritas dan deadline supaya kamu tahu tugas mana yang perlu dikerjakan lebih dulu hari ini.
                    </span>
                </div>
            </div>
        </section>

        <!-- Kartu statistik jumlah task berdasarkan status dan prioritas. -->
        <section class="stats-grid">
            <div class="stat-card"><div class="stat-icon">TT</div><h2><?php echo $totalTasks; ?></h2><p>Total Tasks</p></div>
            <div class="stat-card"><div class="stat-icon">TD</div><h2><?php echo $totalTodo; ?></h2><p>Todo</p></div>
            <div class="stat-card"><div class="stat-icon">DG</div><h2><?php echo $totalDoing; ?></h2><p>Doing</p></div>
            <div class="stat-card"><div class="stat-icon">DN</div><h2><?php echo $totalDone; ?></h2><p>Done</p></div>
            <div class="stat-card"><div class="stat-icon">UR</div><h2><?php echo $totalUrgent; ?></h2><p>Urgent</p></div>
        </section>

        <!-- Area utama berisi form tambah/edit task dan tabel daftar tasks. -->
        <section class="grid">
            <div class="card">
                <div class="section-head">
                    <div>
                        <h2><?php echo $editMode ? "Edit Task" : "Tambah Task"; ?></h2>
                        <p>Masukkan task yang ingin kamu kerjakan.</p>
                    </div>
                </div>

                <?php if ($error !== "") { ?>
                    <div class="alert alert-error"><?php echo e($error); ?></div>
                <?php } ?>

                <?php if ($success !== "") { ?>
                    <div class="alert alert-success"><?php echo e($success); ?></div>
                <?php } ?>

                <!-- Form untuk menambah atau memperbarui data task. -->
                <form class="form" action="tasks.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="task_id" value="<?php echo e($editData["id"]); ?>">
                    <input type="hidden" name="old_attachment" value="<?php echo e($editData["attachment"] ?? ""); ?>">

                    <div class="form-group">
                        <label for="project_id">Project Terkait</label>
                        <select id="project_id" name="project_id" class="form-control">
                            <option value="">Tidak dikaitkan</option>
                            <?php foreach ($projects as $project) { ?>
                                <option value="<?php echo e($project["id"]); ?>" <?php if ((string) $editData["project_id"] === (string) $project["id"]) { echo "selected"; } ?>>
                                    <?php echo e($project["name"]); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="title">Judul Task</label>
                        <input
                            type="text"
                            id="title"
                            name="title"
                            class="form-control"
                            placeholder="Contoh: Membuat halaman login"
                            value="<?php echo e($editData["title"]); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="description">Deskripsi</label>
                        <textarea
                            id="description"
                            name="description"
                            class="form-control"
                            placeholder="Tuliskan detail singkat task"
                        ><?php echo e($editData["description"]); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="priority">Prioritas</label>
                        <select id="priority" name="priority" class="form-control" required>
                            <option value="Low" <?php if ($editData["priority"] === "Low") { echo "selected"; } ?>>Low</option>
                            <option value="Medium" <?php if ($editData["priority"] === "Medium") { echo "selected"; } ?>>Medium</option>
                            <option value="High" <?php if ($editData["priority"] === "High") { echo "selected"; } ?>>High</option>
                            <option value="Urgent" <?php if ($editData["priority"] === "Urgent") { echo "selected"; } ?>>Urgent</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="Todo" <?php if ($editData["status"] === "Todo") { echo "selected"; } ?>>Todo</option>
                            <option value="Doing" <?php if ($editData["status"] === "Doing") { echo "selected"; } ?>>Doing</option>
                            <option value="Done" <?php if ($editData["status"] === "Done") { echo "selected"; } ?>>Done</option>
                            <option value="Canceled" <?php if ($editData["status"] === "Canceled") { echo "selected"; } ?>>Canceled</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="due_date">Deadline</label>
                        <input
                            type="date"
                            id="due_date"
                            name="due_date"
                            class="form-control"
                            value="<?php echo e($editData["due_date"]); ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="attachment">Lampiran Task</label>
                        <input
                            type="file"
                            id="attachment"
                            name="attachment"
                            class="form-control"
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.jpg,.jpeg,.png,.webp"
                        >

                        <?php if ($editMode && !empty($editData["attachment"])) { ?>
                            <a href="<?php echo e($editData["attachment"]); ?>" target="_blank" class="attachment-link">
                                Lihat lampiran saat ini: <?php echo e(attachmentFileName($editData["attachment"])); ?>
                            </a>
                        <?php } ?>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $editMode ? "Update Task" : "Tambah Task"; ?>
                        </button>

                        <?php if ($editMode) { ?>
                            <a href="tasks.php" class="btn btn-secondary">Batal Edit</a>
                        <?php } ?>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="section-head">
                    <div>
                        <h2>Daftar Tasks</h2>
                        <p>Semua task yang sudah kamu simpan di NadiOS.</p>
                    </div>
                </div>

                <?php if (count($tasks) === 0) { ?>
                    <div class="empty">Belum ada task. Tambahkan task pertama melalui form.</div>
                <?php } else { ?>
                    <!-- Tabel daftar tasks yang sudah tersimpan di database. -->
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Task</th>
                                    <th>Project</th>
                                    <th>Prioritas</th>
                                    <th>Status</th>
                                    <th>Deadline</th>
                                    <th>Lampiran</th>
                                    <th>Dibuat</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tasks as $task) { ?>
                                    <tr>
                                        <td>
                                            <div class="task-title"><?php echo e($task["title"]); ?></div>
                                            <div class="task-desc">
                                                <?php
                                                if ($task["description"]) {
                                                    echo e($task["description"]);
                                                } else {
                                                    echo "Tidak ada deskripsi.";
                                                }
                                                ?>
                                            </div>
                                        </td>
                                        <td><?php echo e($task["project_name"] ?? "-"); ?></td>
                                        <td>
                                            <span class="badge <?php echo priorityBadgeClass($task["priority"]); ?>">
                                                <?php echo e($task["priority"]); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo statusBadgeClass($task["status"]); ?>">
                                                <?php echo e($task["status"]); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatTanggal($task["due_date"]); ?></td>
                                        <td>
                                            <?php if (!empty($task["attachment"])) { ?>
                                                <a href="<?php echo e($task["attachment"]); ?>" target="_blank" class="attachment-link">
                                                    <?php echo e(attachmentFileName($task["attachment"])); ?>
                                                </a>
                                            <?php } else { ?>
                                                -
                                            <?php } ?>
                                        </td>
                                        <td><?php echo formatTanggal($task["created_at"]); ?></td>
                                        <td>
                                            <div class="action-row">
                                                <?php if ($task["status"] !== "Done") { ?>
                                                    <a href="tasks.php?done=<?php echo $task["id"]; ?>" class="btn btn-success">Done</a>
                                                <?php } ?>
                                                <a href="tasks.php?edit=<?php echo $task["id"]; ?>" class="btn btn-warning">Edit</a>
                                                <a
                                                    href="tasks.php?delete=<?php echo $task["id"]; ?>"
                                                    class="btn btn-danger"
                                                    onclick="return confirm('Yakin ingin menghapus task ini?');"
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
