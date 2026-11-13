# Arborescence du projet

Chaque dossier et fichier du projet, avec son rôle et son origine. La colonne « Origine » indique qui l'a créé : **écrit** veut dire écrit pour ce projet, **recette** veut dire créé par une [recette Flex](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/02-composer-et-flex.md), **généré** veut dire créé par un outil.

```text
Debutant-Nv3/
├── .github/workflows/    Intégration continue
├── bin/                  Exécutables du projet
├── config/               Configuration de Symfony
├── docs/                 Documentation de formation
├── migrations/           Migrations de la base de données
├── public/               Seul dossier exposé au web
├── src/                  Code PHP de l'application
├── templates/            Templates Twig
├── tests/                Tests automatisés
├── var/                  Cache, journaux et base de test (non versionné)
└── vendor/               Dépendances (non versionné)
```

## Racine

| Fichier | Rôle | Origine |
|---|---|---|
| [README.md](../README.md) | Présentation du niveau et démarrage | Écrit |
| [AGENTS.md](../AGENTS.md) | Consignes pour les assistants de code | Recette |
| [LICENSE](../LICENSE) | Licence MIT | Écrit |
| [composer.json](../composer.json) | Les dépendances demandées | Généré, puis complété |
| [composer.lock](../composer.lock) | Les versions exactes installées | Généré par Composer |
| [symfony.lock](../symfony.lock) | Les recettes appliquées | Généré par Flex |
| [Dockerfile](../Dockerfile) | La recette de l'image Docker, voir [docker.md](docker.md) | Écrit |
| [compose.yaml](../compose.yaml) | Le lancement des services `app` et `database` | Recette pour `database`, écrit pour `app` |
| [compose.override.yaml](../compose.override.yaml) | Le port de la base, publié sur votre machine seulement | Recette, puis modifié |
| [.dockerignore](../.dockerignore) | Ce qui n'entre pas dans l'image | Écrit |
| [.env](../.env) | Les variables par défaut, dont `DATABASE_URL` | Recette |
| [.env.dev](../.env.dev) | Les variables de `dev` | Recette |
| [.env.test](../.env.test) | Les variables de `test` | Recette |
| [.gitignore](../.gitignore) | Ce que Git ne suit pas | Recette, puis complété |
| [.editorconfig](../.editorconfig) | Le format des fichiers pour les éditeurs | Recette |
| [phpunit.dist.xml](../phpunit.dist.xml) | La configuration de PHPUnit, dont la base SQLite des tests | Recette, puis complété |

Les fichiers `.env.local`, `.env.local.php` et `.env.*.local` sont personnels : ils sont ignorés par Git et absents de l'image, mais visibles du conteneur lancé (voir [docker.md](docker.md)). Le projet n'en a pas besoin. Un fichier `phpunit.xml` à la racine, lui aussi ignoré, remplacerait `phpunit.dist.xml` sur votre machine : [la fiche sur les tests](formations/07-tests-avec-base-de-donnees.md) s'en sert pour une expérience. `.DS_Store` est un fichier technique de macOS, ignoré.

## `.github/workflows/`

| Fichier | Rôle |
|---|---|
| [ci.yml](../.github/workflows/ci.yml) | La CI GitHub Actions, voir [ci-cd.md](ci-cd.md) |

## `bin/`

| Fichier | Rôle | Origine |
|---|---|---|
| [console](../bin/console) | Point d'entrée des commandes Symfony | Recette |
| [phpunit](../bin/phpunit) | Lanceur des tests | Recette |

## `config/`

Pour les fichiers communs à tous les projets, voir [la fiche du premier niveau](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/06-configuration-et-environnements.md).

