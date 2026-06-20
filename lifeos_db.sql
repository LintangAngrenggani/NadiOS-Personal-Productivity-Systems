-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 20 Jun 2026 pada 04.01
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
(1, 2, 'Menyelesaikan Aplikasi NadiOS', 'Menyelesaikan fitur utama NadiOS untuk kebutuhan presentasi dan seleksi', 'In Progress', '2026-06-20', 'uploads/goals/goal_1781919527_77782803.png', '2026-06-20 01:38:47'),
(2, 2, 'Menyiapkan Presentasi Seleksi', 'Menyusun materi presentasi, alur demo, dan penjelasan fitur utama aplikasi.', 'Not Started', '2026-06-21', '', '2026-06-20 01:39:23'),
(3, 2, 'Meningkatkan Konsistensi Produktivitas', 'Membiasakan diri menggunakan goals, tasks, habits, dan journal secara rutin.', 'In Progress', '2026-06-23', '', '2026-06-20 01:39:48');

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
(1, 2, 'Progress Perbaikan Database', 'Hari ini struktur PK dan FK database NadiOS sudah diperiksa dan diperbaiki agar relasi tabel lebih valid.', 'Good', 'uploads/journals/journal_1781920026_38cd6b25.png', '2026-06-20 01:47:06');

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
(1, 2, 'Finalisasi Fitur NadiOS', 'Merapikan fitur dashboard, goals, projects, tasks, habits, journals, dan Nara AI.', 'Active', '2026-06-16', '2026-06-20', 'uploads/projects/project_1781919629_16901c67.pdf', '2026-06-20 01:40:29'),
(2, 2, 'Pembuatan Video Presentasi', 'Membuat video singkat yang menjelaskan alur penggunaan aplikasi NadiOS.', 'Planning', '2026-06-20', '2026-06-27', 'uploads/projects/project_1781919677_f4f0d4f8.pdf', '2026-06-20 01:41:17'),
(3, 2, 'Dokumentasi dan Repository GitHub', 'Merapikan README, struktur folder, database SQL, dan screenshot aplikasi.', 'Done', '2026-06-12', '2026-06-14', '', '2026-06-20 01:42:03');

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
(1, 2, 1, 'Cek Login dan Pemisahan Data User', 'Memastikan setiap akun hanya dapat melihat data miliknya sendiri.', 'Urgent', 'Doing', '2026-06-30', '', '2026-06-20 01:43:18'),
(2, 2, 2, 'Rekam Video Alur Aplikasi', 'Merekam alur register, login, dashboard, CRUD, upload file, dan Nara AI.', 'High', 'Todo', '2026-06-26', '', '2026-06-20 01:44:02'),
(3, 2, 3, 'Upload Repository ke GitHub', 'Mengunggah file project, README, SQL, dan screenshot ke repository publik.', 'Medium', 'Done', '2026-06-11', 'uploads/tasks/task_1781919890_88fb5e63.png', '2026-06-20 01:44:50');

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
(1, 'Lintang Angrenggani', 'lintangjenong.lp@gmail.com', '$2y$10$91XXXyy8nDeh/oYQeC7ja.Ve3jjpYo4vxveOSMni8JQ1dI1thTvVm', 'user', 'active', '2026-06-19 23:25:09', '113232729961531076962', 'google', 'https://lh3.googleusercontent.com/a/ACg8ocILYezBY4leNZr2VkSF7KWB0Q45nFNZrA2VKGWZ6urOpbWsfrWSQQ=s96-c'),
(2, 'syit dude', 'syitdude20@gmail.com', '$2y$10$p8ysoRTw5crmg75vkKPHcO5W9y1TdLzMzcXAE/Debm27sr/t8/hMO', 'user', 'active', '2026-06-20 01:37:37', '114047451632769953623', 'google', 'https://lh3.googleusercontent.com/a/ACg8ocKpwfY8qtssm6cqqr4PW3_nzTpfwCFfNZTrIsKXSjmPuXi_nAk=s96-c');

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
  ADD KEY `idx_tasks_user_id` (`user_id`),
  ADD KEY `idx_tasks_project_id` (`project_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `habits`
--
ALTER TABLE `habits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `habit_logs`
--
ALTER TABLE `habit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `journals`
--
ALTER TABLE `journals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `goals`
--
ALTER TABLE `goals`
  ADD CONSTRAINT `fk_goals_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `habits`
--
ALTER TABLE `habits`
  ADD CONSTRAINT `fk_habits_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `habit_logs`
--
ALTER TABLE `habit_logs`
  ADD CONSTRAINT `fk_habit_logs_habit` FOREIGN KEY (`habit_id`) REFERENCES `habits` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `journals`
--
ALTER TABLE `journals`
  ADD CONSTRAINT `fk_journals_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `fk_projects_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `fk_tasks_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tasks_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
