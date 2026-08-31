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

## 11. Documenter l'API : OpenAPI + Swagger UI

Deux fichiers statiques dans `public/`, servis tels quels par le serveur PHP intégré (ils ne
passent pas par le routeur — voir §10, le serveur ne route vers `index.php` que si le fichier
demandé n'existe pas physiquement) :

- **`public/openapi.yaml`** : la spécification **OpenAPI 3.0** de l'API — chaque route, ses
  paramètres, ses schémas de requête/réponse, ses codes HTTP. C'est un contrat, pas du code :
  n'importe quel outil (Swagger, Postman, un générateur de client TypeScript…) peut le lire.
- **`public/docs.html`** : une page **Swagger UI** (chargée depuis un CDN, aucune dépendance à
  installer) qui lit `openapi.yaml` et affiche une interface interactive pour explorer et
  tester chaque endpoint directement dans le navigateur.

Accès : http://localhost:8090/docs.html (le spec brut est sur `/openapi.yaml`).

**Point à souligner** : la doc et le code peuvent diverger avec le temps si on oublie de mettre
à jour le YAML après avoir changé un contrôleur — ce n'est pas généré automatiquement à partir
du code ici (contrairement à des libs comme `zircote/swagger-php` qui lisent des annotations).
Pour ce projet pédagogique, le fichier écrit à la main garde les choses simples et lisibles.

## 12. Initialiser un projet de ce genre from scratch

Ce qui suit est la séquence réelle de commandes pour partir d'un **dossier vide** et arriver à
la structure de ce dépôt. Utile si vous démarrez un nouveau projet API PHP sans framework.

### Prérequis

- PHP ≥ 8.2 en ligne de commande (`php -v`)
- [Composer](https://getcomposer.org/) (`composer.phar` ou `composer` dans le PATH)
- Docker Desktop (optionnel mais recommandé — évite d'installer l'extension `pdo_pgsql` en
  local, voir §9)

### Étape 1 — Structure de dossiers

```bash
mkdir -p backend/public backend/src/Config backend/src/Http backend/src/Controllers backend/src/Repositories
cd backend
```

`src/` = code métier (autoloadé par Composer), `public/` = tout ce qui doit être exposé
publiquement par le serveur web — un seul fichier PHP y suffit (`index.php`), le reste
(`src/`) reste inatteignable directement en HTTP.

### Étape 2 — `composer.json` et autoload PSR-4

Écrivez-le directement (plus prévisible pour un cours que l'assistant interactif
`composer init`) :

```json
{
    "name": "votre-org/votre-api",
    "type": "project",
    "require": {
        "php": ">=8.2",
        "ext-pdo": "*",
        "ext-pdo_pgsql": "*"
    },
    "autoload": {
        "psr-4": { "App\\": "src/" }
    }
}
```

```bash
composer install
```

> Si votre PHP local (hors Docker) n'a pas `pdo_pgsql` installé (fréquent sous WAMP/XAMPP),
> `composer install` refusera avec *"the requested PHP extension pdo_pgsql is missing"*.
> C'est normal : l'extension sera présente **dans le conteneur Docker** (§9). Pour générer
> quand même `vendor/autoload.php` en local :
> ```bash
> composer install --ignore-platform-req=ext-pdo_pgsql
> ```

### Étape 3 — Écrire le code, dans cet ordre

L'ordre compte : chaque couche dépend de la précédente, donc les écrire dans ce sens évite les
allers-retours.

1. `src/Config/Database.php` — la connexion (§7). Rien ne fonctionne sans elle.
2. `src/Http/Response.php` — le formateur JSON (§8). Utilisé par tout le reste.
3. `src/Http/Router.php` — le routeur (§4). Ne dépend de rien d'autre ici.
4. `src/Repositories/*.php` — un repository par entité (§6), utilise `Database`.
5. `src/Controllers/*.php` — un contrôleur par entité (§5), utilise son repository + `Response`.
6. `public/index.php` — le front controller (§3) qui assemble tout : instancie le routeur,
   déclare les routes, chaque route pointant vers `[$controller, 'methode']`.

### Étape 4 — Variables d'environnement

Créez `.env.example` (jamais `.env` avec de vraies valeurs — celui-là va dans `.gitignore`) :

```
DB_HOST=db
DB_PORT=5432
DB_NAME=recette_db
DB_USER=recette_user
DB_PASSWORD=recette_pass
```

`Database.php` les lit via `getenv()`, avec des valeurs par défaut de secours pour le
développement local.

### Étape 5 — Conteneuriser (voir GUIDE.md §4 pour le détail)

`Dockerfile` (image, extension `pdo_pgsql`, commande de démarrage) + `docker-compose.yml` à la
racine du dépôt (services `db`, `api`, `web`). `docker compose up --build` construit et démarre
tout.

### Étape 6 — Vérifier avant de documenter

```bash
php -l public/index.php src/**/*.php   # vérifie qu'il n'y a pas d'erreur de syntaxe
curl http://localhost:8090/api/categories
```

### Étape 7 — Documenter l'API

Une fois les routes stabilisées, écrivez `public/openapi.yaml` + `public/docs.html` (§11) —
faites-le en dernier : documenter une API qui bouge encore, c'est de la doc à refaire deux fois.

### Étape 8 — Versionner

```bash
git init
# .gitignore doit exclure vendor/, .env, composer.lock (optionnel), node_modules/, dist/
git add .
git commit -m "Initial commit"
git remote add origin <url-de-votre-depot>
git branch -M master
git push -u origin master
```

## En résumé

Pas de framework, pas de librairie tierce — uniquement PHP 8.3 natif + PDO. La structure imite un pattern **MVC allégé** (Router/Controller/Repository) codé à la main, ce qui est un bon exercice pédagogique pour comprendre ce qu'un framework comme Laravel fait pour toi en coulisses.
