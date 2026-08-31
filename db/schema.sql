-- ============================================================================
-- Gestion de recettes — Schéma physique PostgreSQL (issu du MCD)
-- Entités        : personne, recette, categorie, ingredient
-- Associations N:N : recette_personne, recette_categorie, recette_ingredient
-- ============================================================================

CREATE TABLE IF NOT EXISTS personne (
    id               SERIAL PRIMARY KEY,
    nom              VARCHAR(100) NOT NULL,
    prenom           VARCHAR(100) NOT NULL,
    email            VARCHAR(150) NOT NULL UNIQUE,
    date_inscription TIMESTAMP NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS categorie (
    id          SERIAL PRIMARY KEY,
    nom         VARCHAR(80) NOT NULL UNIQUE,
    description TEXT
);

CREATE TABLE IF NOT EXISTS ingredient (
    id           SERIAL PRIMARY KEY,
    nom          VARCHAR(100) NOT NULL UNIQUE,
    unite_mesure VARCHAR(20)
);

CREATE TABLE IF NOT EXISTS recette (
    id                SERIAL PRIMARY KEY,
    titre             VARCHAR(150) NOT NULL,
    description       TEXT,
    instructions      TEXT,
    temps_preparation SMALLINT,
    temps_cuisson     SMALLINT,
    difficulte        VARCHAR(20) NOT NULL DEFAULT 'facile'
                       CHECK (difficulte IN ('facile', 'moyen', 'difficile')),
    nb_portions       SMALLINT NOT NULL DEFAULT 4,
    date_creation     TIMESTAMP NOT NULL DEFAULT now(),
    date_modification TIMESTAMP NOT NULL DEFAULT now()
);

-- Association N:N : une recette a plusieurs créateurs, une personne co-crée plusieurs recettes
CREATE TABLE IF NOT EXISTS recette_personne (
    recette_id  INTEGER NOT NULL REFERENCES recette(id) ON DELETE CASCADE,
    personne_id INTEGER NOT NULL REFERENCES personne(id) ON DELETE CASCADE,
    role        VARCHAR(30) NOT NULL DEFAULT 'auteur',
    PRIMARY KEY (recette_id, personne_id)
);

-- Association N:N : une recette appartient à plusieurs catégories
CREATE TABLE IF NOT EXISTS recette_categorie (
    recette_id   INTEGER NOT NULL REFERENCES recette(id) ON DELETE CASCADE,
    categorie_id INTEGER NOT NULL REFERENCES categorie(id) ON DELETE CASCADE,
    PRIMARY KEY (recette_id, categorie_id)
);

-- Association N:N porteuse d'attributs (quantité/unité propres à chaque recette)
CREATE TABLE IF NOT EXISTS recette_ingredient (
    recette_id    INTEGER NOT NULL REFERENCES recette(id) ON DELETE CASCADE,
    ingredient_id INTEGER NOT NULL REFERENCES ingredient(id) ON DELETE CASCADE,
    quantite      NUMERIC(6,2) NOT NULL,
    unite         VARCHAR(20),
    PRIMARY KEY (recette_id, ingredient_id)
);

CREATE INDEX IF NOT EXISTS idx_recette_titre             ON recette (titre);
CREATE INDEX IF NOT EXISTS idx_recette_personne_personne  ON recette_personne (personne_id);
CREATE INDEX IF NOT EXISTS idx_recette_categorie_categorie ON recette_categorie (categorie_id);

-- Données de démonstration
INSERT INTO personne (nom, prenom, email) VALUES
    ('Toukam', 'Vianey', 'vianey@example.com'),
    ('Dupont', 'Claire', 'claire@example.com')
ON CONFLICT DO NOTHING;

INSERT INTO categorie (nom, description) VALUES
    ('Entrée', 'Plats servis en début de repas'),
    ('Dessert', 'Plats sucrés de fin de repas'),
    ('Végétarien', 'Recettes sans viande ni poisson')
ON CONFLICT DO NOTHING;
