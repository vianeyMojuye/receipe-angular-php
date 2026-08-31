<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Repositories\PersonneRepository;

final class PersonneController
{
    private PersonneRepository $repository;

    public function __construct()
    {
        $this->repository = new PersonneRepository();
    }

    public function index(): void
    {
        Response::json($this->repository->findAll());
    }

    public function store(): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($data['nom']) || empty($data['prenom']) || empty($data['email'])) {
            Response::json(['error' => 'Les champs "nom", "prenom" et "email" sont obligatoires'], 422);
            return;
        }

        $id = $this->repository->create($data);
        Response::json(['id' => $id] + $data, 201);
    }
}
