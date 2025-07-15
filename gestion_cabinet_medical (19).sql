-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : mar. 15 juil. 2025 à 22:05
-- Version du serveur : 9.1.0
-- Version de PHP : 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `gestion_cabinet_medical`
--

-- --------------------------------------------------------

--
-- Structure de la table `connexion`
--

DROP TABLE IF EXISTS `connexion`;
CREATE TABLE IF NOT EXISTS `connexion` (
  `id_connexion` int NOT NULL AUTO_INCREMENT,
  `id_utilisateur` int DEFAULT NULL,
  `login` varchar(50) DEFAULT NULL,
  `mot_de_passe` char(64) DEFAULT NULL,
  `derniere_connexion` timestamp NULL DEFAULT NULL,
  `date_modification` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date du dernier changement de mot de passe',
  `modifie_par` int DEFAULT NULL COMMENT 'ID de l''admin qui a changé le mot de passe',
  PRIMARY KEY (`id_connexion`),
  KEY `id_utilisateur` (`id_utilisateur`)
) ENGINE=MyISAM AUTO_INCREMENT=120 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `connexion`
--

INSERT INTO `connexion` (`id_connexion`, `id_utilisateur`, `login`, `mot_de_passe`, `derniere_connexion`, `date_modification`, `modifie_par`) VALUES
(1, 1, 'mdurand', '240be518fabd2724ddb6f04eeb1da5967448d7e831c08c8fa822809f74c720a9', NULL, NULL, NULL),
(2, 2, 'lpetit', 'c0c487a0e7b7b6ba21cf840fd665f00fe879ca70185e5a52c54081189c370ce3', NULL, NULL, NULL),
(3, 3, 'jmoreau', '3ac1e413422505fe42b9f5b0ca8d5257f563722429c980ef1f7c7d84a33ade3f', NULL, NULL, NULL),
(4, 4, '24124@supnum.mr', '$2y$10$B5OQxZR9fdlF8w1mWICTDO9b1nycm5AOZkT11RAv/FOes66KanVYa', NULL, NULL, NULL),
(5, 5, '24122@supnum.mr', '$2y$10$icsGwE/N8Mmhse7.t4o.q.Irxj2bzxgRwiH0v069wpM9tiBdD/WPi', NULL, NULL, NULL),
(6, 7, 'saadbouh@gmail.com', '$2y$10$mBZM4ygAkFNCW7QQxAf/ZuSgLlSMtoM6hy/FUJ8xUiWi361ATDZfG', NULL, NULL, NULL),
(7, 8, 'brahim@gmail.com', '$2y$10$4soSB2l4uFtzFhxfGdL2GuONkSXetgmPhStapKe6cK44G.bdrohzK', NULL, NULL, NULL),
(8, 9, 'ahmedsidiya@gmail.com', '$2y$10$9enLvEbO3qadtf9gaoFCpenS8w9AVrE1OBMWJvpPjhBipcvDyE9Sy', NULL, NULL, NULL),
(9, 10, 'zaineb@gmail.com', '$2y$10$.G9JkegK5.GTW29ExBU1wu6g6OJ2ZvzAll0uYwxPwi6CKM.yZY0IW', NULL, NULL, NULL),
(10, 11, 'zaibe@gmail.com', '$2y$10$djZLHU8Z0Syd/I.1iAEnjutVZXNll6G7rosZVeFJsO7GvuAjkcvgK', NULL, NULL, NULL),
(11, 31, 'test.doctor@gmail.com', '81e22496bd87e5ffae5f2c933154e65c1f290a6cc921072c4f5d3fd08d9b9a87', NULL, NULL, NULL),
(12, 32, 'test.assistant@gmail.com', '3ce2305001eb77db89331a21af3dc007e9bbbecfb5cd07a62a8cb55b9981e1ba', NULL, NULL, NULL),
(47, 51, 'login_med1', '21202d90c47658ab27eded00a86dbcecd6a0b1340f6207a5c31b635e1bf2edee', NULL, NULL, NULL),
(14, 35, 'Mokhtar@gmail.com', '$2y$10$LJ6V80YHQEFC/kuvlzCWgOQUk02KF5duox6c9d01.DZxXi7jApt/.', NULL, NULL, NULL),
(15, 36, 'ouldibm@gmail.com', 'acbca50231dd6170c60dbb0b3762eba60b2f9bf7dc9f9662ff72040a0c0dfd27', NULL, NULL, NULL),
(16, 37, 'zain99999eb@gmaiiiil.com', '1d253ab8363d6fd073db995b36754f387c11a71cf4461aacde246990b75b9667', NULL, NULL, NULL),
(17, 38, 'gohy@gmail.com', '17e871160f8718ad0144eed6820cdccf096d88ecc2abdcdc5fd9597f4d7e75c2', NULL, NULL, NULL),
(109, 80, '240908@Supnum.mr', '17756315ebd47b7110359fc7b168179bf6f2df3646fcc888bc8aa05c78b38ac1', NULL, NULL, NULL),
(40, 40, 'sadbouh@Email.com', 'c1d4eadf2d104ecefb6b7924336b9cc79091c77a645f25d378f61ed7f14e5c0e', NULL, NULL, NULL),
(41, 41, 'ahmed@Email.com', '261e87847b237c4202104b9fdb9aea7c2b903c75461af947df0f2c4c78b7d1c9', NULL, NULL, NULL),
(49, 53, 'login_med3', 'c74dce2ad4f951973c69f80da3e1135b3425db26d58b1ba2c61b83bcd2b6f6c0', NULL, NULL, NULL),
(43, 40, '24212@gmail.com', '81e22496bd87e5ffae5f2c933154e65c1f290a6cc921072c4f5d3fd08d9b9a87', NULL, NULL, NULL),
(44, 41, '24210@gmail.com', 'c02eb248fdca01d3def18184bdb14a1500ac958246e2c094959bca7e315cf08c', NULL, NULL, NULL),
(48, 52, 'login_med2', '40262302721170ede972148fa01cc7874309df2e74b493cf325f992a2d6e47ee', NULL, NULL, NULL),
(46, 43, '24068@Supnum.mr', '896a5db57ef7e230d1e428fad6840922f4520604e65daaa384717491f172e081', NULL, NULL, NULL),
(50, 54, 'login_med4', '06c217f179f0fc01ada65ed4872835090d237f041143aaf734524cae191136cc', NULL, NULL, NULL),
(51, 55, 'login_med5', '29b63ebe14f9d7d43d48efcdd343a068123438dea4f1dd5a49e9126b8cf96ee5', NULL, NULL, NULL),
(52, 61, 'login_pat1', '112b310b688a0f735466a95e963ea4be4f56ef4e7f2c2292d204ee8faf7870a2', NULL, NULL, NULL),
(53, 62, 'login_pat2', '0d4167348b53bbe0826c35dd2b7c3a5df86717c5113a744ffffe347929ef9585', NULL, NULL, NULL),
(54, 63, 'login_pat3', '8f6944da788b2c7b56e6ffe803ab0b35c6ade83fb0081877e8ab054c63746857', NULL, NULL, NULL),
(55, 64, 'login_pat4', '1a1c523db4c3f8d81834a295452d8482fede62c14630604b37296a3187ddfa93', NULL, NULL, NULL),
(56, 65, 'login_pat5', 'e89c9efdcf5041177c0f1e8a9d4b25aa3518a7cd3644e78f0abd1668aaf9441e', NULL, NULL, NULL),
(57, 66, 'login_pat6', '58705fe62364a1de4874b959e7e2126167722b5484549a6125c340b7b5ab3d60', NULL, NULL, NULL),
(58, 67, 'login_pat7', '1907a83a5f64171069247aebe341ff6528fec8c580d8cc2bd0599c3ea9fdc3c3', NULL, NULL, NULL),
(112, 86, '24131@Supnum.mr', 'ef797c8118f02dfb649607dd5d3f8c7623048c9c063d532cc95c5ed7a898a64f', NULL, NULL, NULL),
(61, 70, 'login_pat10', '6040b63e5fa532f7f41258ab90bfad436834f20d92bbac07821288cd703aabe3', NULL, NULL, NULL),
(62, 71, 'login_as1', 'f79e03eed4710637def569e6fd1104e600f304bd8264d43cb3ec512cb94e52b3', NULL, NULL, NULL),
(63, 72, 'login_as2', '78083a46111ae12ff63be75db939cafb1a4b10e064b5f6b83d627f5b42c9c93d', NULL, NULL, NULL),
(64, 73, 'login_as3', 'a9233accfdb91fdc3302fa7ff559f982ee8de1729853569f1d3f34ae96e86b49', NULL, NULL, NULL),
(65, 74, 'login_as4', 'dd0fe02fdc1a18ffd8e2a2a2bb0790c47a712f12aab8ff639b93c4cb3823f854', NULL, NULL, NULL),
(66, 75, 'login_as5', 'de28c2a39e385966d2d36856632db09906089c8c41f1b1990fab51bf21b7f877', NULL, NULL, NULL),
(67, 51, 'login_med1', '21202d90c47658ab27eded00a86dbcecd6a0b1340f6207a5c31b635e1bf2edee', NULL, NULL, NULL),
(68, 52, 'login_med2', '40262302721170ede972148fa01cc7874309df2e74b493cf325f992a2d6e47ee', NULL, NULL, NULL),
(69, 53, 'login_med3', 'c74dce2ad4f951973c69f80da3e1135b3425db26d58b1ba2c61b83bcd2b6f6c0', NULL, NULL, NULL),
(70, 54, 'login_med4', '06c217f179f0fc01ada65ed4872835090d237f041143aaf734524cae191136cc', NULL, NULL, NULL),
(71, 55, 'login_med5', '29b63ebe14f9d7d43d48efcdd343a068123438dea4f1dd5a49e9126b8cf96ee5', NULL, NULL, NULL),
(72, 61, 'login_pat1', '112b310b688a0f735466a95e963ea4be4f56ef4e7f2c2292d204ee8faf7870a2', NULL, NULL, NULL),
(73, 62, 'login_pat2', '0d4167348b53bbe0826c35dd2b7c3a5df86717c5113a744ffffe347929ef9585', NULL, NULL, NULL),
(74, 63, 'login_pat3', '8f6944da788b2c7b56e6ffe803ab0b35c6ade83fb0081877e8ab054c63746857', NULL, NULL, NULL),
(75, 64, 'login_pat4', '1a1c523db4c3f8d81834a295452d8482fede62c14630604b37296a3187ddfa93', NULL, NULL, NULL),
(76, 65, 'login_pat5', 'e89c9efdcf5041177c0f1e8a9d4b25aa3518a7cd3644e78f0abd1668aaf9441e', NULL, NULL, NULL),
(77, 66, 'login_pat6', '58705fe62364a1de4874b959e7e2126167722b5484549a6125c340b7b5ab3d60', NULL, NULL, NULL),
(78, 67, 'login_pat7', '1907a83a5f64171069247aebe341ff6528fec8c580d8cc2bd0599c3ea9fdc3c3', NULL, NULL, NULL),
(111, 85, '24028@supnum.mr', '$2y$10$k8L2DisycevWRrvfyBfZReWFMuU.LV/VG73YUwVrUu6DIkJ0sfUkW', NULL, NULL, NULL),
(81, 70, 'login_pat10', '6040b63e5fa532f7f41258ab90bfad436834f20d92bbac07821288cd703aabe3', NULL, NULL, NULL),
(82, 71, 'login_as1', 'f79e03eed4710637def569e6fd1104e600f304bd8264d43cb3ec512cb94e52b3', NULL, NULL, NULL),
(83, 72, 'login_as2', '78083a46111ae12ff63be75db939cafb1a4b10e064b5f6b83d627f5b42c9c93d', NULL, NULL, NULL),
(84, 73, 'login_as3', 'a9233accfdb91fdc3302fa7ff559f982ee8de1729853569f1d3f34ae96e86b49', NULL, NULL, NULL),
(85, 74, 'login_as4', 'dd0fe02fdc1a18ffd8e2a2a2bb0790c47a712f12aab8ff639b93c4cb3823f854', NULL, NULL, NULL),
(86, 75, 'login_as5', 'de28c2a39e385966d2d36856632db09906089c8c41f1b1990fab51bf21b7f877', NULL, NULL, NULL),
(87, 51, 'login_med1', '21202d90c47658ab27eded00a86dbcecd6a0b1340f6207a5c31b635e1bf2edee', NULL, NULL, NULL),
(88, 52, 'login_med2', '40262302721170ede972148fa01cc7874309df2e74b493cf325f992a2d6e47ee', NULL, NULL, NULL),
(89, 53, 'login_med3', 'c74dce2ad4f951973c69f80da3e1135b3425db26d58b1ba2c61b83bcd2b6f6c0', NULL, NULL, NULL),
(90, 54, 'login_med4', '06c217f179f0fc01ada65ed4872835090d237f041143aaf734524cae191136cc', NULL, NULL, NULL),
(91, 55, 'login_med5', '29b63ebe14f9d7d43d48efcdd343a068123438dea4f1dd5a49e9126b8cf96ee5', NULL, NULL, NULL),
(92, 61, 'login_pat1', '112b310b688a0f735466a95e963ea4be4f56ef4e7f2c2292d204ee8faf7870a2', NULL, NULL, NULL),
(93, 62, 'login_pat2', '0d4167348b53bbe0826c35dd2b7c3a5df86717c5113a744ffffe347929ef9585', NULL, NULL, NULL),
(94, 63, 'login_pat3', '8f6944da788b2c7b56e6ffe803ab0b35c6ade83fb0081877e8ab054c63746857', NULL, NULL, NULL),
(95, 64, 'login_pat4', '1a1c523db4c3f8d81834a295452d8482fede62c14630604b37296a3187ddfa93', NULL, NULL, NULL),
(96, 65, 'login_pat5', 'e89c9efdcf5041177c0f1e8a9d4b25aa3518a7cd3644e78f0abd1668aaf9441e', NULL, NULL, NULL),
(97, 66, 'login_pat6', '58705fe62364a1de4874b959e7e2126167722b5484549a6125c340b7b5ab3d60', NULL, NULL, NULL),
(98, 67, 'login_pat7', '1907a83a5f64171069247aebe341ff6528fec8c580d8cc2bd0599c3ea9fdc3c3', NULL, NULL, NULL),
(110, 84, '25024@Supnum.mr', 'c775e7b757ede630cd0aa1113bd102661ab38829ca52a6422ab782862f268646', NULL, NULL, NULL),
(101, 70, 'login_pat10', '6040b63e5fa532f7f41258ab90bfad436834f20d92bbac07821288cd703aabe3', NULL, NULL, NULL),
(102, 71, 'login_as1', 'f79e03eed4710637def569e6fd1104e600f304bd8264d43cb3ec512cb94e52b3', NULL, NULL, NULL),
(103, 72, 'login_as2', '78083a46111ae12ff63be75db939cafb1a4b10e064b5f6b83d627f5b42c9c93d', NULL, NULL, NULL),
(104, 73, 'login_as3', 'a9233accfdb91fdc3302fa7ff559f982ee8de1729853569f1d3f34ae96e86b49', NULL, NULL, NULL),
(105, 74, 'login_as4', 'dd0fe02fdc1a18ffd8e2a2a2bb0790c47a712f12aab8ff639b93c4cb3823f854', NULL, NULL, NULL),
(106, 75, 'login_as5', 'de28c2a39e385966d2d36856632db09906089c8c41f1b1990fab51bf21b7f877', NULL, NULL, NULL),
(107, 77, '24320@supnum.mr', '$2y$10$J1FGF5eiRbbYegv3Kn0S.uH4kFR/DIIudJMyesFnp97fn.lzuMJ..', NULL, NULL, NULL),
(108, 79, '23070@supnum.mr', '$2y$10$fgIRFW9dF7oU3aZO8eMzeO1SJ8/HDfW/wqlN0bz2WsmMrfAmYsCRu', NULL, NULL, NULL),
(113, 87, '24312@supnum.mr', 'c775e7b757ede630cd0aa1113bd102661ab38829ca52a6422ab782862f268646', NULL, NULL, NULL),
(114, 88, '27312@supnum.mr', '15e2b0d3c33891ebb0f1ef609ec419420c20e320ce94c65fbc8c3312448eb225', NULL, NULL, NULL),
(115, 89, '24319@supnum.mr', 'c775e7b757ede630cd0aa1113bd102661ab38829ca52a6422ab782862f268646', NULL, NULL, NULL),
(116, 90, '20312@supnum.mr', 'c775e7b757ede630cd0aa1113bd102661ab38829ca52a6422ab782862f268646', NULL, NULL, NULL),
(117, 91, '209312@supnum.mr', 'c775e7b757ede630cd0aa1113bd102661ab38829ca52a6422ab782862f268646', NULL, NULL, NULL),
(118, 92, 'patient@supnum.mr', 'c775e7b757ede630cd0aa1113bd102661ab38829ca52a6422ab782862f268646', NULL, NULL, NULL),
(119, 93, '24000@Supnum.mr', '17756315ebd47b7110359fc7b168179bf6f2df3646fcc888bc8aa05c78b38ac1', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `examen`
--

DROP TABLE IF EXISTS `examen`;
CREATE TABLE IF NOT EXISTS `examen` (
  `id_examen` int NOT NULL AUTO_INCREMENT,
  `id_traitement` int DEFAULT NULL,
  `type_examen` varchar(100) DEFAULT NULL,
  `résultat` text,
  `date_examen` date DEFAULT NULL,
  PRIMARY KEY (`id_examen`),
  KEY `id_traitement` (`id_traitement`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `examen`
--

INSERT INTO `examen` (`id_examen`, `id_traitement`, `type_examen`, `résultat`, `date_examen`) VALUES
(1, 1, 'ECG', 'Normal', '2024-01-03'),
(2, 2, 'Scanner', 'Anomalie détectée', '2024-02-04'),
(3, 3, 'IRM', 'Pas d\'anomalie', '2024-03-06'),
(4, 11, 'GCC', 'malade', '2025-06-19'),
(5, 12, 'Test', 'Résultat OK', '2025-07-06'),
(6, 6, 'CCG', 'uploadsExamen/68763aa71570a_Capture d\'écran 2025-07-13 155519.png', '2025-07-15'),
(7, 6, 'CCG', 'uploadsExamen/68764098423aa_Capture d\'écran 2025-07-13 155519.png', '2025-07-15'),
(8, 6, 'CCG', 'uploadsExamen/687641f890913_Capture d\'écran 2025-07-13 155519.png', '2025-07-15'),
(9, 6, 'CCG', 'uploadsExamen/6876421924350_Capture d\'écran 2025-07-13 155519.png', '2025-07-15'),
(10, 8, 'CCG', 'uploadsExamen/6876499ba7292_Capture d\'écran 2025-07-14 064807.png', '2025-07-15'),
(11, 86, 'hhhhh', 'uploadsExamen/68764be570cdf_profile_43_1751954393.jpg', '2025-07-15');

-- --------------------------------------------------------

--
-- Structure de la table `hospitalisation`
--

DROP TABLE IF EXISTS `hospitalisation`;
CREATE TABLE IF NOT EXISTS `hospitalisation` (
  `id_hospitalisation` int NOT NULL AUTO_INCREMENT,
  `id_traitement` int DEFAULT NULL,
  `date_entree` date DEFAULT NULL,
  `date_sortie` date DEFAULT NULL,
  `service` varchar(100) DEFAULT NULL,
  `id_patient` int DEFAULT NULL,
  PRIMARY KEY (`id_hospitalisation`),
  KEY `id_traitement` (`id_traitement`),
  KEY `fk_patient` (`id_patient`)
) ENGINE=MyISAM AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `hospitalisation`
--

INSERT INTO `hospitalisation` (`id_hospitalisation`, `id_traitement`, `date_entree`, `date_sortie`, `service`, `id_patient`) VALUES
(25, NULL, '2025-05-12', '2025-05-14', 'abdi', 3),
(24, NULL, '2025-05-12', '2025-05-14', 'abdi', 3),
(3, 3, '2024-03-10', '2024-03-18', 'Neurologie', 3),
(10, 20, '2025-07-13', NULL, 'Cardiologie', 61),
(11, 21, '2025-07-14', NULL, 'Neurologie', 62),
(12, 22, '2025-07-15', NULL, 'Dermatologie', 63),
(13, 23, '2025-07-16', '2025-07-14', 'Pédiatrie', 64),
(14, 24, '2025-07-17', NULL, 'Urgences', 65),
(15, 25, '2025-07-18', '2025-07-14', 'Cardiologie', 66),
(16, 26, '2025-07-14', '2025-07-02', 'Neurologie', 67),
(26, 88, '2025-05-12', '2025-05-14', 'abdi', 3),
(19, 29, '2025-07-07', '2025-07-08', 'Urgences', 70),
(20, 76, '2025-07-09', '2025-07-08', 'Cardiologies', 78),
(23, 85, '2025-07-14', NULL, 'Cardiologie', 93),
(27, NULL, '2025-05-12', '2025-05-14', 'abdi', 3),
(28, NULL, '2025-05-12', '2025-05-14', 'abdi', 3),
(32, NULL, '2025-07-17', '2025-07-03', 'trop', 36);

-- --------------------------------------------------------

--
-- Structure de la table `medecin`
--

DROP TABLE IF EXISTS `medecin`;
CREATE TABLE IF NOT EXISTS `medecin` (
  `id_medecin` int NOT NULL,
  `nom` varchar(50) DEFAULT NULL,
  `prenom` varchar(50) DEFAULT NULL,
  `spécialité` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id_medecin`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `medecin`
--

INSERT INTO `medecin` (`id_medecin`, `nom`, `prenom`, `spécialité`, `email`, `telephone`) VALUES
(1, 'Lemoine', 'Claire', 'Cardiologie', 'claire.lemoine@mail.com', '0611111111'),
(2, 'Gomez', 'Eric', 'Neurologie', 'eric.gomez@mail.com', '0622222222'),
(3, 'Rolland', 'Camille', 'Dermatologie', 'camille.rolland@mail.com', '0633333333'),
(4, 'Fabre', 'Nicolas', 'Gastro-entérologie', 'nicolas.fabre@mail.com', '0644444444'),
(5, 'Renaud', 'Sophie', 'Psychiatrie', 'sophie.renaud@mail.com', '0655555555'),
(30, 'Doctor', 'Test', 'Généraliste', 'test.doctor@gmail.com', '0612345670'),
(31, 'Doctor', 'Test', 'Généraliste', 'test.doctor@gmail.com', '0612345670'),
(40, 'Bouh', 'Sad', 'Cardiologie', 'sadbouh@Email.com', '0612345678'),
(41, 'Ahmed', 'Dr', 'Pédiatrie', 'ahmed@Email.com', '0612345679'),
(42, 'Abdi', 'Dr', 'Chirurgie', 'abdi@Email.com', '0612345680'),
(51, 'Medecin1', 'Prenom1', 'Cardiologie', 'medecin1@example.com', '0600000001'),
(52, 'Medecin2', 'Prenom2', 'Neurologie', 'medecin2@example.com', '0600000002'),
(53, 'Medecin3', 'Prenom3', 'Dermatologie', 'medecin3@example.com', '0600000003'),
(54, 'Medecin4', 'Prenom4', 'Pédiatrie', 'medecin4@example.com', '0600000004'),
(55, 'Medecin5', 'Prenom5', 'Généraliste', 'medecin5@example.com', '0600000005'),
(79, NULL, NULL, 'pscycatrie', NULL, NULL),
(44, NULL, NULL, 'google', NULL, NULL),
(80, NULL, NULL, 'psychologist', NULL, NULL),
(84, 'xavi', 'mister', 'cadiologie', '25024@Supnum.mr', '36712319'),
(85, NULL, NULL, '', NULL, NULL),
(86, 'abdy', 'mohameden', 'cadiologie', '24131@Supnum.mr', '22247088'),
(87, 'ahmed', 'mohameden', 'psychologist', '24312@supnum.mr', '41745904'),
(89, 'ahmed', 'mohameden', 'psychologist', '24319@supnum.mr', '41745904');

-- --------------------------------------------------------

--
-- Structure de la table `ordonnance`
--

DROP TABLE IF EXISTS `ordonnance`;
CREATE TABLE IF NOT EXISTS `ordonnance` (
  `id_ordonnance` int NOT NULL AUTO_INCREMENT,
  `id_traitement` int DEFAULT NULL,
  `date_ordonnance` date DEFAULT NULL,
  `médicaments` varchar(255) NOT NULL,
  PRIMARY KEY (`id_ordonnance`),
  KEY `id_traitement` (`id_traitement`)
) ENGINE=MyISAM AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `ordonnance`
--

INSERT INTO `ordonnance` (`id_ordonnance`, `id_traitement`, `date_ordonnance`, `médicaments`) VALUES
(1, 1, '2024-01-01', 'Bétabloquants'),
(2, 2, '2024-02-01', 'Aspirine'),
(3, 3, '2024-03-01', 'Triptans'),
(30, 10, '2025-07-01', 'uploads/ord_6863c755546566.38754230.pdf'),
(31, 10, '2025-07-01', 'uploads/ord_6863c87521d388.31738334.pdf'),
(38, 11, '2025-07-01', 'uploads/ord_6863e8b04bc3f6.58753809.pdf'),
(39, 11, '2025-07-01', 'uploads/ord_6863e8f06f4350.72478079.pdf'),
(50, 23, '2025-07-14', 'Paracetamol 1g'),
(51, 25, '2025-07-09', 'Amoxicilline 500mg, 3 fois par jour pendant 19 jours'),
(52, 29, '2025-07-15', 'Amoxicilline 500mg, 3 fois par jour pendant 7 jours'),
(53, 20, '2025-07-15', 'Amoxicilline 500mg, 3 fois par jour pendant 7 jours'),
(54, 79, '2025-07-17', 'uploads/ordonnances/ordonnance_2_1752428814.pdf'),
(55, 80, '2025-07-13', 'uploads/ordonnances/ordonnance_78_1752428962.pdf'),
(56, 81, '2025-07-13', 'uploads/ordonnances/ordonnance_1_1752429269.pdf'),
(57, 82, '2025-07-13', 'uploads/ordonnances/ordonnance_9_1752429456.pdf'),
(58, 83, '2025-07-11', 'uploads/ordonnances/ordonnance_9_1752429599.pdf');

--
-- Déclencheurs `ordonnance`
--
DROP TRIGGER IF EXISTS `after_ordonnance_insert`;
DELIMITER $$
CREATE TRIGGER `after_ordonnance_insert` AFTER INSERT ON `ordonnance` FOR EACH ROW BEGIN
  INSERT INTO archive_ordonnance (
    id_ordonnance,
    id_traitement,
    date_ordonnance,
    médicaments
  ) VALUES (
    NEW.id_ordonnance,
    NEW.id_traitement,
    NEW.date_ordonnance,
    NEW.médicaments
  );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `patient`
--

DROP TABLE IF EXISTS `patient`;
CREATE TABLE IF NOT EXISTS `patient` (
  `id_patient` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) DEFAULT NULL,
  `prenom` varchar(50) DEFAULT NULL,
  `date_naissance` date DEFAULT NULL,
  `sexe` varchar(10) DEFAULT NULL,
  `adresse` varchar(255) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `dossier_medical` text,
  PRIMARY KEY (`id_patient`)
) ENGINE=MyISAM AUTO_INCREMENT=96 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `patient`
--

INSERT INTO `patient` (`id_patient`, `nom`, `prenom`, `date_naissance`, `sexe`, `adresse`, `telephone`, `email`, `dossier_medical`) VALUES
(1, 'Martin', 'Claire', '1985-06-15', 'Femme', '123 Rue Paris', '0600001111', 'claire.martin@mail.com', 'Allergie pénicilline'),
(2, 'Durand', 'Paul', '1978-03-10', 'Homme', '456 Rue Lyon', '0600002222', 'paul.durand@mail.com', 'Antécédents AVC'),
(3, 'Nguyen', 'Thierry', '1992-11-05', 'Homme', '789 Rue Marseille', '0600003333', 'thierry.nguyen@mail.com', 'Diabète type 2'),
(4, 'Roux', 'Isabelle', '1967-07-20', 'Femme', '321 Rue Nice', '0600004444', 'isabelle.roux@mail.com', 'Hypertension'),
(5, 'Fabre', 'Lucie', '1990-12-01', 'Femme', '147 Rue Lille', '0600005555', 'lucie.fabre@mail.com', 'Asthme'),
(6, 'Garcia', 'Antoine', '1982-01-15', 'Homme', '369 Rue Bordeaux', '0600006666', 'antoine.garcia@mail.com', 'Aucun'),
(51, 'Patient1', 'Prenom1', '1990-01-01', 'Femme', '1 Rue de la Paix', '0700000001', 'patient1@example.com', 'Dossier médical pour Patient1'),
(8, 'Lemoine', 'Karim', '1995-09-09', 'Homme', '753 Rue Dijon', '0600008888', 'karim.lemoine@mail.com', 'Insuffisance rénale'),
(9, 'Benoit', 'Sarah', '2025-07-10', 'M', 'supnum-nouakchott\r\ndar elbarka-nouakchott', '0600009999', 'sarah.benoit@mail.com', 'Epilepsie'),
(10, 'Pires', 'Julien', '2000-10-10', 'Homme', '159 Rue Le Havre', '0600010000', 'julien.pires@mail.com', 'Allergie gluten'),
(11, 'kerim', 'ahmed', '2000-01-12', 'Homme', 'aleg', '44465665', 'kerim@gmail.com', ''),
(12, 'ahmed', 'med', '2005-01-10', 'Homme', 'aleg', '46444334', 'ahmedmed73@gmail.com', ''),
(13, 'ahmed', 'med', '2005-01-10', 'Homme', 'aleg', '46444334', 'ahmedmed173@gmail.com', ''),
(14, 'bissat', 'ahmed', '0000-00-00', 'Homme', 'aleg', '32222242', '24124@supnum.mr', ''),
(15, 'ahmed', 'mohamed', '2005-06-19', 'Homme', 'nouakchott', '44432332', '24122@supnum.mr', 'Diabète type 2'),
(16, 'saadbouh', 'moulay', '2005-06-19', 'Homme', 'kiffa', '44432332', 'saadbouh@gmail.com', ''),
(17, 'brahim', 'sidi cheikh', '2005-02-21', 'Homme', 'nouakchoot', '46534344', 'brahim@gmail.com', ''),
(18, 'ahm', 'ed', '2000-10-20', 'Homme', 'nktt', '22247088', 'ahmedsidiya@gmail.com', ''),
(19, 'bah', 'vatimetou', '2006-08-25', 'Femme', 'diambour', '34732915', 'zaineb@gmail.com', ''),
(42, 'toto', 'titi', '2000-05-12', 'Homme', 'kiffe', '15545515', 'toto@gmail.com', NULL),
(35, 'mokhtar', 'abdou', '2005-02-10', 'Homme', 'librakna', '48687745', 'Mokhtar@gmail.com', NULL),
(36, 'Amar', 'ibm', '2008-10-12', 'Homme', 'medina', '45334437', 'ouldibm@gmail.com', NULL),
(37, 'nini', 'idoumou', '2026-01-20', 'Homme', 'medina', '46444565', 'niniidoumu@gmaiiiil.com', NULL),
(38, 'GOHY', 'Abdellahi', '2006-01-10', 'Homme', 'zatar', '41329982', 'gohy@gmail.com', NULL),
(40, 'saadbouh', 'moulaye', '2005-05-05', 'Homme', 'kiffe', '33329210', '24212@gmail.com', NULL),
(52, 'Patient2', 'Prenom2', '1991-01-01', 'Homme', '2 Rue de la Paix', '0700000002', 'patient2@example.com', 'Dossier médical pour Patient2'),
(53, 'Patient3', 'Prenom3', '1992-01-01', 'Femme', '3 Rue de la Paix', '0700000003', 'patient3@example.com', 'Dossier médical pour Patient3'),
(54, 'Patient4', 'Prenom4', '1993-01-01', 'Homme', '4 Rue de la Paix', '0700000004', 'patient4@example.com', 'Dossier médical pour Patient4'),
(55, 'Patient5', 'Prenom5', '1994-01-01', 'Femme', '5 Rue de la Paix', '0700000005', 'patient5@example.com', 'Dossier médical pour Patient5'),
(56, 'Patient6', 'Prenom6', '1995-01-01', 'Homme', '6 Rue de la Paix', '0700000006', 'patient6@example.com', 'Dossier médical pour Patient6'),
(57, 'Patient7', 'Prenom7', '1996-01-01', 'Femme', '7 Rue de la Paix', '0700000007', 'patient7@example.com', 'Dossier médical pour Patient7'),
(58, 'Patient8', 'Prenom8', '1997-01-01', 'Homme', '8 Rue de la Paix', '0700000008', 'patient8@example.com', 'Dossier médical pour Patient8'),
(59, 'Patient9', 'Prenom9', '1998-01-01', 'Femme', '9 Rue de la Paix', '0700000009', 'patient9@example.com', 'Dossier médical pour Patient9'),
(60, 'Patient10', 'Prenom10', '1999-01-01', 'Homme', '10 Rue de la Paix', '07000000010', 'patient10@example.com', 'Dossier médical pour Patient10'),
(91, 'kader', 'mohameden', NULL, NULL, NULL, '41745904', '209312@supnum.mr', NULL),
(92, 'supnum', 'patient', NULL, NULL, NULL, '36542378', 'patient@supnum.mr', NULL),
(93, 'Ahmed', 'behan', NULL, NULL, NULL, '41745904', '24000@Supnum.mr', NULL),
(94, NULL, NULL, '2025-07-07', 'M', 'supnum-nouakchott\r\ndar elbarka-nouakchott', NULL, NULL, NULL),
(95, 'abdy', 'fatous', '2000-02-04', 'Femme', 'supnum-nouakchott\r\ndar elbarka-nouakchott', '41745904', 'sfgnlo@supnum.mr', 'hdfjkdfjrdjr');

--
-- Déclencheurs `patient`
--
DROP TRIGGER IF EXISTS `after_dossier_update`;
DELIMITER $$
CREATE TRIGGER `after_dossier_update` AFTER UPDATE ON `patient` FOR EACH ROW BEGIN
  IF OLD.dossier_medical <> NEW.dossier_medical THEN
    INSERT INTO archive_dossier_medical (
      id_patient,
      dossier_medical
    ) VALUES (
      NEW.id_patient,
      NEW.dossier_medical
    );
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `rendezvous`
--

DROP TABLE IF EXISTS `rendezvous`;
CREATE TABLE IF NOT EXISTS `rendezvous` (
  `id_rdv` int NOT NULL AUTO_INCREMENT,
  `id_traitement` int DEFAULT NULL,
  `date_rdv` date DEFAULT NULL,
  `heure` time DEFAULT NULL,
  `lieu` varchar(100) DEFAULT NULL,
  `motif` text,
  `statut` enum('en_attente','confirme','annule') DEFAULT 'en_attente',
  PRIMARY KEY (`id_rdv`),
  KEY `id_traitement` (`id_traitement`)
) ENGINE=MyISAM AUTO_INCREMENT=135 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `rendezvous`
--

INSERT INTO `rendezvous` (`id_rdv`, `id_traitement`, `date_rdv`, `heure`, `lieu`, `motif`, `statut`) VALUES
(1, 1, '2024-01-02', '10:00:00', 'Clinique A', 'Contrôle', 'confirme'),
(2, 2, '2024-02-03', '11:00:00', 'Clinique A', 'Consultation', 'confirme'),
(3, 3, '2024-03-05', '14:00:00', 'Clinique B', 'Évaluation', 'confirme'),
(35, 11, '2025-11-14', '12:00:00', 'Clinique A', 'malade', 'annule'),
(9, 15, '0000-00-00', '12:00:00', 'kiffa', 'malade', 'en_attente'),
(6, 2, '0000-00-00', '12:00:00', 'kiffa', 'malade', 'en_attente'),
(8, 2, '2025-11-13', '12:00:00', 'kiffa', 'malade', 'annule'),
(37, 10, '2026-05-15', '12:00:00', 'Clinique A', 'kjhg', 'annule'),
(60, 20, '2025-07-14', '10:00:00', 'Clinique A', 'Consultation de suivi', 'confirme'),
(39, 13, '2025-06-12', '12:00:00', 'Clinique A', 'malade', 'annule'),
(40, 11, '2025-06-15', '12:00:00', 'Clinique A', 'malade', 'annule'),
(41, 11, '2025-07-15', '10:00:00', 'Clinique A', 'malade', 'annule'),
(42, 10, '2025-06-18', '09:00:00', 'Clinique A', 'malade', 'confirme'),
(43, 11, '2025-06-05', '10:00:00', 'Clinique A', 'malade', 'annule'),
(44, 10, '2025-12-06', '09:00:00', 'Clinique A', 'khgghn', 'confirme'),
(45, 11, '2025-12-14', '12:45:00', 'Clinique A', 'salle 12', 'confirme'),
(46, 10, '2025-12-12', '12:00:00', 'Clinique A', 'dodo', 'confirme'),
(47, 10, '2026-02-22', '12:00:00', 'Clinique A', 'malade', 'confirme'),
(48, 11, '2026-02-24', '12:00:00', 'Clinique A', 'malade', 'confirme'),
(49, 10, '2026-02-24', '12:00:00', 'Clinique A', 'rasou yewj3ou', 'annule'),
(50, 0, '2025-08-10', '18:00:00', 'Clinique A', 'je bois une bouteille de javel', 'en_attente'),
(80, 20, '2025-07-14', '10:00:00', 'Clinique A - Salle 1', 'Consultation de suivi', 'confirme'),
(82, 22, '2025-07-08', '09:00:00', 'Clinique B', 'Contrôle annuel (passé)', 'confirme'),
(84, 20, '2025-07-14', '10:00:00', 'Clinique A - Salle 1', 'Consultation de suivi', 'confirme'),
(85, 21, '2025-07-13', '11:30:00', 'Hôpital Central', 'Examen post-opératoire', 'annule'),
(86, 22, '2025-07-08', '09:00:00', 'Clinique B', 'Contrôle annuel (passé)', 'confirme'),
(87, 23, '2025-07-20', '14:00:00', 'Clinique A - Salle 3', 'Vaccination', 'annule'),
(88, 24, '2025-07-13', '18:05:00', 'Cabinet du Dr. Medecin5', 'Urgence mineure', 'en_attente'),
(90, 25, '2025-07-16', '15:00:00', 'Clinique A', 'Demande de consultation', 'en_attente'),
(92, 26, '2025-07-17', '10:30:00', 'Hôpital Central', 'Analyse de résultats', 'en_attente'),
(93, 27, '2025-07-21', '11:00:00', 'Clinique B', 'Premier rendez-vous', 'en_attente'),
(94, 28, '2025-07-23', '16:30:00', 'Clinique A', 'Consultation pédiatrique', 'en_attente'),
(95, 29, '2025-07-25', '09:30:00', 'Cabinet du Dr. Medecin5', 'Bilan de santé', 'en_attente'),
(96, 20, '2025-07-12', '14:00:00', 'Clinique A', 'Contrôle (annulé par le patient)', 'annule'),
(98, 21, '2025-07-03', '09:00:00', 'Hôpital Central', 'Rendez-vous annulé (médecin absent)', 'annule'),
(120, 20, '2025-07-12', '14:00:00', 'Clinique A', 'Contrôle (annulé par le patient)', 'annule'),
(121, 21, '2025-07-03', '09:00:00', 'Hôpital Central', 'Rendez-vous annulé (médecin absent)', 'annule'),
(122, 22, '2025-07-14', '17:00:00', 'Clinique B', 'Soins dentaires (annulé)', 'annule'),
(123, 23, '2025-06-23', '11:30:00', 'Clinique A', 'Consultation (annulée)', 'annule'),
(124, 24, '2025-07-18', '10:00:00', 'Cabinet du Dr. Medecin5', 'Suivi (annulé, date reportée)', 'annule'),
(125, 60, '2025-07-30', '14:47:00', 'cabinetA', 'Consultation de suivi', 'en_attente'),
(126, 61, '2025-07-31', '21:08:00', 'Clinique A - Salle 1', 'Consultation de suivi', 'confirme'),
(127, 62, '2025-07-31', '16:06:00', 'Cabinet du Dr. Medecin5', 'Urgence mineure', 'annule'),
(128, 63, '2025-07-15', '18:36:00', 'Hôpital Central', 'Urgence mineure', 'en_attente'),
(130, 84, '2025-07-29', '14:17:00', 'cabinetA', 'Consultation de suivi', 'confirme'),
(134, 91, '2025-07-15', '23:24:00', 'Clinique A', 'dhiarre', 'annule');

-- --------------------------------------------------------

--
-- Structure de la table `traitement`
--

DROP TABLE IF EXISTS `traitement`;
CREATE TABLE IF NOT EXISTS `traitement` (
  `id_traitement` int NOT NULL AUTO_INCREMENT,
  `id_patient` int DEFAULT NULL,
  `id_medecin` int DEFAULT NULL,
  `id_assistant` int DEFAULT NULL,
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  `diagnostic` text,
  PRIMARY KEY (`id_traitement`),
  KEY `id_patient` (`id_patient`),
  KEY `id_medecin` (`id_medecin`)
) ENGINE=MyISAM AUTO_INCREMENT=92 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `traitement`
--

INSERT INTO `traitement` (`id_traitement`, `id_patient`, `id_medecin`, `id_assistant`, `date_debut`, `date_fin`, `diagnostic`) VALUES
(1, 1, 1, NULL, '2024-01-01', '2024-01-15', 'Hypertension'),
(2, 2, 1, NULL, '2024-02-01', '2024-02-10', 'Tachycardie'),
(3, 3, 31, NULL, '2024-03-01', '2024-03-20', 'Migraine chronique'),
(4, 4, 3, NULL, '2024-04-01', '2024-04-10', 'Eczéma'),
(5, 5, 4, NULL, '2024-05-01', '2024-05-15', 'Reflux gastro-œsophagien'),
(6, 15, 4, NULL, '2025-05-11', '2025-05-27', 'Hypertension'),
(7, 17, 4, NULL, '2025-05-11', NULL, NULL),
(8, 19, 4, NULL, '2025-05-15', NULL, NULL),
(20, 51, 51, 71, '2025-07-13', NULL, 'Diagnostic pour le patient 51'),
(10, 37, 30, NULL, '2025-05-25', NULL, NULL),
(11, 38, 30, NULL, '2025-05-27', NULL, NULL),
(86, 93, 4, NULL, '2025-07-15', NULL, NULL),
(13, 41, 30, NULL, '2025-06-03', NULL, NULL),
(32, 5, 30, 2, '2025-06-19', '2025-06-25', 'blabla'),
(91, 93, 1, NULL, '2025-07-15', NULL, NULL),
(21, 15, 52, 72, '2025-07-14', NULL, 'Diagnostic pour le patient 52'),
(22, 53, 53, 73, '2025-07-15', NULL, 'Diagnostic pour le patient 53'),
(23, 54, 54, 74, '2025-07-16', NULL, 'Diagnostic pour le patient 54'),
(24, 55, 55, 75, '2025-07-17', NULL, 'Diagnostic pour le patient 55'),
(25, 56, 51, 71, '2025-07-18', NULL, 'Diagnostic pour le patient 56'),
(26, 57, 52, 72, '2025-07-19', NULL, 'Diagnostic pour le patient 57'),
(27, 58, 53, 73, '2025-07-20', NULL, 'Diagnostic pour le patient 58'),
(28, 59, 54, 74, '2025-07-21', NULL, 'Diagnostic pour le patient 59'),
(29, 60, 55, 75, '2025-07-22', NULL, 'Diagnostic pour le patient 60'),
(50, 61, 51, 71, '2025-07-13', NULL, 'Diagnostic pour le patient 61'),
(51, 62, 52, 72, '2025-07-14', NULL, 'Diagnostic pour le patient 62'),
(52, 63, 53, 73, '2025-07-15', NULL, 'Diagnostic pour le patient 63'),
(53, 64, 54, 74, '2025-07-16', NULL, 'Diagnostic pour le patient 64'),
(54, 65, 55, 75, '2025-07-17', NULL, 'Diagnostic pour le patient 65'),
(55, 66, 51, 71, '2025-07-18', NULL, 'Diagnostic pour le patient 66'),
(56, 67, 52, 72, '2025-07-19', NULL, 'Diagnostic pour le patient 67'),
(63, 9, 79, NULL, '2025-07-23', NULL, 'Consultation pour : Urgence mineure'),
(62, 78, 79, NULL, '2025-07-31', NULL, 'Consultation pour : Urgence mineure'),
(59, 70, 55, 75, '2025-07-22', NULL, 'Diagnostic pour le patient 70'),
(60, 78, 79, NULL, '2025-07-30', NULL, 'Consultation pour : Consultation de suivi'),
(61, 78, 31, NULL, '2025-07-31', NULL, 'Consultation pour : Consultation de suivi'),
(85, 93, 54, NULL, '2025-07-14', NULL, 'Hospitalisation en service Cardiologie'),
(84, 36, 1, NULL, '2025-07-29', NULL, 'Consultation pour : Consultation de suivi'),
(83, 9, 53, NULL, '2025-07-11', NULL, 'Ordonnance du 11/07/2025'),
(82, 9, 79, NULL, '2025-07-13', NULL, 'Ordonnance du 13/07/2025'),
(81, 1, 31, NULL, '2025-07-13', NULL, 'Ordonnance du 13/07/2025'),
(70, 78, 31, NULL, '2025-07-09', NULL, 'Hospitalisation en service supnum'),
(71, 78, 79, NULL, '2025-07-01', NULL, 'Hospitalisation en service Cardiologies'),
(72, 36, 53, NULL, '2025-07-08', NULL, 'Hospitalisation en service Urgences'),
(73, 78, 79, NULL, '2025-07-08', NULL, 'Hospitalisation en service Cardiologies'),
(74, 78, 79, NULL, '2025-07-08', NULL, 'Hospitalisation en service Cardiologies'),
(75, 1, 52, NULL, '2025-07-09', NULL, 'Hospitalisation en service Cardiologies'),
(76, 78, 52, NULL, '2025-07-09', NULL, 'Hospitalisation en service Cardiologies'),
(80, 78, 31, NULL, '2025-07-13', NULL, 'Ordonnance du 13/07/2025'),
(79, 2, 44, NULL, '2025-07-17', NULL, 'Ordonnance du 17/07/2025'),
(87, 42, 31, NULL, '2025-07-15', NULL, NULL),
(88, 3, 0, NULL, '2025-07-15', NULL, NULL),
(89, 4, 31, NULL, '2025-08-08', NULL, 'Hospitalisation en service Neurologie'),
(90, 93, 79, NULL, '2025-07-15', NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `utilisateur`
--

DROP TABLE IF EXISTS `utilisateur`;
CREATE TABLE IF NOT EXISTS `utilisateur` (
  `id_utilisateur` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telephone` varchar(20) NOT NULL,
  `rôle` varchar(50) NOT NULL,
  `photo_profil` varchar(255) DEFAULT NULL COMMENT 'Chemin vers la photo de profil de l''utilisateur',
  `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date de la dernière modification du profil',
  `modifie_par` int DEFAULT NULL COMMENT 'ID de l''admin qui a fait la dernière modification',
  PRIMARY KEY (`id_utilisateur`)
) ENGINE=MyISAM AUTO_INCREMENT=95 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `utilisateur`
--

INSERT INTO `utilisateur` (`id_utilisateur`, `nom`, `prenom`, `email`, `telephone`, `rôle`, `photo_profil`, `date_creation`, `date_modification`, `modifie_par`) VALUES
(1, 'ahmed', 'med', 'ahmedmed@gmail.com', '46444334', 'Patient', NULL, '2025-07-13 07:24:06', NULL, NULL),
(2, 'ahmed', 'med', 'ahmedmed73@gmail.com', '46444334', 'Patient', NULL, '2025-07-13 07:24:06', NULL, NULL),
(3, 'ahmed', 'med', 'ahmedmed173@gmail.com', '46444334', 'Patient', NULL, '2025-07-13 07:24:06', NULL, NULL),
(4, 'bissat', 'ahmed', '24124@supnum.mr', '32222242', 'Patient', NULL, '2025-07-13 07:24:06', NULL, NULL),
(5, 'ahmed', 'mohamed', '24122@supnum.mr', '44432332', 'Patient', NULL, '2025-07-13 07:24:06', NULL, NULL),
(6, 'Nom', 'Prénom', 'assistant@exemple.com', '0123456789', 'assistant', NULL, '2025-07-13 07:24:06', NULL, NULL),
(7, 'saadbouh', 'moulay', 'saadbouh@gmail.com', '44432332', 'Patient', NULL, '2025-07-13 07:24:06', NULL, NULL),
(8, 'brahim', 'sidi cheikh', 'brahim@gmail.com', '46534344', 'Patient', NULL, '2025-07-13 07:24:06', NULL, NULL),
(9, 'ahm', 'ed', '24068@supnum.mr', '41745904', 'patient', NULL, '2025-07-13 07:24:06', NULL, NULL),
(10, 'bah', 'vatimetou', 'zaineb@gmail.com', '34732915', 'Patient', NULL, '2025-07-13 07:24:06', NULL, NULL),
(31, 'Doctor', 'Test', 'test.doctor@gmail.com', '0612345670', 'medecin', NULL, '2025-07-13 07:24:06', NULL, NULL),
(32, 'Assistant', 'Test', 'test.assistant@gmail.com', '0700000000', 'assistant', NULL, '2025-07-13 07:24:06', NULL, NULL),
(52, 'Medecin2', 'Prenom2', 'medecin2@example.com', '0600000002', 'medecin', NULL, '2025-07-13 07:24:06', NULL, NULL),
(35, 'mokhtar', 'abdou', 'Mokhtar@gmail.com', '48687745', 'Patient', NULL, '2025-07-13 07:24:06', NULL, NULL),
(36, 'Amar', 'ibm', 'ouldibm@gmail.com', '45334437', 'Patient', NULL, '2025-07-13 07:24:06', NULL, NULL),
(38, 'GOHY', 'Abdellahi', 'gohy@gmail.com', '41329982', 'Patient', NULL, '2025-07-13 07:24:06', NULL, NULL),
(40, 'saadbouh', 'moulaye', '24212@gmail.com', '33329210', 'Patient', NULL, '2025-07-13 07:24:06', NULL, NULL),
(51, 'Medecin1', 'Prenom1', 'medecin1@example.com', '0600000001', 'medecin', NULL, '2025-07-13 07:24:06', NULL, NULL),
(43, 'abdy', 'mohameden', '24068@supnum.mr', '41745904', 'admin', 'uploads/profiles/profile_43_1752575329.jpg', '2025-07-13 07:24:06', '2025-07-15 10:28:49', NULL),
(44, 'ahmed', 'mohameden', '2416889@Supnum.mr', '31745500', 'medecin', NULL, '2025-07-13 07:24:06', NULL, NULL),
(53, 'Medecin3', 'Prenom3', 'medecin3@example.com', '0600000003', 'medecin', NULL, '2025-07-13 07:24:06', NULL, NULL),
(54, 'Medecin4', 'Prenom4', 'medecin4@example.com', '0600000004', 'medecin', NULL, '2025-07-13 07:24:06', NULL, NULL),
(55, 'Medecin5', 'Prenom5', 'medecin5@example.com', '0600000005', 'medecin', NULL, '2025-07-13 07:24:06', NULL, NULL),
(61, 'Patient1', 'Prenom1', 'patient1@example.com', '0700000001', 'patient', NULL, '2025-07-13 07:24:06', NULL, NULL),
(62, 'Patient2', 'Prenom2', 'patient2@example.com', '0700000002', 'patient', NULL, '2025-07-13 07:24:06', NULL, NULL),
(63, 'Patient3', 'Prenom3', 'patient3@example.com', '0700000003', 'patient', NULL, '2025-07-13 07:24:06', NULL, NULL),
(64, 'Patient4', 'Prenom4', 'patient4@example.com', '0700000004', 'patient', NULL, '2025-07-13 07:24:06', NULL, NULL),
(65, 'Patient5', 'Prenom5', 'patient5@example.com', '0700000005', 'patient', NULL, '2025-07-13 07:24:06', NULL, NULL),
(66, 'Patient6', 'Prenom6', 'patient6@example.com', '0700000006', 'patient', NULL, '2025-07-13 07:24:06', NULL, NULL),
(67, 'Patient7', 'Prenom7', 'patient7@example.com', '0700000007', 'patient', NULL, '2025-07-13 07:24:06', NULL, NULL),
(81, 'hhhh', 'good', '24058@Supnum.mr', '41745904', 'medecin', NULL, '2025-07-14 06:44:53', NULL, NULL),
(70, 'Patient10', 'Prenom10', 'patient10@example.com', '07000000010', 'patient', NULL, '2025-07-13 07:24:06', NULL, NULL),
(71, 'Assistant1', 'Prenom1', 'assistant1@example.com', '0800000001', 'assistant', NULL, '2025-07-13 07:24:06', NULL, NULL),
(72, 'Assistant2', 'Prenom2', 'assistant2@example.com', '0800000002', 'assistant', NULL, '2025-07-13 07:24:06', NULL, NULL),
(73, 'Assistant3', 'Prenom3', 'assistant3@example.com', '0800000003', 'assistant', NULL, '2025-07-13 07:24:06', NULL, NULL),
(74, 'Assistant4', 'Prenom4', 'assistant4@example.com', '0800000004', 'assistant', NULL, '2025-07-13 07:24:06', NULL, NULL),
(75, 'Assistant5', 'Prenom5', 'assistant5@example.com', '0800000005', 'assistant', NULL, '2025-07-13 07:24:06', NULL, NULL),
(76, 'ahmed', 'mohameden', '230954@Supnum.mr', '41745904', 'medecin', NULL, '2025-07-13 07:24:06', '2025-07-13 12:28:32', 43),
(77, 'ahmed', 'mohameden', '24320@supnum.mr', '41745904', 'assistant', NULL, '2025-07-13 08:38:09', NULL, NULL),
(78, 'abdy', 'fatou', '24143@supnum.mr', '31745009', 'patient', NULL, '2025-07-13 08:40:53', '2025-07-13 13:06:26', NULL),
(79, 'med', 'cin', '23070@supnum.mr', '41745904', 'medecin', NULL, '2025-07-13 09:16:14', NULL, NULL),
(80, 'sidi', 'mohameden', '240908@Supnum.mr', '41745904', 'medecin', NULL, '2025-07-13 23:54:22', NULL, NULL),
(82, 'hhhh', 'good', '240878@Supnum.mr', '41745904', 'medecin', NULL, '2025-07-14 06:45:35', NULL, NULL),
(83, 'x', 'mister', '25025@Supnum.mr', '36712317', 'medecin', NULL, '2025-07-14 06:52:45', NULL, NULL),
(84, 'xavi', 'mister', '25024@Supnum.mr', '36712319', 'medecin', NULL, '2025-07-14 07:00:36', NULL, NULL),
(85, 'ahmed', 'baba', '24028@supnum.mr', '22247088', 'medecin', NULL, '2025-07-14 10:14:34', '2025-07-14 10:14:49', NULL),
(86, 'abdy', 'mohameden', '24131@Supnum.mr', '22247088', 'medecin', NULL, '2025-07-14 12:24:13', NULL, NULL),
(87, 'ahmed', 'mohameden', '24312@supnum.mr', '41745904', 'medecin', NULL, '2025-07-14 12:34:41', NULL, NULL),
(88, 'supnum', 'mohameden', '27312@supnum.mr', '41745904', 'patient', NULL, '2025-07-14 16:10:42', NULL, NULL),
(89, 'ahmed', 'mohameden', '24319@supnum.mr', '41745904', 'medecin', NULL, '2025-07-14 16:14:58', NULL, NULL),
(90, 'Abd', 'mohameden', '20312@supnum.mr', '41745904', 'patient', NULL, '2025-07-14 16:15:36', NULL, NULL),
(91, 'kader', 'mohameden', '209312@supnum.mr', '41745904', 'patient', NULL, '2025-07-14 16:24:50', NULL, NULL),
(92, 'supnum', 'patient', 'patient@supnum.mr', '36542378', 'patient', NULL, '2025-07-14 16:36:44', NULL, NULL),
(93, 'Ahmed', 'behan', '24000@Supnum.mr', '41745904', 'patient', NULL, '2025-07-15 08:56:53', NULL, NULL),
(94, 'ahmed', 'mohameden', '27068@supnum.mr', '41745904', 'patient', NULL, '2025-07-15 09:41:47', NULL, NULL);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
