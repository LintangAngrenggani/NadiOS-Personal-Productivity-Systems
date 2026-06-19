<?php
// Menentukan lokasi file koneksi database MySQL.
$pathKoneksi = __DIR__ . "/koneksi.php";

// Mengecek apakah file koneksi database tersedia sebelum digunakan.
if (!file_exists($pathKoneksi)) {
    die("File koneksi tidak ditemukan di: " . $pathKoneksi);
}

// Memanggil file koneksi agar variabel conn dapat digunakan pada proses register.
require_once $pathKoneksi;

// Mengecek apakah variabel koneksi database sudah tersedia dari file koneksi.php.
if (!isset($conn)) {
    die("Variabel conn tidak tersedia. Cek isi file koneksi.php");
}

// Mengecek session. Jika session belum aktif, maka session akan dimulai.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Menyimpan nama aplikasi yang akan ditampilkan pada halaman register.
$appName = "NadiOS";
// Menyiapkan variabel pesan error serta nilai form agar input user bisa dipertahankan saat validasi gagal.
$error = "";
$fullName = "";
$email = "";

// Fungsi untuk mengamankan output HTML agar data tidak menyebabkan XSS.
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

// Jika user sudah login, user langsung diarahkan ke dashboard agar tidak register ulang.
if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}

// Proses registrasi ketika form dikirim menggunakan method POST.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Mengambil input nama lengkap, email, password, dan konfirmasi password dari form.
    $fullName = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    // Validasi input agar semua field terisi, email valid, password cukup panjang, dan konfirmasi password sesuai.
    if ($fullName === "" || $email === "" || $password === "" || $confirmPassword === "") {
        $error = "Semua field wajib diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter.";
    } elseif ($password !== $confirmPassword) {
        $error = "Konfirmasi password tidak sama.";
    } else {
        // Query untuk mengecek apakah email sudah pernah terdaftar.
        $checkQuery = "SELECT id FROM users WHERE email = ? LIMIT 1";
        $checkStmt = mysqli_prepare($conn, $checkQuery);

        if ($checkStmt) {
            mysqli_stmt_bind_param($checkStmt, "s", $email);
            mysqli_stmt_execute($checkStmt);
            mysqli_stmt_store_result($checkStmt);

            // Jika email sudah ada di database, proses register dihentikan.
            if (mysqli_stmt_num_rows($checkStmt) > 0) {
                $error = "Email sudah terdaftar. Gunakan email lain.";
            } else {
                // Password di-hash sebelum disimpan agar tidak tersimpan dalam bentuk teks asli.
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                // Query untuk menyimpan data user baru ke tabel users.
                $insertQuery = "INSERT INTO users (full_name, email, password) VALUES (?, ?, ?)";
                $insertStmt = mysqli_prepare($conn, $insertQuery);

                if ($insertStmt) {
                    mysqli_stmt_bind_param($insertStmt, "sss", $fullName, $email, $hashedPassword);

                    // Jika register berhasil, user diarahkan ke halaman login dengan pesan sukses.
                    if (mysqli_stmt_execute($insertStmt)) {
                        header("Location: login.php?message=registered");
                        exit;
                    } else {
                        $error = "Registrasi gagal. Silakan coba lagi.";
                    }

                    mysqli_stmt_close($insertStmt);
                } else {
                    $error = "Query register gagal disiapkan.";
                }
            }

            mysqli_stmt_close($checkStmt);
        } else {
            $error = "Query cek email gagal disiapkan.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo e($appName); ?></title>

    <style>
        /* Reset dasar agar ukuran elemen lebih mudah dikontrol. */
        * {
            box-sizing: border-box;
        }

        /* Pengaturan dasar halaman dan font utama. */
        html,
        /* Layout body untuk menempatkan card register di tengah layar. */
        body {
            margin: 0;
            min-height: 100%;
            font-family: Arial, Helvetica, sans-serif;
            background: #f8fafc;
            color: #172033;
        }

        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background:
                radial-gradient(circle at top left, rgba(37, 99, 235, 0.14), transparent 34%),
                radial-gradient(circle at bottom right, rgba(22, 163, 74, 0.10), transparent 30%),
                #f8fafc;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        input,
        button {
            font-family: inherit;
        }

        /* Card utama berisi form register dan link navigasi. */
        .auth-card {
            width: min(480px, 100%);
            background: #ffffff;
            border: 1px solid #d9dee8;
            border-radius: 28px;
            padding: 32px;
            box-shadow: 0 24px 70px rgba(15, 23, 42, 0.10);
        }

        /* Logo kecil NadiOS pada halaman register. */
        .logo {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            background: #111827;
            color: #ffffff;
            display: grid;
            place-items: center;
            font-weight: 900;
            margin-bottom: 18px;
        }

        h1 {
            margin: 0;
            font-size: 34px;
            letter-spacing: -0.05em;
        }

        .desc {
            margin: 10px 0 24px;
            color: #697386;
            line-height: 1.6;
            font-size: 15px;
        }

        /* Style pesan error validasi register. */
        .alert {
            padding: 13px 14px;
            border-radius: 14px;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 16px;
            font-weight: 700;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Layout form register. */
        .form {
            display: grid;
            gap: 16px;
        }

        .form-group {
            display: grid;
            gap: 8px;
        }

        label {
            font-size: 14px;
            font-weight: 900;
        }

        /* Style input form register. */
        .form-control {
            width: 100%;
            border: 1px solid #d9dee8;
            border-radius: 14px;
            padding: 13px 14px;
            outline: none;
            font-size: 15px;
            color: #172033;
            background: #ffffff;
        }

        .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
        }

        /* Style dasar untuk tombol pada halaman register. */
        .btn {
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            padding: 13px 18px;
            font-size: 15px;
            font-weight: 900;
            text-align: center;
        }

        .btn-primary {
            background: #2563eb;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: #eef2f7;
            color: #172033;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        /* Area link menuju halaman login. */
        .footer {
            margin-top: 18px;
            color: #697386;
            font-size: 14px;
            line-height: 1.6;
        }

        .footer a {
            color: #2563eb;
            font-weight: 900;
        }

        /* Area tombol kembali ke halaman index. */
        .actions {
            margin-top: 18px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .actions .btn {
            flex: 1;
        }

        /* Responsive layout untuk layar mobile. */
        @media (max-width: 520px) {
            body {
                padding: 16px;
            }

            .auth-card {
                padding: 26px 20px;
                border-radius: 24px;
            }

            .actions .btn {
                width: 100%;
                flex: auto;
            }
        }
    </style>
</head>
<body>

<!-- Card utama halaman register. -->
<main class="auth-card">
    <!-- Logo/nama aplikasi pada halaman register. -->
    <div class="logo"><?php echo e($appName); ?></div>

    <h1>Register</h1>
    <p class="desc">
        Buat akun terlebih dahulu untuk menggunakan dashboard dan modul NadiOS.
    </p>

    <!-- Menampilkan pesan error jika proses register gagal. -->
    <?php if ($error !== "") { ?>
        <div class="alert alert-error">
            <?php echo e($error); ?>
        </div>
    <?php } ?>

    <!-- Form register untuk membuat akun baru. -->
    <form class="form" action="register.php" method="POST">
        <div class="form-group">
            <label for="full_name">Nama Lengkap</label>
            <input
                type="text"
                id="full_name"
                name="full_name"
                class="form-control"
                value="<?php echo e($fullName); ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input
                type="email"
                id="email"
                name="email"
                class="form-control"
                value="<?php echo e($email); ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                class="form-control"
                minlength="6"
                required
            >
        </div>

        <div class="form-group">
            <label for="confirm_password">Konfirmasi Password</label>
            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                class="form-control"
                minlength="6"
                required
            >
        </div>

        <button type="submit" class="btn btn-primary">
            Register
        </button>
    </form>

    <!-- Link menuju halaman login untuk user yang sudah memiliki akun. -->
    <div class="footer">
        Sudah punya akun? <a href="login.php">Login di sini</a>.
    </div>

    <!-- Tombol kembali ke halaman index. -->
    <div class="actions">
        <a href="index.php" class="btn btn-secondary">Kembali ke Index</a>
    </div>
</main>

</body>
</html>
