<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Repositories\CategorieRepository;

final class CategorieController
{
    private CategorieRepository $repository;

    public function __construct()
    {
        $this->repository = new CategorieRepository();
    }

    public function index(): void
    {
        Response::json($this->repository->findAll());
    }

    public function store(): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($data['nom'])) {
            Response::json(['error' => 'Le champ "nom" est obligatoire'], 422);
            return;
        }

        $id = $this->repository->create($data);
        Response::json(['id' => $id, 'nom' => $data['nom']], 201);
    }
}
