# Architecture du backend

Ce document explique pas à pas comment le backend PHP a été construit : le rôle de chaque dossier, fonction et librairie.

## 1. Vue d'ensemble de l'arborescence

```
backend/
├── composer.json          → déclare les dépendances et l'autoload
├── composer.lock          → verrouille les versions exactes installées
├── Dockerfile              → recette pour construire l'image du serveur PHP
├── .env.example            → modèle des variables d'environnement (accès BDD)
├── public/
│   └── index.php           → point d'entrée unique de l'API (front controller)
├── src/
│   ├── Config/Database.php         → connexion PDO à PostgreSQL
│   ├── Http/Router.php             → routeur maison (fait correspondre URL → fonction)
│   ├── Http/Response.php           → formate les réponses JSON
│   ├── Controllers/*.php           → reçoivent la requête, appellent le repository, renvoient une réponse
│   └── Repositories/*.php          → exécutent les requêtes SQL
└── vendor/                 → générée par Composer (autoload), pas écrite à la main
```

C'est une architecture **sans framework** (pas de Symfony/Laravel), mais qui reprend l'esprit MVC en 3 couches : **Router → Controller → Repository**. Chaque couche a une seule responsabilité — c'est le principe de séparation des responsabilités.

## 2. Le point de départ : `composer.json`

```json
"require": {
    "php": ">=8.2",
    "ext-pdo": "*",
    "ext-pdo_pgsql": "*"
},
"autoload": {
    "psr-4": { "App\\": "src/" }
}
```

Deux infos importantes :
- **Aucune librairie externe** n'est utilisée (pas de Slim, pas de Guzzle...). Seules deux extensions PHP natives sont requises : `pdo` (l'interface générique d'accès aux bases de données) et `pdo_pgsql` (le pilote spécifique à PostgreSQL).
- La règle `"App\\": "src/"` dit à Composer : « quand tu vois la classe `App\Controllers\RecetteController`, va la chercher dans `src/Controllers/RecetteController.php` ». C'est l'autoload **PSR-4**, une convention qui remplace les `require`/`include` manuels. `vendor/autoload.php` est le fichier généré par `composer install` qui met en place cette correspondance automatiquement.

## 3. Le point d'entrée : `public/index.php`

C'est le **front controller** : toutes les requêtes HTTP arrivent ici (une seule porte d'entrée, contrairement à un fichier PHP par page).

```php
require dirname(__DIR__) . '/vendor/autoload.php';  // active l'autoload

header('Access-Control-Allow-Origin: *');           // autorise Angular à appeler l'API (CORS)

$router = new Router();
$recette = new RecetteController();

$router->get('#^/api/recettes$#', [$recette, 'index']);
$router->get('#^/api/recettes/(\d+)$#', [$recette, 'show']);
...

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
```

Rôle de chaque bloc :
- Les `header()` CORS : sans ça, le frontend Angular (autre origine) serait bloqué par le navigateur.
- Le bloc `OPTIONS` : le navigateur envoie une requête "preflight" avant certaines requêtes (PUT/DELETE/JSON) ; on répond juste 204 sans traitement.
- On instancie un `Router`, puis chaque `Controller`, puis on **déclare les routes** : une regex d'URL associée à une méthode HTTP et à une fonction du controller à appeler.
- `$router->dispatch(...)` déclenche la recherche de la route correspondante et exécute le bon controller.

## 4. Le routeur maison : `src/Http/Router.php`

```php
public function get(string $pattern, callable $handler): void {
    $this->add('GET', $pattern, $handler);
}

public function dispatch(string $method, string $uri): void {
    $path = parse_url($uri, PHP_URL_PATH) ?? '/';
    foreach ($this->routes as $route) {
        if ($route['method'] !== strtoupper($method)) continue;
        if (preg_match($route['pattern'], $path, $matches) === 1) {
            array_shift($matches);
            ($route['handler'])(...array_values($matches));
            return;
        }
    }
    Response::json(['error' => 'Route non trouvee'], 404);
}
```

C'est un **standardiste téléphonique** : il reçoit l'appel (méthode + URL), regarde sa liste de routes enregistrées, et dès qu'une regex correspond (ex: `#^/api/recettes/(\d+)$#` capture l'id `3` dans `/api/recettes/3`), il transfère l'appel à la bonne fonction du controller — en lui passant l'id capturé comme argument. Si rien ne correspond, il renvoie une erreur 404.

Le `callable` PHP ici est un tableau `[$objet, 'nomMethode']` — c'est la syntaxe PHP pour dire « appelle cette méthode sur cet objet ».

## 5. Les Controllers (`src/Controllers/`)

