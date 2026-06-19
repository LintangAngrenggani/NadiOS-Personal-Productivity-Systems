<?php
// Mengecek session. Jika session belum aktif, maka session akan dimulai untuk menyimpan state OAuth.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Memanggil konfigurasi Google OAuth berisi Client ID, Client Secret, dan Redirect URI.
require_once __DIR__ . "/google_config.php";

// Validasi agar Client ID Google sudah diisi sebelum redirect ke halaman login Google.
if (!isset($googleClientId) || trim($googleClientId) === "" || $googleClientId === "ISI_GOOGLE_CLIENT_ID_KAMU") {
    die("Google Client ID belum diisi di google_config.php");
}

// Validasi agar Client Secret Google sudah diisi.
if (!isset($googleClientSecret) || trim($googleClientSecret) === "" || $googleClientSecret === "ISI_GOOGLE_CLIENT_SECRET_KAMU") {
    die("Google Client Secret belum diisi di google_config.php");
}

// Validasi agar Redirect URI Google tersedia.
if (!isset($googleRedirectUri) || trim($googleRedirectUri) === "") {
    die("Google Redirect URI belum diisi di google_config.php");
}

// Membuat state acak untuk keamanan OAuth dan mencegah CSRF.
$state = bin2hex(random_bytes(16));
// Menyimpan state ke session agar bisa divalidasi kembali di google_callback.php.
$_SESSION["google_oauth_state"] = $state;

// Menyiapkan parameter yang dikirim ke halaman authorization Google.
$params = array(
    "client_id" => $googleClientId,
    "redirect_uri" => $googleRedirectUri,
    "response_type" => "code",
    "scope" => "openid email profile",
    "access_type" => "online",
    "prompt" => "select_account",
    "state" => $state
);

// Membuat URL login Google lengkap dengan query string OAuth.
$authUrl = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query($params);

// Mengarahkan user ke halaman login Google.
header("Location: " . $authUrl);
// Menghentikan script setelah redirect dijalankan.
exit;