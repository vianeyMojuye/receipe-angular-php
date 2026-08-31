<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use PDO;

final class RecetteRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function findAll(?int $categorieId = null, ?int $personneId = null): array
    {
        $sql = 'SELECT DISTINCT r.* FROM recette r';
        $conditions = [];
        $params = [];

        if ($categorieId !== null) {
            $sql .= ' JOIN recette_categorie rc ON rc.recette_id = r.id';
            $conditions[] = 'rc.categorie_id = :categorie_id';
            $params['categorie_id'] = $categorieId;
        }

        if ($personneId !== null) {
            $sql .= ' JOIN recette_personne rp ON rp.recette_id = r.id';
            $conditions[] = 'rp.personne_id = :personne_id';
            $params['personne_id'] = $personneId;
        }

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY r.date_creation DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM recette WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $recette = $stmt->fetch();

        if ($recette === false) {
            return null;
        }

        $recette['categories'] = $this->categoriesFor($id);
        $recette['auteurs'] = $this->auteursFor($id);

        return $recette;
    }

    private function categoriesFor(int $recetteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.nom FROM categorie c
             JOIN recette_categorie rc ON rc.categorie_id = c.id
             WHERE rc.recette_id = :id'
        );
        $stmt->execute(['id' => $recetteId]);

        return $stmt->fetchAll();
    }

    private function auteursFor(int $recetteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.id, p.nom, p.prenom, rp.role FROM personne p
             JOIN recette_personne rp ON rp.personne_id = p.id
             WHERE rp.recette_id = :id'
        );
        $stmt->execute(['id' => $recetteId]);

        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO recette (titre, description, instructions, temps_preparation, temps_cuisson, difficulte, nb_portions)
             VALUES (:titre, :description, :instructions, :temps_preparation, :temps_cuisson, :difficulte, :nb_portions)
             RETURNING id'
        );
        $stmt->execute([
            'titre' => $data['titre'],
            'description' => $data['description'] ?? null,
            'instructions' => $data['instructions'] ?? null,
            'temps_preparation' => $data['temps_preparation'] ?? null,
            'temps_cuisson' => $data['temps_cuisson'] ?? null,
            'difficulte' => $data['difficulte'] ?? 'facile',
            'nb_portions' => $data['nb_portions'] ?? 4,
        ]);

        $id = (int) $stmt->fetchColumn();

        foreach ($data['personnes'] ?? [] as $personneId) {
            $this->attachPersonne($id, (int) $personneId);
        }

        foreach ($data['categories'] ?? [] as $categorieId) {
            $this->attachCategorie($id, (int) $categorieId);
        }

        return $id;
    }

    public function attachPersonne(int $recetteId, int $personneId, string $role = 'auteur'): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO recette_personne (recette_id, personne_id, role) VALUES (:r, :p, :role)
             ON CONFLICT (recette_id, personne_id) DO NOTHING'
        );
        $stmt->execute(['r' => $recetteId, 'p' => $personneId, 'role' => $role]);
    }

    public function attachCategorie(int $recetteId, int $categorieId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO recette_categorie (recette_id, categorie_id) VALUES (:r, :c)
             ON CONFLICT (recette_id, categorie_id) DO NOTHING'
        );
        $stmt->execute(['r' => $recetteId, 'c' => $categorieId]);
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE recette SET titre = :titre, description = :description, instructions = :instructions,
             temps_preparation = :temps_preparation, temps_cuisson = :temps_cuisson,
             difficulte = :difficulte, nb_portions = :nb_portions, date_modification = now()
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $id,
            'titre' => $data['titre'],
            'description' => $data['description'] ?? null,
            'instructions' => $data['instructions'] ?? null,
            'temps_preparation' => $data['temps_preparation'] ?? null,
            'temps_cuisson' => $data['temps_cuisson'] ?? null,
            'difficulte' => $data['difficulte'] ?? 'facile',
            'nb_portions' => $data['nb_portions'] ?? 4,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM recette WHERE id = :id');

        return $stmt->execute(['id' => $id]);
    }
}
