-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 19 Jun 2026 pada 21.09
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lifeos_db`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `goals`
--

CREATE TABLE `goals` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Not Started','In Progress','Completed','Archived') NOT NULL DEFAULT 'Not Started',
  `target_date` date DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `goals`
--

INSERT INTO `goals` (`id`, `user_id`, `title`, `description`, `status`, `target_date`, `image`, `created_at`) VALUES
(2, 1, 'Menyelesaikan Demo Aplikasi NadiOS', 'Menyelesaikan seluruh fitur utama NadiOS agar siap digunakan untuk demo seleksi, mulai dari dashboard, goals, projects, tasks, habits, journal, hingga Nara AI.', 'In Progress', '2026-06-25', 'uploads/goals/goal_1781892419_ad4d0613.png', '2026-06-19 18:06:59');

-- --------------------------------------------------------

--
-- Struktur dari tabel `habits`
--

CREATE TABLE `habits` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `frequency` enum('Daily','Weekly') NOT NULL DEFAULT 'Daily',
  `target_per_week` int(11) NOT NULL DEFAULT 7,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `habits`
--

INSERT INTO `habits` (`id`, `user_id`, `name`, `frequency`, `target_per_week`, `is_active`, `created_at`) VALUES
(1, 1, 'Review Progress NadiOS', 'Daily', 7, 1, '2026-06-19 18:10:10'),
(2, 1, 'Belajar Coding 1 Menit', 'Daily', 2, 1, '2026-06-19 18:10:28');

-- --------------------------------------------------------

--
-- Struktur dari tabel `habit_logs`
--

CREATE TABLE `habit_logs` (
  `id` int(11) NOT NULL,
  `habit_id` int(11) NOT NULL,
  `log_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `journals`
--

CREATE TABLE `journals` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `content` text NOT NULL,
  `mood` enum('Great','Good','Neutral','Bad','Stressed') NOT NULL DEFAULT 'Neutral',
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `journals`
--

INSERT INTO `journals` (`id`, `user_id`, `title`, `content`, `mood`, `image`, `created_at`) VALUES
(1, 1, 'Progress Perbaikan Data User', 'Hari ini saya berhasil memahami bahwa data task dan project harus dipisahkan berdasarkan user_id. Perbaikan ini membuat NadiOS lebih aman karena setiap akun hanya bisa melihat datanya sendiri.', 'Good', '', '2026-06-19 18:11:25'),
(2, 1, 'Persiapan Video Demo', 'Saya mulai menyiapkan alur video demo NadiOS. Bagian yang perlu ditampilkan adalah login, dashboard, goals, projects, tasks, habits, journal, dan Nara AI agar fitur aplikasi terlihat lengkap.', 'Neutral', '', '2026-06-19 18:12:21');

-- --------------------------------------------------------

--
-- Struktur dari tabel `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Planning','Active','Done','Paused') NOT NULL DEFAULT 'Planning',
  `start_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `projects`
--

INSERT INTO `projects` (`id`, `user_id`, `name`, `description`, `status`, `start_date`, `due_date`, `attachment`, `created_at`) VALUES
(2, 1, 'Finalisasi Fitur NadiOS', 'Merapikan seluruh fitur inti NadiOS, termasuk pemisahan data per user, tampilan dashboard, CRUD data, upload file, dan integrasi Nara AI.', 'Active', '2026-06-23', '2026-06-26', 'uploads/projects/project_1781892492_ce3a8ef1.pdf', '2026-06-19 18:08:12'),
(3, 1, 'Pembuatan Video Demo', 'Membuat video demo yang menampilkan alur penggunaan aplikasi dari register, login, dashboard, goals, projects, tasks, habits, journal, sampai Nara AI.', 'Planning', '2026-06-21', '2026-06-30', '', '2026-06-19 18:08:52');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `priority` enum('Low','Medium','High','Urgent') NOT NULL DEFAULT 'Medium',
  `status` enum('Todo','Doing','Done','Canceled') NOT NULL DEFAULT 'Todo',
  `due_date` date DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tasks`
--

INSERT INTO `tasks` (`id`, `user_id`, `project_id`, `title`, `description`, `priority`, `status`, `due_date`, `attachment`, `created_at`) VALUES
(2, 1, 2, 'Cek Login dan Pemisahan Data Akun', 'Menguji dua akun berbeda untuk memastikan task, project, goal, habit, dan journal tidak bercampur antar user.', 'Urgent', 'Doing', '2026-06-25', '', '2026-06-19 18:09:38');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `google_id` varchar(100) DEFAULT NULL,
  `auth_provider` varchar(30) NOT NULL DEFAULT 'local',
  `avatar` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `role`, `status`, `created_at`, `google_id`, `auth_provider`, `avatar`) VALUES
(1, 'Lintang Cantik', 'lintangjenong.lp@gmail.com', '$2y$10$Vpl5G66/6AT71QNI6Beob.Gw2bQDpsFQnoKol/QGlAPKD/c3lX61C', 'user', 'active', '2026-06-18 22:03:33', '113232729961531076962', 'google', 'https://lh3.googleusercontent.com/a/ACg8ocILYezBY4leNZr2VkSF7KWB0Q45nFNZrA2VKGWZ6urOpbWsfrWSQQ=s96-c'),
(2, 'Ikhwanul fatiha', 'fatihatih06@gmail.com', '$2y$10$.wOXJwU//CkJqOCvho7Ui.Kt5EsihjXFmtIQqw5Q00EUgaGHnxrmK', 'user', 'active', '2026-06-19 01:32:56', '107234256064589809863', 'google', 'https://lh3.googleusercontent.com/a/ACg8ocIQZ0mtM88MrseAIBxCMb9eMbZyoxuf06xrriD61qWawYqgtw30=s96-c'),
(3, 'lintang arsip', 'lintangarsip20@gmail.com', '$2y$10$iwR7o6SFfK9/gouMyzbtkOX0qj6iCTWhn2s1IVJuNYE6Hq0Ro9Cfe', 'user', 'active', '2026-06-19 17:54:52', '104427230005919572435', 'google', 'https://lh3.googleusercontent.com/a/ACg8ocIUCgHfTLcYp7qMc7sReUVNniPlddfCuejvCHAqOTvKUwcyjg=s96-c');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `goals`
--
ALTER TABLE `goals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_goals_user_id` (`user_id`);

--
-- Indeks untuk tabel `habits`
--
ALTER TABLE `habits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_habits_user_id` (`user_id`);

--
-- Indeks untuk tabel `habit_logs`
--
ALTER TABLE `habit_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_habit_date` (`habit_id`,`log_date`);

--
-- Indeks untuk tabel `journals`
--
ALTER TABLE `journals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_journals_user_id` (`user_id`);

--
-- Indeks untuk tabel `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_projects_user_id` (`user_id`);

--
-- Indeks untuk tabel `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tasks_project` (`project_id`),
  ADD KEY `idx_tasks_user_id` (`user_id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `goals`
--
ALTER TABLE `goals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `habits`
--
ALTER TABLE `habits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `habit_logs`
--
ALTER TABLE `habit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `journals`
--
ALTER TABLE `journals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `habit_logs`
--
ALTER TABLE `habit_logs`
  ADD CONSTRAINT `fk_habit_logs_habit` FOREIGN KEY (`habit_id`) REFERENCES `habits` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `fk_tasks_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
