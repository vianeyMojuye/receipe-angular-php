<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Controllers\CategorieController;
use App\Controllers\PersonneController;
use App\Controllers\RecetteController;
use App\Http\Response;
use App\Http\Router;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$router = new Router();

$recette = new RecetteController();
$categorie = new CategorieController();
$personne = new PersonneController();

$router->get('#^/api/recettes$#', [$recette, 'index']);
$router->get('#^/api/recettes/(\d+)$#', [$recette, 'show']);
$router->post('#^/api/recettes$#', [$recette, 'store']);
$router->put('#^/api/recettes/(\d+)$#', [$recette, 'update']);
$router->delete('#^/api/recettes/(\d+)$#', [$recette, 'destroy']);

$router->get('#^/api/categories$#', [$categorie, 'index']);
$router->post('#^/api/categories$#', [$categorie, 'store']);

$router->get('#^/api/personnes$#', [$personne, 'index']);
$router->post('#^/api/personnes$#', [$personne, 'store']);

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
} catch (\Throwable $e) {
    Response::json(['error' => 'Erreur serveur', 'message' => $e->getMessage()], 500);
}