| Fichier | Rôle | Origine |
|---|---|---|
| [bundles.php](../config/bundles.php) | Les bundles actifs par environnement, dont Doctrine et les migrations | Flex |
| [routes.yaml](../config/routes.yaml) | Le chargement des routes par attributs, et la redirection de `/` | Recette, puis complété |
| [routes/framework.yaml](../config/routes/framework.yaml) | La route d'aperçu des erreurs, en `dev` | Recette |
| [services.yaml](../config/services.yaml) | La déclaration des classes de `src/` comme services | Recette |
| [preload.php](../config/preload.php) | Une optimisation de `prod`, inutilisée ici | Recette |
| [reference.php](../config/reference.php) | La description des options, à ne pas modifier | Généré |
| [packages/cache.yaml](../config/packages/cache.yaml) | Le cache applicatif, sans réglage actif | Recette |
| [packages/csrf.yaml](../config/packages/csrf.yaml) | La protection CSRF sans état des formulaires | Recette |
| [packages/doctrine.yaml](../config/packages/doctrine.yaml) | La connexion, le mapping des entités et le suffixe `_test` | Recette |
| [packages/doctrine_migrations.yaml](../config/packages/doctrine_migrations.yaml) | Le dossier des migrations | Recette |
| [packages/framework.yaml](../config/packages/framework.yaml) | Le secret, la session et le mode test | Recette |
| [packages/property_info.yaml](../config/packages/property_info.yaml) | La lecture des types de propriétés, arrivée avec Form | Recette |
| [packages/routing.yaml](../config/packages/routing.yaml) | Les réglages du routeur | Recette |
| [packages/twig.yaml](../config/packages/twig.yaml) | Les réglages de Twig, dont `strict_variables` sous `when@test` | Recette |
| [packages/validator.yaml](../config/packages/validator.yaml) | Les réglages du Validator | Recette |

## `docs/`

| Fichier | Rôle |
|---|---|
| [arborescence.md](arborescence.md) | Ce document |
| [versions.md](versions.md) | Les versions de PHP, Symfony, FrankenPHP, PostgreSQL et des paquets |
| [base-de-donnees.md](base-de-donnees.md) | La table `note` et les commandes pour inspecter la base |
| [docker.md](docker.md) | Docker expliqué ligne par ligne |
| [ci-cd.md](ci-cd.md) | La CI expliquée étape par étape |
| [formations/index.md](formations/index.md) | L'ordre de lecture et le parcours du code |
| [formations/01-orm-et-entites.md](formations/01-orm-et-entites.md) | L'ORM et l'entité `Note` |
| [formations/02-connexion-et-postgresql.md](formations/02-connexion-et-postgresql.md) | `DATABASE_URL` et PostgreSQL |
| [formations/03-migrations.md](formations/03-migrations.md) | Les migrations |
| [formations/04-entity-manager-et-repository.md](formations/04-entity-manager-et-repository.md) | L'`EntityManager` et le dépôt |
| [formations/05-crud-genere.md](formations/05-crud-genere.md) | Le CRUD généré par `make:crud` |
| [formations/06-validation-et-csrf.md](formations/06-validation-et-csrf.md) | La validation, le CSRF et l'échappement |
| [formations/07-tests-avec-base-de-donnees.md](formations/07-tests-avec-base-de-donnees.md) | Les tests avec une base de données |

## `migrations/`

| Fichier | Rôle | Origine |
|---|---|---|
| [Version20261113114813.php](../migrations/Version20261113114813.php) | La création de la table `note` | `make:migration` |
| [.gitignore](../migrations/.gitignore) | Un fichier vide, qui fait exister le dossier dans Git avant la première migration | Recette |

## `public/`

| Fichier | Rôle | Origine |
|---|---|---|
| [index.php](../public/index.php) | Le contrôleur frontal, voir [la fiche HTTP](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/01-http-et-requete.md) | Recette |
| [css/app.css](../public/css/app.css) | La feuille de style du site | Écrit |

## `src/`

