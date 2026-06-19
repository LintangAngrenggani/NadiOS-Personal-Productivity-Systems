<?php
// Mengecek session. Jika session belum aktif, maka session akan dimulai untuk membaca state OAuth.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Koneksi Database
|--------------------------------------------------------------------------
*/
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

/*
|--------------------------------------------------------------------------
| Google Config
|--------------------------------------------------------------------------
*/
// Menentukan lokasi file konfigurasi Google OAuth.
$pathGoogleConfig = __DIR__ . "/google_config.php";

// Mengecek apakah file google_config.php tersedia sebelum digunakan.
if (!file_exists($pathGoogleConfig)) {
    die("File google_config.php tidak ditemukan di: " . $pathGoogleConfig);
}

// Memanggil konfigurasi Google OAuth berisi Client ID, Client Secret, dan Redirect URI.
require_once $pathGoogleConfig;

/** @var string $googleClientId */
/** @var string $googleClientSecret */
/** @var string $googleRedirectUri */

// Membersihkan nilai konfigurasi agar tidak ada spasi kosong yang mengganggu proses OAuth.
$googleClientId = isset($googleClientId) ? trim($googleClientId) : "";
$googleClientSecret = isset($googleClientSecret) ? trim($googleClientSecret) : "";
$googleRedirectUri = isset($googleRedirectUri) ? trim($googleRedirectUri) : "";

// Validasi agar Client ID Google sudah diisi sebelum proses callback dilanjutkan.
if ($googleClientId === "" || $googleClientId === "ISI_GOOGLE_CLIENT_ID_KAMU") {
    die("Google Client ID belum diisi di google_config.php");
}

// Validasi agar Client Secret Google sudah diisi sebelum proses callback dilanjutkan.
if ($googleClientSecret === "" || $googleClientSecret === "ISI_GOOGLE_CLIENT_SECRET_KAMU") {
    die("Google Client Secret belum diisi di google_config.php");
}

// Validasi agar Redirect URI tersedia dan sesuai dengan konfigurasi Google Cloud.
if ($googleRedirectUri === "") {
    die("Google Redirect URI belum diisi di google_config.php");
}

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/
// Fungsi untuk mengamankan output HTML agar data tidak menyebabkan XSS.
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ""), ENT_QUOTES, "UTF-8");
}

// Fungsi untuk mengirim request POST form-urlencoded menggunakan cURL. Dipakai untuk menukar authorization code menjadi access token.
function postCurlForm($url, $fields): array
{
    // Mengecek apakah ekstensi cURL PHP aktif.
    if (!function_exists("curl_init")) {
        return array(
            "ok" => false,
            "error" => "cURL belum aktif. Aktifkan extension=curl di php.ini, lalu restart Apache."
        );
    }

    // Membuat instance cURL untuk endpoint yang dituju.
    $ch = curl_init($url);

    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_HTTPHEADER => array(
            "Content-Type: application/x-www-form-urlencoded"
        ),
        CURLOPT_TIMEOUT => 60
    ));

    // Menjalankan request cURL dan menyimpan respons dari server.
    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);

        return array(
            "ok" => false,
            "error" => $error
        );
    }

    // Mengambil HTTP status code untuk menentukan apakah request berhasil.
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return array(
        "ok" => $httpCode >= 200 && $httpCode < 300,
        "http_code" => $httpCode,
        "body" => $response
    );
}

// Fungsi untuk mengambil data JSON dari endpoint Google menggunakan access token.
function getCurlJson($url, $accessToken): array
{
    if (!function_exists("curl_init")) {
        return array(
            "ok" => false,
            "error" => "cURL belum aktif. Aktifkan extension=curl di php.ini, lalu restart Apache."
        );
    }

    $ch = curl_init($url);

    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer " . $accessToken
        ),
        CURLOPT_TIMEOUT => 60
    ));

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);

        return array(
            "ok" => false,
            "error" => $error
        );
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return array(
        "ok" => $httpCode >= 200 && $httpCode < 300,
        "http_code" => $httpCode,
        "body" => $response
    );
}

