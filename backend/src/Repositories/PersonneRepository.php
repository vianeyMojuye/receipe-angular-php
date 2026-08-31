<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use PDO;

final class PersonneRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function findAll(): array
    {
        return $this->pdo->query('SELECT id, nom, prenom, email, date_inscription FROM personne ORDER BY nom')->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO personne (nom, prenom, email) VALUES (:nom, :prenom, :email) RETURNING id'
        );
        $stmt->execute([
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'email' => $data['email'],
        ]);

        return (int) $stmt->fetchColumn();
    }
}
