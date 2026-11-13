# Guide de formation : Debutant Nv3

Ce guide accompagne la lecture du code. Vous n'avez rien à écrire : ouvrez chaque fiche à côté des fichiers qu'elle cite et suivez les liens.

## Ce que vous saurez expliquer à la fin

- Ce qu'est un ORM, et comment une classe PHP devient une table.
- Comment Symfony trouve la base de données, dans un conteneur comme sur votre machine.
- Pourquoi on modifie une base avec des migrations, et pourquoi une migration vaut pour un seul moteur.
- Qui enregistre, qui lit, et à quel moment Doctrine envoie vraiment le SQL.
- Ce que génère `make:crud`, et ce qu'il faut changer ensuite.
- Comment une saisie est validée, comment une requête prouve qu'elle vient du site, et pourquoi un `<script>` saisi ne s'exécute pas.
- Comment des tests écrivent en base sans jamais toucher vos données, et pourquoi une vraie variable d'environnement a failli les envoyer sur PostgreSQL.

## Ordre de lecture

Chaque fiche suit le même plan : ce que c'est, pourquoi ça existe, où le voir dans ce projet, comment lire ce code, les commandes officielles utilisées, à retenir.

| Ordre | Fiche | Question à laquelle elle répond |
|---|---|---|
| 1 | [L'ORM et les entités](01-orm-et-entites.md) | Comment un objet PHP devient-il une ligne de table ? |
| 2 | [La connexion et PostgreSQL](02-connexion-et-postgresql.md) | Où est la base, et comment Symfony la trouve-t-il ? |
| 3 | [Les migrations](03-migrations.md) | Comment la table est-elle créée, partout pareil ? |
| 4 | [L'EntityManager et le dépôt](04-entity-manager-et-repository.md) | Qui écrit, qui lit, et quand ? |
| 5 | [Le CRUD généré](05-crud-genere.md) | Que fait `make:crud`, et qu'a-t-on changé ? |
| 6 | [Validation, CSRF et échappement](06-validation-et-csrf.md) | Comment se protège-t-on d'une saisie fausse ou piégée ? |
| 7 | [Les tests avec une base de données](07-tests-avec-base-de-donnees.md) | Comment tester sans abîmer ses données ? |

La fiche 7 contient la meilleure leçon de ce niveau : un même fichier de configuration qui donnait deux résultats selon la machine. Ne la sautez pas.

Puis trois documents de référence, à lire une fois et à rouvrir au besoin :

- [La base de données](../base-de-donnees.md) : la table `note` en diagramme, et les commandes pour l'inspecter.
- [Docker](../docker.md) : comment le site et PostgreSQL tournent dans deux conteneurs.
- [CI/CD](../ci-cd.md) : ce que GitHub vérifie à chaque changement.

Deux autres servent de carte : [l'arborescence](../arborescence.md) et [les versions](../versions.md).

## Déjà vu aux niveaux précédents

Ces sujets ne sont pas réexpliqués ici. Rouvrez la fiche correspondante si un point vous échappe.

| Sujet | Fiche | Ce qui sert dans ce niveau |
|---|---|---|
| HTTP et la requête | [Nv1 : 01-http-et-requete.md](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/01-http-et-requete.md) | Le contrôleur frontal, les méthodes HTTP, les statuts 404 et 405 |
| Composer et Flex | [Nv1 : 02-composer-et-flex.md](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/02-composer-et-flex.md) | Les recettes, qui ont écrit `doctrine.yaml`, `compose.yaml` et `.env` |
| Le routage | [Nv1 : 03-routage.md](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/03-routage.md) | `#[Route]`, un paramètre de route et son exigence, `path()`, la réponse 405 |
| Les contrôleurs | [Nv1 : 04-controleurs.md](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/04-controleurs.md) | `AbstractController`, `render()`, le cas « introuvable » |
| Twig | [Nv1 : 05-twig.md](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/05-twig.md) | L'héritage, l'inclusion, les boucles, l'échappement |
| Configuration et environnements | [Nv1 : 06-configuration-et-environnements.md](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/06-configuration-et-environnements.md) | `APP_ENV`, l'ordre des fichiers `.env`, `when@test` |
| Les tests fonctionnels | [Nv1 : 07-tests.md](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/07-tests.md) | `WebTestCase`, les assertions, les fournisseurs de données |
| Git | [Nv1 : 08-git.md](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/08-git.md) | Les commits, les branches, `.gitignore` et les dossiers gardés vides |
| Les services | [Nv2 : 01-services.md](https://github.com/baptistedev-fullstack/symfony-debutant-nv2/blob/main/docs/formations/01-services.md) | Le dépôt et l'`EntityManager` sont des services |
| L'injection de dépendances | [Nv2 : 02-injection-de-dependances.md](https://github.com/baptistedev-fullstack/symfony-debutant-nv2/blob/main/docs/formations/02-injection-de-dependances.md) | `NoteRepository` et `EntityManagerInterface` reçus en arguments |
| La configuration | [Nv2 : 04-configuration.md](https://github.com/baptistedev-fullstack/symfony-debutant-nv2/blob/main/docs/formations/04-configuration.md) | Les variables d'environnement, dont `DATABASE_URL` |
| Le formulaire GET | [Nv2 : 05-formulaire-get-et-parametres.md](https://github.com/baptistedev-fullstack/symfony-debutant-nv2/blob/main/docs/formations/05-formulaire-get-et-parametres.md) | Pourquoi un formulaire qui modifie des données passe en `POST` |
| Exceptions et erreurs | [Nv2 : 06-exceptions-et-erreurs.md](https://github.com/baptistedev-fullstack/symfony-debutant-nv2/blob/main/docs/formations/06-exceptions-et-erreurs.md) | Qui produit une 404, et pourquoi une 500 est un bogue |
| Tests unitaires et doublures | [Nv2 : 07-tests-unitaires-et-doublures.md](https://github.com/baptistedev-fullstack/symfony-debutant-nv2/blob/main/docs/formations/07-tests-unitaires-et-doublures.md) | `KernelTestCase`, `setUp()`, le conteneur de test |

Ce niveau va plus loin sur trois de ces sujets : les variables d'environnement, avec `DATABASE_URL` et le piège de `$_ENV` ; les formulaires, avec le composant Form en aperçu ; les tests, avec une base à remettre à zéro.

## Parcours du code conseillé

Suivez une vraie requête, l'envoi du formulaire de création `POST /note/new`, fichier par fichier, jusqu'à la ligne en base, puis jusqu'à la page suivante.

| Étape | Fichier | Ce qu'il faut y regarder |
|---|---|---|
| 1 | [public/index.php](../../public/index.php) | Le point d'entrée unique : il crée le Kernel |
| 2 | [config/routes.yaml](../../config/routes.yaml) | `controllers` charge les routes écrites en attributs dans `src/Controller/` |
| 3 | [src/Controller/NoteController.php](../../src/Controller/NoteController.php) | Le préfixe `#[Route('/note')]`, puis la route `app_note_new`, qui accepte `POST` ; Symfony fournit `$request` et `$entityManager` |
| 4 | [src/Form/NoteType.php](../../src/Form/NoteType.php) | `createForm()` construit le formulaire : les champs `title` et `content`, liés à `Note` par `data_class` |
| 5 | [config/packages/csrf.yaml](../../config/packages/csrf.yaml) | `handleRequest()` lit `note[title]`, `note[content]` et `note[_token]` ; le jeton `submit` est validé par l'origine de la requête |
| 6 | [src/Entity/Note.php](../../src/Entity/Note.php) | `setTitle()` et `setContent()` reçoivent la saisie ; `isValid()` applique les quatre contraintes `#[Assert\...]` |
| 7 | [src/Controller/NoteController.php](../../src/Controller/NoteController.php) | `persist($note)` : aucune requête encore, mais `onPrePersist()` remplit `createdAt` |
| 8 | [src/Entity/Note.php](../../src/Entity/Note.php) | Les attributs `#[ORM\...]` disent à Doctrine dans quelles colonnes écrire |
| 9 | [config/packages/doctrine.yaml](../../config/packages/doctrine.yaml) | `flush()` ouvre la connexion décrite par `DATABASE_URL` |
| 10 | [compose.yaml](../../compose.yaml) | Dans le conteneur, `DATABASE_URL` désigne le service `database`, PostgreSQL 16 |
| 11 | [migrations/Version20261113114813.php](../../migrations/Version20261113114813.php) | La table `note` qui reçoit l'`INSERT` ; PostgreSQL attribue l'`id` |
| 12 | [config/packages/framework.yaml](../../config/packages/framework.yaml) | `addFlash()` range `Note créée.` dans la session |
| 13 | [src/Controller/NoteController.php](../../src/Controller/NoteController.php) | `redirectToRoute()` répond 303 vers `/note` ; le navigateur suit avec un `GET` |
| 14 | [src/Repository/NoteRepository.php](../../src/Repository/NoteRepository.php) | `index()` appelle `findAllNewestFirst()` : la nouvelle note arrive en tête |
| 15 | [templates/note/index.html.twig](../../templates/note/index.html.twig) | La liste, avec chaque titre échappé par Twig |
| 16 | [templates/base.html.twig](../../templates/base.html.twig) | `app.flashes('success')` affiche `Note créée.`, une seule fois |
| 17 | [tests/Controller/NoteControllerTest.php](../../tests/Controller/NoteControllerTest.php) | `testCreateNote()` rejoue tout ce parcours, sur SQLite |

Refaites ensuite le parcours avec un titre vide. À l'étape 6, `isValid()` répond non : pas de `persist()`, pas de redirection, et `render()` réaffiche le formulaire avec un statut 422. `testInvalidNoteIsNotSaved()` couvre ce chemin.

## Pour essayer

Rien de tout cela n'est obligatoire, mais voir le site tourner aide à lire le code.

```bash
docker compose up -d --build --wait
docker compose exec app php bin/console doctrine:migrations:migrate
docker compose exec app php bin/console debug:router
docker compose exec database psql -U app -d app -c 'SELECT * FROM note'
docker compose exec app php bin/phpunit
docker compose down -v
```

Le site répond sur <http://localhost:8080>. Créez quelques notes, puis relancez la requête `psql` pour les voir en base. Les tests ne les effacent pas : la fiche 7 explique pourquoi.

Hors de Docker, avec la base seule :

```bash
docker compose up -d --wait database
symfony console doctrine:migrations:migrate
symfony serve
```

`symfony console` et `symfony serve` trouvent la base grâce à la Symfony CLI, voir [la fiche 2](02-connexion-et-postgresql.md).