// Fungsi untuk mengecek apakah kolom tertentu tersedia pada tabel database.
function columnExists($conn, $tableName, $columnName): bool
{
    $safeTable = mysqli_real_escape_string($conn, $tableName);
    $safeColumn = mysqli_real_escape_string($conn, $columnName);

    $result = mysqli_query($conn, "SHOW COLUMNS FROM `$safeTable` LIKE '$safeColumn'");

    return $result && mysqli_num_rows($result) > 0;
}

// Fungsi untuk menambahkan kolom database jika kolom tersebut belum tersedia.
function ensureColumn($conn, $tableName, $columnName, $definition): void
{
    if (!columnExists($conn, $tableName, $columnName)) {
        mysqli_query($conn, "ALTER TABLE `$tableName` ADD COLUMN `$columnName` $definition");
    }
}

/*
|--------------------------------------------------------------------------
| Validasi Response Google
|--------------------------------------------------------------------------
*/
// Mengecek apakah Google mengirimkan error saat proses login dibatalkan atau gagal.
if (isset($_GET["error"])) {
    die("Google Login dibatalkan atau gagal: " . e($_GET["error"]));
}

// Validasi state OAuth untuk mencegah CSRF dan memastikan callback berasal dari proses login yang sah.
if (!isset($_GET["state"], $_SESSION["google_oauth_state"]) || $_GET["state"] !== $_SESSION["google_oauth_state"]) {
    die("State OAuth tidak valid. Coba login ulang dari halaman login.");
}

// Menghapus state OAuth dari session setelah divalidasi agar tidak dipakai ulang.
unset($_SESSION["google_oauth_state"]);

// Mengambil authorization code dari callback Google.
$code = $_GET["code"] ?? "";

if ($code === "") {
    die("Kode authorization dari Google tidak ditemukan.");
}

/*
|--------------------------------------------------------------------------
| Tukar Authorization Code Menjadi Access Token
|--------------------------------------------------------------------------
*/
// Menukar authorization code menjadi access token melalui endpoint token Google.
$tokenResponse = postCurlForm("https://oauth2.googleapis.com/token", array(
    "code" => $code,
    "client_id" => $googleClientId,
    "client_secret" => $googleClientSecret,
    "redirect_uri" => $googleRedirectUri,
    "grant_type" => "authorization_code"
));

if (!$tokenResponse["ok"]) {
    die("Gagal mengambil token Google: " . e($tokenResponse["body"] ?? $tokenResponse["error"] ?? "Unknown error"));
}

// Mengubah response token dari JSON menjadi array PHP.
$tokenData = json_decode($tokenResponse["body"], true);

if (!isset($tokenData["access_token"])) {
    die("Access token Google tidak ditemukan: " . e($tokenResponse["body"]));
}

// Menyimpan access token Google untuk mengambil data profil user.
$accessToken = $tokenData["access_token"];

/*
|--------------------------------------------------------------------------
| Ambil Profil User Google
|--------------------------------------------------------------------------
*/
// Mengambil profil user dari Google menggunakan access token.
$userInfoResponse = getCurlJson("https://openidconnect.googleapis.com/v1/userinfo", $accessToken);

if (!$userInfoResponse["ok"]) {
    die("Gagal mengambil data user Google: " . e($userInfoResponse["body"] ?? $userInfoResponse["error"] ?? "Unknown error"));
}

// Mengubah response profil Google dari JSON menjadi array PHP.
$userInfo = json_decode($userInfoResponse["body"], true);

// Mengambil data penting dari profil Google: id, email, nama, dan avatar.
$googleId = $userInfo["sub"] ?? "";
$email = $userInfo["email"] ?? "";
$fullName = $userInfo["name"] ?? "";
$avatar = $userInfo["picture"] ?? "";

