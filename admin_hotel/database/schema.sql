CREATE DATABASE IF NOT EXISTS hotels CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hotels;

CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  mot_de_passe VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS hotels (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(150) NOT NULL,
  ville VARCHAR(100) NOT NULL,
  adresse VARCHAR(255) DEFAULT NULL,
  telephone VARCHAR(40) DEFAULT NULL,
  email VARCHAR(150) DEFAULT NULL,
  etoiles TINYINT NOT NULL DEFAULT 3,
  description TEXT,
  publicite VARCHAR(255) DEFAULT NULL,
  image VARCHAR(60) DEFAULT NULL,
  logo VARCHAR(60) DEFAULT NULL,
  statut ENUM('en_attente','actif','suspendu') NOT NULL DEFAULT 'en_attente',
  pub_fin DATE DEFAULT NULL,
  acces_mdp VARCHAR(100) DEFAULT NULL, -- dernier mot de passe donné à l'hôtel (consultable par l'admin)
  gestion_hotel_id INT DEFAULT NULL, -- hôtel correspondant dans gestion-hotel (laravel_hotel.hotels)
  cree_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS catalogues (
  id INT AUTO_INCREMENT PRIMARY KEY,
  hotel_id INT NOT NULL,
  titre VARCHAR(150) NOT NULL,
  description TEXT,
  prix DECIMAL(10,0) NOT NULL DEFAULT 0,
  capacite TINYINT NOT NULL DEFAULT 2,
  nb_chambres INT NOT NULL DEFAULT 3, -- nombre de chambres créées dans gestion-hotel pour cette offre
  image VARCHAR(60) DEFAULT NULL,
  disponible TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (hotel_id) REFERENCES hotels(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Galerie de photos de chaque hôtel (matériel pour les publicités)
CREATE TABLE IF NOT EXISTS photos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  hotel_id INT NOT NULL,
  fichier VARCHAR(60) NOT NULL,
  legende VARCHAR(150) DEFAULT NULL,
  cree_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (hotel_id) REFERENCES hotels(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reservations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  hotel_id INT NOT NULL,
  catalogue_id INT NOT NULL,
  client_nom VARCHAR(120) NOT NULL,
  client_email VARCHAR(150) NOT NULL,
  client_telephone VARCHAR(40) NOT NULL,
  arrivee DATE NOT NULL,
  depart DATE NOT NULL,
  personnes TINYINT NOT NULL DEFAULT 1,
  total DECIMAL(12,0) NOT NULL DEFAULT 0,
  statut ENUM('en_attente','confirmee','annulee') NOT NULL DEFAULT 'en_attente',
  gestion_reservation_id INT DEFAULT NULL,
  cree_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (hotel_id) REFERENCES hotels(id) ON DELETE CASCADE,
  FOREIGN KEY (catalogue_id) REFERENCES catalogues(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Paiements de publicité : un hôtel n'apparaît sur la page d'accueil que tant que hotels.pub_fin >= aujourd'hui
CREATE TABLE IF NOT EXISTS paiements_pub (
  id INT AUTO_INCREMENT PRIMARY KEY,
  hotel_id INT NOT NULL,
  montant DECIMAL(12,0) NOT NULL,
  jours INT NOT NULL,
  mode VARCHAR(40) DEFAULT NULL,
  debut DATE NOT NULL,
  fin DATE NOT NULL,
  cree_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (hotel_id) REFERENCES hotels(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Coordonnées de l'entreprise de gestion : affichées aux hôtels dont l'accès est suspendu
CREATE TABLE IF NOT EXISTS parametres (
  cle VARCHAR(40) PRIMARY KEY,
  valeur VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB;
INSERT IGNORE INTO parametres (cle, valeur) VALUES ('entreprise', 'HOTELPRO'), ('telephone', ''), ('email', '');

-- Administrateur général par défaut : admin@hotel.local / admin123 (à changer)
INSERT IGNORE INTO admins (nom, email, mot_de_passe)
VALUES ('Administrateur général', 'admin@hotel.local', '$2y$12$ZKnFQ/A6XrVO.U9UfTYZC.P8Q/QboaMGgoLXLyyh/BCqzlvJ2cR4a');
