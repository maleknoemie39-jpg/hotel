-- Migration des anciennes tables vers le nouveau MCD.
-- Faire une sauvegarde de la base avant de lancer ce fichier.

CREATE TABLE admin (
    id_utilisateur INT NOT NULL,
    PRIMARY KEY (id_utilisateur),
    CONSTRAINT fk_admin_utilisateur_mcd
        FOREIGN KEY (id_utilisateur) REFERENCES Utilisateur(id_utilisateur)
) ENGINE=InnoDB;

INSERT INTO admin (id_utilisateur)
SELECT id_utilisateur
FROM Utilisateur
WHERE role_utilisateur = 'ADMIN';

CREATE TABLE gerer_nouveau (
    id_utilisateur INT NOT NULL,
    id_evenement INT NOT NULL,
    PRIMARY KEY (id_utilisateur, id_evenement),
    CONSTRAINT fk_gerer_admin_mcd
        FOREIGN KEY (id_utilisateur) REFERENCES admin(id_utilisateur),
    CONSTRAINT fk_gerer_evenement_mcd
        FOREIGN KEY (id_evenement) REFERENCES evenement(id_evenement)
) ENGINE=InnoDB;

-- Les anciens événements sont rattachés aux administrateurs existants.
INSERT INTO gerer_nouveau (id_utilisateur, id_evenement)
SELECT a.id_utilisateur, e.id_evenement
FROM admin a
CROSS JOIN evenement e;

CREATE TABLE inscrire_nouveau (
    id_utilisateur INT NOT NULL,
    id_evenement INT NOT NULL,
    date_inscription DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    statut VARCHAR(20) NOT NULL DEFAULT 'CONFIRMEE',
    place_attribue VARCHAR(20) NOT NULL DEFAULT '1',
    PRIMARY KEY (id_utilisateur, id_evenement),
    CONSTRAINT fk_inscrire_utilisateur_mcd
        FOREIGN KEY (id_utilisateur) REFERENCES Utilisateur(id_utilisateur),
    CONSTRAINT fk_inscrire_evenement_mcd
        FOREIGN KEY (id_evenement) REFERENCES evenement(id_evenement)
) ENGINE=InnoDB;

-- Copier les anciennes réservations dans la nouvelle relation directe.
INSERT INTO inscrire_nouveau (
    id_utilisateur, id_evenement, date_inscription, statut, place_attribue
)
SELECT u.id_utilisateur, c.id_evenement, i.date_inscription, i.statut, i.place_attribue
FROM inscrire u
JOIN Inscription i ON i.id_inscription = u.id_inscription
JOIN concerne c ON c.id_inscription = i.id_inscription;

-- Remplacer les anciennes tables de réservation par la nouvelle table.
RENAME TABLE
    inscrire TO inscrire_ancienne,
    inscrire_nouveau TO inscrire,
    gerer_nouveau TO gerer;

DROP TABLE concerne;
DROP TABLE inscrire_ancienne;
DROP TABLE Inscription;

ALTER TABLE Utilisateur DROP COLUMN role_utilisateur;
