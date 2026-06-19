<?php

// Menyimpan nama host database. Pada XAMPP lokal biasanya menggunakan localhost.
$host = "localhost";
// Menyimpan username database MySQL. Default XAMPP biasanya root.
$username = "root";
// Menyimpan password database. Default XAMPP biasanya kosong.
$password = "";
// Menyimpan nama database yang digunakan aplikasi. Nama database dibiarkan sama agar koneksi lama tetap berjalan.
$database = "lifeos_db";

// Membuat koneksi ke database menggunakan konfigurasi host, username, password, dan nama database.
$conn = mysqli_connect($host, $username, $password, $database);

// Mengecek apakah koneksi database gagal. Jika gagal, tampilkan pesan error MySQL.
if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Mengatur karakter koneksi ke utf8mb4 agar mendukung teks Indonesia, simbol, dan karakter khusus.
mysqli_set_charset($conn, "utf8mb4");