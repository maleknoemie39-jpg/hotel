CREATE TABLE Utilisateur(
   id_utilisateur INT NOT NULL AUTO_INCREMENT,
   nom VARCHAR(50) NOT NULL,
   prenom VARCHAR(50) NOT NULL,
   email VARCHAR(120) NOT NULL,
   mot_de_passe VARCHAR(255) NOT NULL,
   role_utilisateur VARCHAR(20) NOT NULL DEFAULT 'CLIENT',
   PRIMARY KEY(id_utilisateur),
   UNIQUE KEY email_utilisateur (email)
);

CREATE TABLE Inscription(
   id_inscription INT NOT NULL AUTO_INCREMENT,
   date_inscription DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   statut VARCHAR(20) NOT NULL DEFAULT 'CONFIRMEE',
   place_attribue VARCHAR(20) NOT NULL DEFAULT '1',
   PRIMARY KEY(id_inscription)
);

CREATE TABLE evenement(
   id_evenement INT NOT NULL AUTO_INCREMENT,
   titre VARCHAR(100) NOT NULL,
   type_evenement VARCHAR(50) NOT NULL,
   description TEXT NOT NULL,
   date_debut DATETIME NOT NULL,
   date_fin DATETIME NOT NULL,
   nombre_place INT NOT NULL,
   statut VARCHAR(20) NOT NULL DEFAULT 'PUBLIE',
   date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   PRIMARY KEY(id_evenement)
);

CREATE TABLE jeux(
   id_jeu INT auto_increment,
   titre VARCHAR(100) NOT NULL,
   plateforme VARCHAR(50) ,
   genre VARCHAR(50) ,
   description TEXT ,
   image VARCHAR(50) ,
   PRIMARY KEY(id_jeu)
);

CREATE TABLE salle(
   id_salle INT,
   nom VARCHAR(50) ,
   capacite INT,
   description VARCHAR(50) ,
   PRIMARY KEY(id_salle)
);

CREATE TABLE inscrire(
   id_utilisateur INT,
   id_inscription INT,
   PRIMARY KEY(id_utilisateur, id_inscription),
   FOREIGN KEY(id_utilisateur) REFERENCES Utilisateur(id_utilisateur),
   FOREIGN KEY(id_inscription) REFERENCES Inscription(id_inscription)
);

CREATE TABLE concerne(
   id_inscription INT,
   id_evenement INT,
   PRIMARY KEY(id_inscription, id_evenement),
   FOREIGN KEY(id_inscription) REFERENCES Inscription(id_inscription),
   FOREIGN KEY(id_evenement) REFERENCES evenement(id_evenement)
);

CREATE TABLE lier(
   id_evenement INT,
   id_jeu INT,
   PRIMARY KEY(id_evenement, id_jeu),
   FOREIGN KEY(id_evenement) REFERENCES evenement(id_evenement),
   FOREIGN KEY(id_jeu) REFERENCES jeux(id_jeu)
);

CREATE TABLE derouler(
   id_evenement INT,
   id_salle INT,
   PRIMARY KEY(id_evenement, id_salle),
   FOREIGN KEY(id_evenement) REFERENCES evenement(id_evenement),
   FOREIGN KEY(id_salle) REFERENCES salle(id_salle)
);


INSERT INTO salle (id_salle, nom, capacite, description) VALUES
  (1, 'Salle Arcade', 20, 'Consoles et jeux rétro'),
  (2, 'Espace Gaming', 24, 'Espace tournois et jeux en équipe'),
  (3, 'Salon VR', 8, 'Expériences de réalité virtuelle'),
  (4, 'Salle Pixel', 16, 'Ateliers et découvertes')
ON DUPLICATE KEY UPDATE
  nom = VALUES(nom),
  capacite = VALUES(capacite),
  description = VALUES(description);

-- Trois evenements par jour; les valeurs tirees sont conservees dans les tables.
INSERT INTO evenement (
  id_evenement, titre, type_evenement, description,
  date_debut, date_fin, nombre_place, statut, date_creation
)
SELECT
  planning.id_evenement,
  CONCAT(
    ELT(planning.creneau + 1, 'Tournoi', 'Atelier', 'Soirée'),
    ' ',
    ELT(FLOOR(1 + RAND() * 8), 'Mario Kart', 'Minecraft', 'jeux rétro', 'arcade', 'FIFA', 'Fortnite', 'quiz gaming', 'VR')
  ),
  'Animation',
  'Animation de démonstration',
  DATE_ADD(DATE_ADD(CURDATE(), INTERVAL planning.jour DAY), INTERVAL (16 + planning.creneau * 2) HOUR),
  DATE_ADD(DATE_ADD(CURDATE(), INTERVAL planning.jour DAY), INTERVAL (17 + planning.creneau * 2) HOUR),
  s.capacite,
  'PUBLIE',
  CURDATE()
FROM (
  SELECT jours.jour, creneaux.creneau, jours.jour + 1 + creneaux.creneau * 7 AS id_evenement
  FROM (
    SELECT 0 AS jour UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL
    SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6
  ) AS jours
  CROSS JOIN (
    SELECT 0 AS creneau UNION ALL SELECT 1 UNION ALL SELECT 2
  ) AS creneaux
) AS planning
JOIN salle s ON s.id_salle = MOD(planning.id_evenement - 1, 4) + 1
ON DUPLICATE KEY UPDATE
  titre = VALUES(titre),
  type_evenement = VALUES(type_evenement),
  description = VALUES(description),
  date_debut = VALUES(date_debut),
  date_fin = VALUES(date_fin),
  nombre_place = VALUES(nombre_place),
  statut = VALUES(statut);

DELETE FROM derouler WHERE id_evenement BETWEEN 1 AND 21;
INSERT INTO derouler (id_evenement, id_salle)
SELECT e.id_evenement, s.id_salle
FROM evenement e
JOIN salle s ON s.id_salle = MOD(e.id_evenement - 1, 4) + 1
WHERE e.id_evenement BETWEEN 1 AND 21;

-- Insertion jeux
INSERT INTO jeux (titre) VALUES
('Fortnite'),
('League of Legends'),
('Valorant'),
('Minecraft'),
('Apex Legends'),
('FIFA'),
('Call of Duty'),
('Overwatch'),
('Rocket League'),
('PUBG'),
('Counter-Strike 2'),
('Dota 2'),
('Among Us'),
('The Legend of Zelda: Tears of the Kingdom'),
('Elden Ring'),
('Grand Theft Auto V'),
('Red Dead Redemption 2'),
('Cyberpunk 2077'),
('The Witcher 3: Wild Hunt'),
('Rainbow Six Siege'),
('Genshin Impact'),
('Honkai: Star Rail'),
('Starfield'),
('Baldur''s Gate 3'),
('The Sims 4'),
('Animal Crossing: New Horizons'),
('Super Smash Bros. Ultimate'),
('Splatoon 3'),
('Persona 5 Royal'),
('Assassin''s Creed Mirage'),
('Destiny 2'),
('Fall Guys'),
('Terraria'),
('Rust'),
('ARK: Survival Evolved'),
('Palworld'),
('Helldivers 2'),
('No Man''s Sky'),
('Diablo IV'),
('Skyrim'),
('Hogwarts Legacy');

-- Chaque animation reçoit un jeu au hasard, enregistré jusqu'au prochain tirage.
DELETE FROM lier WHERE id_evenement BETWEEN 1 AND 21;
INSERT INTO lier (id_evenement, id_jeu)
SELECT id_evenement, FLOOR(1 + RAND() * 41)
FROM evenement
WHERE id_evenement BETWEEN 1 AND 21;