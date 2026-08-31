<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use PDO;

final class CategorieRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function findAll(): array
    {
        return $this->pdo->query('SELECT * FROM categorie ORDER BY nom')->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO categorie (nom, description) VALUES (:nom, :description) RETURNING id'
        );
        $stmt->execute([
            'nom' => $data['nom'],
            'description' => $data['description'] ?? null,
        ]);

        return (int) $stmt->fetchColumn();
    }
}