| Fichier | Rôle | Origine |
|---|---|---|
| [Kernel.php](../src/Kernel.php) | Le cœur de l'application | Recette |
| [Controller/NoteController.php](../src/Controller/NoteController.php) | Les cinq actions du CRUD | `make:crud`, puis modifié |
| [Entity/Note.php](../src/Entity/Note.php) | L'entité, son mapping, ses contraintes et ses rappels | `make:entity`, puis complété |
| [Form/NoteType.php](../src/Form/NoteType.php) | Le formulaire d'une note | `make:crud`, puis modifié |
| [Repository/NoteRepository.php](../src/Repository/NoteRepository.php) | Le dépôt, avec `findAllNewestFirst()` | `make:entity`, puis complété |
| [Controller/.gitignore](../src/Controller/.gitignore), [Entity/.gitignore](../src/Entity/.gitignore), [Repository/.gitignore](../src/Repository/.gitignore) | Des fichiers vides, qui font exister les dossiers dans Git | Recette |

## `templates/`

| Fichier | Rôle | Origine |
|---|---|---|
| [base.html.twig](../templates/base.html.twig) | Le squelette de la page et les messages flash | Recette, puis réécrit |
| [note/index.html.twig](../templates/note/index.html.twig) | La liste des notes | `make:crud`, puis réécrit |
| [note/show.html.twig](../templates/note/show.html.twig) | Le détail d'une note | `make:crud`, puis réécrit |
| [note/new.html.twig](../templates/note/new.html.twig) | La création | `make:crud`, puis traduit |
| [note/edit.html.twig](../templates/note/edit.html.twig) | La modification | `make:crud`, puis traduit |
| [note/_form.html.twig](../templates/note/_form.html.twig) | Le formulaire, partagé | `make:crud`, puis traduit |
| [note/_delete_form.html.twig](../templates/note/_delete_form.html.twig) | Le bouton de suppression et son jeton | `make:crud`, puis traduit |

## `tests/`

| Fichier | Rôle | Origine |
|---|---|---|
| [bootstrap.php](../tests/bootstrap.php) | Le démarrage des tests et la lecture des `.env` | Recette |
| [ResetDatabaseTrait.php](../tests/ResetDatabaseTrait.php) | La remise à zéro du schéma avant chaque test | Écrit |
| [Config/DatabaseConfigTest.php](../tests/Config/DatabaseConfigTest.php) | Le garde-fou : SQLite en test, `serverVersion` égal à l'image | `make:test`, puis complété |
| [Controller/NoteControllerTest.php](../tests/Controller/NoteControllerTest.php) | Les tests des pages | `make:crud`, puis réécrit |
| [Entity/NoteValidationTest.php](../tests/Entity/NoteValidationTest.php) | Les tests des contraintes | `make:test`, puis complété |
| [Repository/NoteRepositoryTest.php](../tests/Repository/NoteRepositoryTest.php) | Les tests du dépôt et des rappels | `make:test`, puis complété |
| [Docs/DocsConsistencyTest.php](../tests/Docs/DocsConsistencyTest.php) | Les tests de cohérence de la documentation | Repris de Debutant-Nv2, puis complété |

## Dossiers générés

Ces dossiers n'existent pas sur GitHub : ils sont recréés sur chaque machine et ignorés par Git.

| Dossier ou fichier | Contenu | Recréé par |
|---|---|---|
| `vendor/` | Le code des dépendances | `composer install` |
| `var/cache/` | La configuration compilée, dont le conteneur, par environnement | Symfony, à la première requête |
| `var/log/` | Les journaux, s'il y en a | Symfony |
| `var/test.db` | La base SQLite des tests | Le premier test qui touche la base |
| `.phpunit.cache/` | Le cache de PHPUnit | PHPUnit |
| `public/bundles/` | Les fichiers publics des bundles, s'il y en a | `assets:install` |

Les données de PostgreSQL ne sont pas dans le projet : elles vivent dans le volume Docker `database_data`, voir [docker.md](docker.md).
