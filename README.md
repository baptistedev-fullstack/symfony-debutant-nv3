# Symfony Debutant Nv3 : carnet de notes

Troisième niveau du parcours de formation Symfony. Le projet est un carnet de notes : une liste, une page par note, un formulaire pour créer et modifier, un bouton pour supprimer. Chaque note a un titre, un contenu, une date de création et une date de modification.

Le but n'est pas le carnet lui-même, mais de comprendre comment une application Symfony **enregistre ses données** : **Doctrine, les entités, les migrations et le CRUD**. Les notes vivent dans PostgreSQL, la table est créée par une migration, et les tests tournent sur une base jetable qui ne touche jamais vos données.

Ce dépôt se lit : le code d'un côté, la documentation de l'autre. Commencez par [le guide de formation](docs/formations/index.md).

## Prérequis

- Avoir suivi le [niveau précédent](https://github.com/baptistedev-fullstack/symfony-debutant-nv2) : services, injection de dépendances et configuration ne sont pas réexpliqués ici, pas plus que les routes, les contrôleurs et Twig du premier niveau.
- Docker avec Docker Compose : c'est tout ce qu'il faut pour lancer le site et sa base de données.
- Pour travailler hors de Docker : PHP 8.5.12 ou plus avec les extensions `pdo_pgsql` et `pdo_sqlite`, Composer, et le [Symfony CLI](https://symfony.com/download). Le Symfony CLI est ici fortement conseillé : c'est lui qui trouve la base lancée par Docker, comme l'explique [la fiche sur la connexion](docs/formations/02-connexion-et-postgresql.md).

## Démarrage

```bash
docker compose up -d --build --wait
docker compose exec app php bin/console doctrine:migrations:migrate
```

La première commande construit l'image et démarre deux conteneurs : `app`, le site, et `database`, PostgreSQL 16. `--wait` attend que la base soit prête. La seconde crée la table `note` ; elle demande une confirmation, à laquelle on répond `yes`.

Ouvrez ensuite <http://localhost:8080>, qui redirige vers la liste des notes. Le site et la base ne sont accessibles que depuis votre machine. Juste après le démarrage, la première réponse peut échouer ou demander quelques secondes : [docs/docker.md](docs/docker.md) explique pourquoi.

Pour lancer les tests puis tout arrêter :

```bash
docker compose exec app php bin/phpunit
docker compose down -v
```

Les tests utilisent une base SQLite à part : ils n'effacent pas les notes que vous avez créées. `docker compose down -v`, en revanche, supprime aussi le volume de PostgreSQL, donc toutes les notes.

Hors de Docker, avec la base seule :

```bash
composer install
docker compose up -d --wait database
symfony console doctrine:migrations:migrate
symfony serve
```

L'image Docker de ce dépôt sert uniquement au développement local : elle ne doit être ni exposée ni publiée, et le mot de passe de la base, `!ChangeMe!`, est une valeur d'exemple publique. La production arrive à Junior++Nv5. Les raisons sont dans [docs/docker.md](docs/docker.md).

## Ce que fait le site

| URL | Méthode | Résultat |
|---|---|---|
| `/` | `GET` | Redirection vers `/note` |
| `/note` | `GET` | La liste des notes, les plus récentes d'abord |
| `/note/new` | `GET`, `POST` | Le formulaire de création, puis l'enregistrement |
| `/note/{id}` | `GET` | Le détail d'une note |
| `/note/{id}/edit` | `GET`, `POST` | Le formulaire de modification, puis l'enregistrement |
| `/note/{id}` | `POST` | La suppression, avec un jeton CSRF |

Les règles de saisie :

| Champ | Règle | Message en cas d'erreur |
|---|---|---|
| Titre | Obligatoire, pas seulement des espaces, 120 caractères au plus | `Le titre est obligatoire.` ou `Le titre ne doit pas dépasser 120 caractères.` |
| Contenu | Obligatoire, pas seulement des espaces, 10 000 caractères au plus | `Le contenu est obligatoire.` ou `Le contenu ne doit pas dépasser 10000 caractères.` |

Une saisie refusée réaffiche le formulaire avec un statut 422. Un identifiant invalide dans l'URL, comme `/note/abc`, `/note/0` ou `/note/99999999999`, donne une 404.

## Limites connues

Elles sont assumées à ce niveau :

- Pas de pagination : la liste affiche toutes les notes. Pas de données d'exemple non plus. Les deux arrivent en Debutant-Nv5.
- Le formulaire est celui généré par `make:crud`, sans thème ni type personnalisé : le composant Form est étudié en Debutant-Nv4.
- Si le jeton CSRF du formulaire est refusé, son message reste en anglais : le projet n'installe pas de traductions. Voir [la fiche sur la validation et le CSRF](docs/formations/06-validation-et-csrf.md).
- Les tests tournent sur SQLite, pas sur PostgreSQL : la migration est vérifiée par la CI seulement. Voir [la fiche sur les tests](docs/formations/07-tests-avec-base-de-donnees.md).

## Documentation

| Document | Contenu |
|---|---|
| [docs/formations/index.md](docs/formations/index.md) | Ordre de lecture et parcours du code |
| [docs/base-de-donnees.md](docs/base-de-donnees.md) | La table `note` en diagramme, et les commandes pour inspecter la base |
| [docs/arborescence.md](docs/arborescence.md) | Chaque dossier et fichier du projet |
| [docs/versions.md](docs/versions.md) | PHP, Symfony, FrankenPHP, PostgreSQL et chaque paquet |
| [docs/docker.md](docs/docker.md) | Chaque ligne de `Dockerfile`, `compose.yaml`, `compose.override.yaml` et `.dockerignore` |
| [docs/ci-cd.md](docs/ci-cd.md) | Chaque job et chaque étape de la CI |

## Navigation dans le parcours

- Niveau précédent : [symfony-debutant-nv2](https://github.com/baptistedev-fullstack/symfony-debutant-nv2).
- Niveau suivant : [symfony-debutant-nv4](https://github.com/baptistedev-fullstack/symfony-debutant-nv4) (à venir).

## Licence

MIT, voir [LICENSE](LICENSE).
