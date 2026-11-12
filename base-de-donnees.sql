CREATE DATABASE IF NOT EXISTS hotel_pixel
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE hotel_pixel;

CREATE TABLE IF NOT EXISTS salle (
  id_salle INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nom VARCHAR(80) NOT NULL,
  capacite INT UNSIGNED NOT NULL,
  description TEXT NULL,
  PRIMARY KEY (id_salle),
  UNIQUE KEY uq_salle_nom (nom),
  CONSTRAINT chk_salle_capacite CHECK (capacite > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS jeux (
  id_jeu INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titre VARCHAR(100) NOT NULL,
  plateforme VARCHAR(100) NOT NULL DEFAULT '',
  genre VARCHAR(50) NOT NULL DEFAULT '',
  description TEXT NULL,
  image VARCHAR(255) NULL,
  PRIMARY KEY (id_jeu),
  UNIQUE KEY uq_jeu_titre_plateforme (titre, plateforme)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS evenement (
  id_evenement INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titre VARCHAR(100) NOT NULL,
  description TEXT NOT NULL,
  type_evenement VARCHAR(50) NOT NULL,
  date_debut DATETIME NOT NULL,
  date_fin DATETIME NOT NULL,
  nombre_place INT UNSIGNED NOT NULL,
  statut ENUM('BROUILLON', 'PUBLIE', 'ANNULE') NOT NULL DEFAULT 'PUBLIE',
  date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_evenement),
  KEY idx_evenement_agenda (statut, date_debut),
  CONSTRAINT chk_evenement_dates CHECK (date_fin > date_debut),
  CONSTRAINT chk_evenement_places CHECK (nombre_place > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lier (
  id_evenement INT UNSIGNED NOT NULL,
  id_jeu INT UNSIGNED NOT NULL,
  PRIMARY KEY (id_evenement, id_jeu),
  CONSTRAINT fk_lier_evenement
    FOREIGN KEY (id_evenement) REFERENCES evenement(id_evenement) ON DELETE CASCADE,
  CONSTRAINT fk_lier_jeu
    FOREIGN KEY (id_jeu) REFERENCES jeux(id_jeu)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS derouler (
  id_evenement INT UNSIGNED NOT NULL,
  id_salle INT UNSIGNED NOT NULL,
  PRIMARY KEY (id_evenement, id_salle),
  KEY idx_derouler_salle (id_salle),
  CONSTRAINT fk_derouler_evenement
    FOREIGN KEY (id_evenement) REFERENCES evenement(id_evenement) ON DELETE CASCADE,
  CONSTRAINT fk_derouler_salle
    FOREIGN KEY (id_salle) REFERENCES salle(id_salle)
) ENGINE=InnoDB;

INSERT INTO salle (nom, capacite, description) VALUES
  ('Salle Arcade', 20, 'Consoles et jeux rétro'),
  ('Espace Gaming', 24, 'Espace tournois et jeux en équipe'),
  ('Salon VR', 8, 'Expériences de réalité virtuelle'),
  ('Salle Pixel', 16, 'Ateliers et découvertes')
ON DUPLICATE KEY UPDATE capacite = VALUES(capacite);
