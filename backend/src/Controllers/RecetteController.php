<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Repositories\RecetteRepository;

final class RecetteController
{
    private RecetteRepository $repository;

    public function __construct()
    {
        $this->repository = new RecetteRepository();
    }

    public function index(): void
    {
        $categorieId = isset($_GET['categorie']) ? (int) $_GET['categorie'] : null;
        $personneId = isset($_GET['personne']) ? (int) $_GET['personne'] : null;

        Response::json($this->repository->findAll($categorieId, $personneId));
    }

    public function show(string $id): void
    {
        $recette = $this->repository->find((int) $id);

        if ($recette === null) {
            Response::json(['error' => 'Recette introuvable'], 404);
            return;
        }

        Response::json($recette);
    }

    public function store(): void
    {
        $data = $this->parseBody();

        if (empty($data['titre'])) {
            Response::json(['error' => 'Le champ "titre" est obligatoire'], 422);
            return;
        }

        $id = $this->repository->create($data);
        Response::json($this->repository->find($id), 201);
    }

    public function update(string $id): void
    {
        $data = $this->parseBody();

        if (empty($data['titre'])) {
            Response::json(['error' => 'Le champ "titre" est obligatoire'], 422);
            return;
        }

        $this->repository->update((int) $id, $data);
        Response::json($this->repository->find((int) $id));
    }

    public function destroy(string $id): void
    {
        $this->repository->delete((int) $id);
        Response::json(null, 204);
    }

    private function parseBody(): array
    {
        $raw = file_get_contents('php://input');

        return json_decode($raw, true) ?? [];
    }
}
