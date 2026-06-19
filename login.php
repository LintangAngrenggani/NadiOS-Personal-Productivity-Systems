<?php
// Menentukan lokasi file koneksi database MySQL.
$pathKoneksi = __DIR__ . "/koneksi.php";

// Mengecek apakah file koneksi database tersedia sebelum digunakan.
if (!file_exists($pathKoneksi)) {
    die("File koneksi tidak ditemukan di: " . $pathKoneksi);
}

// Memanggil file koneksi agar variabel conn dapat digunakan pada proses login.
require_once $pathKoneksi;

// Mengecek apakah variabel koneksi database sudah tersedia dari file koneksi.php.
if (!isset($conn)) {
    die("Variabel conn tidak tersedia. Cek isi file koneksi.php");
}

// Mengecek session. Jika session belum aktif, maka session akan dimulai.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Menyimpan nama aplikasi yang akan ditampilkan pada halaman login.
$appName = "NadiOS";
// Menyiapkan variabel pesan error, pesan sukses, dan email yang akan diisi ulang pada form.
$error = "";
$success = "";
$email = "";

// Fungsi untuk mengamankan output HTML agar data tidak menyebabkan XSS.
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ""), ENT_QUOTES, "UTF-8");
}

// Jika user sudah login, user langsung diarahkan ke dashboard agar tidak login ulang.
if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}

// Menampilkan pesan berdasarkan query string, misalnya setelah register, logout, atau akses tanpa login.
if (isset($_GET["message"])) {
    if ($_GET["message"] === "registered") {
        $success = "Registrasi berhasil. Silakan login.";
    }

    if ($_GET["message"] === "login_required") {
        $error = "Silakan login terlebih dahulu untuk masuk ke dashboard.";
    }

    if ($_GET["message"] === "logout") {
        $success = "Logout berhasil.";
    }
}

// Proses login manual ketika form dikirim menggunakan method POST.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Mengambil email dan password dari form login.
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    // Validasi input agar email dan password tidak kosong serta format email valid.
    if ($email === "" || $password === "") {
        $error = "Email dan password wajib diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";
    } else {
        // Query untuk mencari user berdasarkan email yang dimasukkan.
        $query = "SELECT id, full_name, email, password, role, status FROM users WHERE email = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $query);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);

            // Jika email ditemukan, data user akan diambil untuk verifikasi password dan status akun.
            if (mysqli_stmt_num_rows($stmt) === 1) {
                $userId = 0;
                $fullName = "";
                $userEmail = "";
                $hashedPassword = "";
                $role = "user";
                $status = "inactive";

                mysqli_stmt_bind_result(
                    $stmt,
                    $userId,
                    $fullName,
                    $userEmail,
                    $hashedPassword,
                    $role,
                    $status
                );

                mysqli_stmt_fetch($stmt);

                // Mengecek apakah akun aktif sebelum mengizinkan login.
                if ($status !== "active") {
                    $error = "Akun tidak aktif.";
                // Memverifikasi password input dengan password hash yang tersimpan di database.
                } elseif (password_verify($password, (string) $hashedPassword)) {
                    // Jika login berhasil, data user disimpan ke session untuk dipakai pada halaman lain.
                    $_SESSION["user_id"] = $userId;
                    $_SESSION["full_name"] = $fullName;
                    $_SESSION["email"] = $userEmail;
                    $_SESSION["role"] = $role;

                    header("Location: dashboard.php");
                    exit;
                } else {
                    $error = "Password salah.";
                }
            } else {
                $error = "Email belum terdaftar.";
            }

            mysqli_stmt_close($stmt);
        } else {
            $error = "Query login gagal disiapkan. Pastikan tabel users sudah benar.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo e($appName); ?></title>

    <style>
        /* Reset dasar agar ukuran elemen lebih mudah dikontrol. */
        * {
            box-sizing: border-box;
        }

        /* Pengaturan dasar halaman dan font utama. */
        html,
        /* Layout body untuk menempatkan card login di tengah layar. */
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

        /* Card utama berisi form login, tombol Google, dan link register. */
        .auth-card {
            width: min(460px, 100%);
            background: #ffffff;
            border: 1px solid #d9dee8;
            border-radius: 28px;
            padding: 32px;
            box-shadow: 0 24px 70px rgba(15, 23, 42, 0.10);
        }

        /* Logo kecil NadiOS pada halaman login. */
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

        /* Style pesan error dan sukses. */
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

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        /* Layout form login manual. */
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

        /* Style input email dan password. */
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

        /* Style dasar untuk semua tombol pada halaman login. */
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

        /* Tombol login menggunakan Google OAuth. */
        .btn-google {
            width: 100%;
            margin-top: 14px;
            background: #ffffff;
            color: #172033;
            border: 1px solid #d9dee8;
        }

        .btn-google:hover {
            background: #f8fafc;
        }

        /* Pembatas antara login Google dan login manual. */
        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 18px 0;
            color: #697386;
            font-size: 13px;
            font-weight: 800;
        }

        .divider::before,
        .divider::after {
            content: "";
            height: 1px;
            background: #d9dee8;
            flex: 1;
        }

        /* Area link menuju halaman register. */
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

<!-- Card utama halaman login. -->
<main class="auth-card">
    <!-- Logo/nama aplikasi pada halaman login. -->
    <div class="logo"><?php echo e($appName); ?></div>

    <h1>Login</h1>

    <p class="desc">
        Masuk menggunakan akun yang sudah dibuat untuk mengakses dashboard NadiOS.
    </p>

    <!-- Menampilkan pesan error jika proses login gagal. -->
    <?php if ($error !== "") { ?>
        <div class="alert alert-error">
            <?php echo e($error); ?>
        </div>
    <?php } ?>

    <!-- Menampilkan pesan sukses seperti register berhasil atau logout berhasil. -->
    <?php if ($success !== "") { ?>
        <div class="alert alert-success">
            <?php echo e($success); ?>
        </div>
    <?php } ?>

    <!-- Tombol login menggunakan akun Google. -->
    <a href="google_login.php" class="btn btn-google">
        Login dengan Google
    </a>

    <div class="divider">atau login manual</div>

    <!-- Form login manual menggunakan email dan password. -->
    <form class="form" action="login.php" method="POST">
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
                required
            >
        </div>

        <button type="submit" class="btn btn-primary">
            Login
        </button>
    </form>

    <!-- Link menuju halaman register untuk user baru. -->
    <div class="footer">
        Belum punya akun? <a href="register.php">Register di sini</a>.
    </div>

    <!-- Tombol kembali ke halaman index. -->
    <div class="actions">
        <a href="index.php" class="btn btn-secondary">Kembali ke Index</a>
    </div>
</main>

</body>
</html>
