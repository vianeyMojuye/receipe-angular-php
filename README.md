# Recette — projet pédagogique full-stack

Application de gestion de recettes de cuisine, utilisée comme support de cours pour un module full-stack :

- **Base de données** : PostgreSQL
- **API** : PHP 8.3 (sans framework, PDO natif) — voir [`backend/`](./backend)
- **Front-end** : Angular v21 (standalone components, signals) — voir [`frontend/`](./frontend)
- **Orchestration** : Docker Compose (un conteneur par couche : `db`, `api`, `web`)

Règles métier illustrées :
- une recette peut être **co-créée par plusieurs personnes** (association N:N `recette_personne`)
- une recette peut appartenir à **plusieurs catégories** (association N:N `recette_categorie`)
- une recette est composée de plusieurs ingrédients, avec une quantité propre à chaque recette (association N:N porteuse d'attributs `recette_ingredient`)

📖 **Le guide pédagogique complet (MCD → schéma physique → API PHP → maquette Angular → Docker) est dans [`docs/GUIDE.md`](./docs/GUIDE.md).**

## Démarrage rapide

```bash
docker compose up --build
```

- Front-end : http://localhost:4300
- API : http://localhost:8090/api/recettes
- Documentation API (Swagger UI) : http://localhost:8090/docs.html
- Base de données : `localhost:5432` (user `recette_user` / db `recette_db`)

> Ports choisis pour éviter les conflits avec d'autres projets locaux (ex. un `ng serve` déjà sur
> le 4200). Changez-les dans `docker-compose.yml` si besoin.

Le schéma SQL (`db/schema.sql`) est exécuté automatiquement au premier démarrage du conteneur `db`.

## Structure du dépôt

```
.
├── db/               # MCD → schéma physique PostgreSQL (DDL + données de démo)
├── backend/           # API REST PHP 8.3 (PDO, routeur maison, contrôleurs, repositories)
├── frontend/           # Application Angular v21 (maquette : liste, détail, formulaire)
├── docker-compose.yml  # Orchestration des 3 services (db, api, web)
└── docs/GUIDE.md        # Guide pédagogique étape par étape
```
