
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `database`
--

-- --------------------------------------------------------

--
-- Structure de la table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `cible_type` varchar(50) DEFAULT NULL,
  `cible_id` int UNSIGNED DEFAULT NULL,
  `detail` text,
  `ip` varchar(45) DEFAULT NULL,
  `date_action` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- --------------------------------------------------------

--
-- Structure de la table `files`
--

CREATE TABLE `files` (
  `id` int UNSIGNED NOT NULL,
  `nom_original` varchar(255) NOT NULL,
  `nom_courant` varchar(255) NOT NULL,
  `nom_stockage` varchar(255) NOT NULL,
  `extension` varchar(20) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `taille` bigint UNSIGNED NOT NULL DEFAULT '0',
  `chemin_stockage` varchar(500) NOT NULL,
  `folder_id` int UNSIGNED NOT NULL,
  `uploaded_by` int UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `files`
--

-- --------------------------------------------------------

--
-- Structure de la table `file_analysis`
--

CREATE TABLE `file_analysis` (
  `id` int UNSIGNED NOT NULL,
  `file_id` int UNSIGNED NOT NULL,
  `texte_extrait` longtext,
  `mots_cles_detectes` text,
  `cb_detectee` tinyint(1) NOT NULL DEFAULT '0',
  `nombre_cb` int UNSIGNED NOT NULL DEFAULT '0',
  `score_ia` decimal(5,2) DEFAULT NULL,
  `verdict_ia` tinyint(1) DEFAULT NULL,
  `raisons_ia` text,
  `resume_ai` varchar(100) DEFAULT NULL,
  `niveau_sensibilite` enum('non_analyse','en_cours','non_sensible','sensible','sensible_eleve','erreur') NOT NULL DEFAULT 'non_analyse',
  `raisons` text,
  `metadata_analysee` tinyint(1) NOT NULL DEFAULT '0',
  `contenu_analyse` tinyint(1) NOT NULL DEFAULT '0',
  `analysed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- --------------------------------------------------------

--
-- Structure de la table `file_metadata`
--

CREATE TABLE `file_metadata` (
  `id` int UNSIGNED NOT NULL,
  `file_id` int UNSIGNED NOT NULL,
  `auteur` varchar(255) DEFAULT NULL,
  `titre` varchar(500) DEFAULT NULL,
  `sujet` varchar(500) DEFAULT NULL,
  `societe` varchar(255) DEFAULT NULL,
  `date_creation_doc` datetime DEFAULT NULL,
  `date_modification_doc` datetime DEFAULT NULL,
  `logiciel_createur` varchar(255) DEFAULT NULL,
  `nb_pages` int UNSIGNED DEFAULT NULL,
  `langue` varchar(50) DEFAULT NULL,
  `mots_cles` text,
  `json_complet` longtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Structure de la table `folders`
--

CREATE TABLE `folders` (
  `id` int UNSIGNED NOT NULL,
  `nom` varchar(255) NOT NULL,
  `parent_id` int UNSIGNED DEFAULT NULL,
  `chemin` varchar(1000) NOT NULL,
  `created_by` int UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Structure de la table `settings`
--

CREATE TABLE `settings` (
  `cle` varchar(100) NOT NULL,
  `valeur` text NOT NULL,
  `updated_by` int UNSIGNED DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `settings`
--

INSERT INTO `settings` (`cle`, `valeur`, `updated_by`) VALUES
('dlp_enabled', '1', 1);

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `nom` varchar(100) NOT NULL,
  `email` varchar(191) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `nom`, `email`, `password`, `role`, `actif`, `created_at`) VALUES
(1, 'Administrateur', 'admin@ged.local', '$2y$12$Upze6AA8l3gxtuzYJowucefQ2Quuqpr2XkN3dW7VZPS4GhJIYsZ7y', 'admin', 1, '2026-01-01 09:22:35'),
(2, 'Co-Administrateur', 'coadmin@ged.local', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, '2026-01-01 09:22:35'),
(4, 'Robert GREEN', 'robert@ged.local', '$2y$12$5I/KqDb4sFy75UzZJys4D.12mrgWIYJ2Nm.WAM6ELjfV2UjOJnnYW', 'user', 1, '2026-01-01 13:50:48'),
(5, 'Julien MOREAU', 'julien@ged.local', '$2y$12$Io0.gg2plzyngvNuUvHMS.Vr9dHQ7id2amisI1uSQt3R1RBO6pM1i', 'user', 1, '2026-01-01 13:51:21'),
(6, 'Valentina SMITH', 'valentina@ged.local', '$2y$12$1UIgoAqz52/vU2a.KeTKgue7xeEa/SozphLSUJjfe8z3Tir6bwG82', 'user', 1, '2026-01-01 13:51:57'),
(7, 'François HOLLANDE', 'francois@ged.local', '$2y$12$MTUM2WTcSWROd9r0iqn1rOR/qDAnBNt/IRmIyAKppjDUsD7wYJBdm', 'user', 1, '2026-01-01 13:52:44'),
(8, 'Friedrich MERZ', 'friedrich@ged.local', '$2y$12$h33ilGEA0EqwX/MtpZB3lOAFeKnrEGQJuUHDl4lo8de2GUiv5LBsi', 'user', 1, '2026-01-01 13:53:21'),
(9, 'Lucas BERNARD', 'lucas@ged.local', '$2y$12$5I/KqDb4sFy75UzZJys4D.12mrgWIYJ2Nm.WAM6ELjfV2UjOJnnYW', 'user', 1, '2026-01-01 14:50:48'),
(10, 'Sophie MARGEAUX', 'sophie@ged.local', '$2y$12$Io0.gg2plzyngvNuUvHMS.Vr9dHQ7id2amisI1uSQt3R1RBO6pM1i', 'user', 1, '2026-01-01 14:51:21'),
(11, 'Thomas PETIT', 'thomas@ged.local', '$2y$12$1UIgoAqz52/vU2a.KeTKgue7xeEa/SozphLSUJjfe8z3Tir6bwG82', 'user', 1, '2026-01-01 14:51:57'),
(12, 'Jean DUPONT', 'jean@ged.local', '$2y$12$MTUM2WTcSWROd9r0iqn1rOR/qDAnBNt/IRmIyAKppjDUsD7wYJBdm', 'user', 1, '2026-01-01 14:52:44'),
(13, 'Marie MARTIN', 'marie@ged.local', '$2y$12$h33ilGEA0EqwX/MtpZB3lOAFeKnrEGQJuUHDl4lo8de2GUiv5LBsi', 'user', 1, '2026-01-01 14:53:21');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `files`
--
ALTER TABLE `files`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nom_stockage` (`nom_stockage`),
  ADD KEY `folder_id` (`folder_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Index pour la table `file_analysis`
--
ALTER TABLE `file_analysis`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `file_id` (`file_id`);

--
-- Index pour la table `file_metadata`
--
ALTER TABLE `file_metadata`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `file_id` (`file_id`);

--
-- Index pour la table `folders`
--
ALTER TABLE `folders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Index pour la table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`cle`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=312;

--
-- AUTO_INCREMENT pour la table `files`
--
ALTER TABLE `files`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT pour la table `file_analysis`
--
ALTER TABLE `file_analysis`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=97;

--
-- AUTO_INCREMENT pour la table `file_metadata`
--
ALTER TABLE `file_metadata`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT pour la table `folders`
--
ALTER TABLE `folders`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `files`
--
ALTER TABLE `files`
  ADD CONSTRAINT `files_ibfk_1` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `files_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `file_analysis`
--
ALTER TABLE `file_analysis`
  ADD CONSTRAINT `file_analysis_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `file_metadata`
--
ALTER TABLE `file_metadata`
  ADD CONSTRAINT `file_metadata_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `folders`
--
ALTER TABLE `folders`
  ADD CONSTRAINT `folders_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `folders_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `settings`
--
ALTER TABLE `settings`
  ADD CONSTRAINT `settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
