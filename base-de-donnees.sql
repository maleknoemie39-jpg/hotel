CREATE TABLE Utilisateur(
   id_utilisateur INT,
   nom VARCHAR(50) ,
   prenom VARCHAR(50) ,
   email VARCHAR(50) ,
   mot_de_passe VARCHAR(50) ,
   rôle_utilisateur VARCHAR(50) ,
   PRIMARY KEY(id_utilisateur)
);

CREATE TABLE Inscription(
   id_inscription INT,
   date_inscription DATE,
   statut VARCHAR(50) ,
   place_attribue VARCHAR(50) ,
   PRIMARY KEY(id_inscription)
);

CREATE TABLE evenement(
   id_evenement INT,
   titre VARCHAR(50) ,
   type_evenement VARCHAR(50) ,
   description VARCHAR(50) ,
   date_debut DATE,
   date_fin DATE,
   nombre_place INT,
   statut VARCHAR(50) ,
   date_creation DATE,
   PRIMARY KEY(id_evenement)
);

CREATE TABLE jeux(
   id_jeu INT,
   titre VARCHAR(50) ,
   plateforme VARCHAR(50) ,
   genre VARCHAR(50) ,
   description VARCHAR(50) ,
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

CREATE TABLE Inscrire(
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


INSERT INTO salle (nom, capacite, description) VALUES
  ('Salle Arcade', 20, 'Consoles et jeux rétro'),
  ('Espace Gaming', 24, 'Espace tournois et jeux en équipe'),
  ('Salon VR', 8, 'Expériences de réalité virtuelle'),
  ('Salle Pixel', 16, 'Ateliers et découvertes')
ON DUPLICATE KEY UPDATE capacite = VALUES(capacite);
