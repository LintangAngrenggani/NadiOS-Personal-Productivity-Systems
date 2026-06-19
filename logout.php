<?php
// Mengecek session. Jika session belum aktif, maka session akan dimulai agar bisa dihapus dengan benar.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Mengosongkan seluruh data session user yang sedang login.
$_SESSION = array();

// Mengecek apakah session menggunakan cookie. Jika iya, cookie session juga akan dihapus dari browser.
if (ini_get("session.use_cookies")) {
    // Mengambil konfigurasi cookie session agar proses penghapusan cookie memakai path, domain, secure, dan httponly yang sama.
    $params = session_get_cookie_params();

    // Menghapus cookie session dengan mengatur waktu kedaluwarsa ke masa lalu.
    setcookie(
        session_name(),
        "",
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Menghancurkan session aktif agar user benar-benar keluar dari aplikasi.
session_destroy();

// Mengarahkan user kembali ke halaman index setelah logout berhasil.
header("Location: index.php?message=logout");
// Menghentikan eksekusi script setelah redirect dijalankan.
exit;