Rôle : **traduire une requête HTTP en appel métier**, sans jamais toucher au SQL directement. Exemple `RecetteController::store()` :

```php
public function store(): void {
    $data = $this->parseBody();                 // lit le JSON envoyé par Angular

    if (empty($data['titre'])) {                 // validation minimale
        Response::json(['error' => 'Le champ "titre" est obligatoire'], 422);
        return;
    }

    $id = $this->repository->create($data);      // délègue au repository
    Response::json($this->repository->find($id), 201);
}
```

- `parseBody()` lit `php://input` (le corps brut de la requête) et le décode en tableau PHP avec `json_decode`.
- Le controller valide les données d'entrée (ex : titre obligatoire → 422 *Unprocessable Entity*).
- Il ne fait **aucune requête SQL** : il appelle `$this->repository`, qui est injecté dans le constructeur.

## 6. Les Repositories (`src/Repositories/`)

Rôle : **le seul endroit qui parle SQL**. Exemple simplifié de `RecetteRepository::find()` :

```php
public function find(int $id): ?array {
    $stmt = $this->pdo->prepare('SELECT * FROM recette WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $recette = $stmt->fetch();
    if ($recette === false) return null;

    $recette['categories'] = $this->categoriesFor($id);
    $recette['auteurs'] = $this->auteursFor($id);
    return $recette;
}
```

Points clés :
- **`prepare()` + `execute(['id' => $id])`** : ce sont des **requêtes préparées**. Le paramètre `:id` est lié séparément de la requête SQL — cela empêche les injections SQL (jamais de concaténation de variables directement dans le SQL).
- Le repository enrichit la recette avec ses catégories et auteurs via des jointures sur les tables pivot `recette_categorie` et `recette_personne` (relations many-to-many).
- `create()` utilise `RETURNING id`, une syntaxe PostgreSQL qui renvoie l'id généré juste après un `INSERT`.

## 7. La connexion base de données : `src/Config/Database.php`

```php
final class Database {
    private static ?PDO $instance = null;

    public static function connection(): PDO {
        if (self::$instance === null) {
            $dsn = "pgsql:host={$host};port={$port};dbname={$name}";
            self::$instance = new PDO($dsn, $user, $password, [...]);
        }
        return self::$instance;
    }
}
```

C'est un **singleton** : la connexion PDO n'est créée qu'une seule fois par requête HTTP (`self::$instance` mémorise l'objet), même si plusieurs repositories la demandent. Les identifiants viennent des variables d'environnement (`getenv('DB_HOST')`...), définies via `.env.example`/Docker — jamais codées en dur, pour pouvoir changer d'environnement (local/prod) sans toucher au code.

`PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION` : si une requête SQL échoue, PDO lève une exception au lieu de renvoyer silencieusement `false` — plus facile à détecter.

## 8. Le formatage des réponses : `src/Http/Response.php`

```php
public static function json(mixed $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    if ($data !== null) {
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
```

Une méthode statique unique, appelée partout, pour garantir que **toute réponse de l'API a le même format** : bon code HTTP, bon header, JSON propre (`JSON_UNESCAPED_UNICODE` évite que "café" devienne `café`).

## 9. Le déploiement : `Dockerfile`

```dockerfile
FROM php:8.3-cli
RUN apt-get install libpq-dev && docker-php-ext-install pdo pdo_pgsql
WORKDIR /app
COPY . /app
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
```

Pas besoin d'Apache/Nginx : PHP a un **serveur de développement intégré** (`php -S`), lancé ici en pointant sur le dossier `public/` comme racine web — donc seul `index.php` est exposé, tout le code métier dans `src/` reste inaccessible directement.

## 10. Le trajet complet d'une requête (exemple concret)

Angular fait `GET /api/recettes/3` :

1. `public/index.php` reçoit la requête, instancie `Router` + `RecetteController`.
2. `Router::dispatch('GET', '/api/recettes/3')` teste chaque route, trouve `#^/api/recettes/(\d+)$#`, capture `3`.
3. Il appelle `$recette->show('3')`.
4. `RecetteController::show('3')` appelle `$this->repository->find(3)`.
5. `RecetteRepository::find(3)` exécute le SQL via PDO, récupère la ligne + catégories + auteurs.
6. Le controller passe le résultat à `Response::json(...)`.
7. Le client Angular reçoit un JSON propre avec le bon code HTTP.

## En résumé

Pas de framework, pas de librairie tierce — uniquement PHP 8.3 natif + PDO. La structure imite un pattern **MVC allégé** (Router/Controller/Repository) codé à la main, ce qui est un bon exercice pédagogique pour comprendre ce qu'un framework comme Laravel fait pour toi en coulisses.
