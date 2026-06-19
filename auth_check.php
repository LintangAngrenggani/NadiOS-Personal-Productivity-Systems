<?php
// Mengecek session. Jika session belum aktif, maka session akan dimulai untuk membaca status login user.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Mengecek apakah user sudah login. Jika belum, user diarahkan ke halaman login dengan pesan login_required.
if (!isset($_SESSION["user_id"])) {
        // Redirect ke halaman login jika user mencoba mengakses halaman yang membutuhkan login.
    header("Location: login.php?message=login_required");
        // Menghentikan eksekusi script setelah redirect dijalankan.
    exit;
}