if ($googleId === "" || $email === "") {
    die("Data Google tidak lengkap: " . e($userInfoResponse["body"]));
}

/*
|--------------------------------------------------------------------------
| Pastikan Kolom Google Ada di Tabel users
|--------------------------------------------------------------------------
*/
// Memastikan tabel users memiliki kolom pendukung login Google.
ensureColumn($conn, "users", "google_id", "VARCHAR(100) NULL");
ensureColumn($conn, "users", "auth_provider", "VARCHAR(30) NOT NULL DEFAULT 'local'");
ensureColumn($conn, "users", "avatar", "VARCHAR(255) NULL");

/*
|--------------------------------------------------------------------------
| Cek User Berdasarkan Email
|--------------------------------------------------------------------------
*/
// Mengecek apakah email Google sudah terdaftar di tabel users.
$stmt = mysqli_prepare($conn, "SELECT id, full_name, email, role, status FROM users WHERE email = ? LIMIT 1");

if (!$stmt) {
    die("Query cek user gagal: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

// Jika email sudah ada, user lama akan dipakai dan data Google-nya diperbarui.
if (mysqli_stmt_num_rows($stmt) === 1) {
    $userId = 0;
    $dbFullName = "";
    $dbEmail = "";
    $role = "user";
    $status = "active";

    mysqli_stmt_bind_result($stmt, $userId, $dbFullName, $dbEmail, $role, $status);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    // Mengecek apakah akun user masih aktif.
    if ($status !== "active") {
        die("Akun tidak aktif.");
    }

    // Memperbarui data Google ID, provider, dan avatar pada user yang sudah ada.
    $updateStmt = mysqli_prepare(
        $conn,
        "UPDATE users SET google_id = ?, auth_provider = 'google', avatar = ? WHERE id = ?"
    );

    if ($updateStmt) {
        mysqli_stmt_bind_param($updateStmt, "ssi", $googleId, $avatar, $userId);
        mysqli_stmt_execute($updateStmt);
        mysqli_stmt_close($updateStmt);
    }

    // Menyimpan data user ke session setelah login Google berhasil.
    $_SESSION["user_id"] = $userId;
    $_SESSION["full_name"] = $dbFullName !== "" ? $dbFullName : $fullName;
    $_SESSION["email"] = $dbEmail;
    $_SESSION["role"] = $role;

    header("Location: dashboard.php");
    exit;
}

mysqli_stmt_close($stmt);

/*
|--------------------------------------------------------------------------
| Jika Email Belum Ada, Buat User Baru Otomatis
|--------------------------------------------------------------------------
*/
// Membuat password acak untuk user Google baru karena login dilakukan melalui provider Google.
$randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
$role = "user";
$status = "active";
$authProvider = "google";

// Menambahkan user baru ke database jika email Google belum pernah terdaftar.
$insertStmt = mysqli_prepare(
    $conn,
    "INSERT INTO users (full_name, email, password, role, status, google_id, auth_provider, avatar)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);

if (!$insertStmt) {
    die("Query insert user Google gagal: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $insertStmt,
    "ssssssss",
    $fullName,
    $email,
    $randomPassword,
    $role,
    $status,
    $googleId,
    $authProvider,
    $avatar
);

if (!mysqli_stmt_execute($insertStmt)) {
    die("Gagal membuat user Google: " . mysqli_stmt_error($insertStmt));
}

// Mengambil id user baru yang berhasil dibuat.
$newUserId = mysqli_insert_id($conn);
mysqli_stmt_close($insertStmt);

// Menyimpan data user baru ke session setelah register otomatis dari Google berhasil.
$_SESSION["user_id"] = $newUserId;
$_SESSION["full_name"] = $fullName;
$_SESSION["email"] = $email;
$_SESSION["role"] = $role;

// Mengarahkan user ke dashboard setelah login Google selesai.
header("Location: dashboard.php");
exit;
