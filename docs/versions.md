# Versions

Les versions de ce niveau. [tests/Docs/DocsConsistencyTest.php](../tests/Docs/DocsConsistencyTest.php) compare ce document à [composer.lock](../composer.lock), [composer.json](../composer.json), au [Dockerfile](../Dockerfile) et à [compose.yaml](../compose.yaml) : le test échoue si une version ne correspond plus.

Pour lire une contrainte comme `8.1.*` ou `^3.7`, voir [la fiche sur Composer](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/02-composer-et-flex.md).

## Socle

| Élément | Version | Rôle |
|---|---|---|
| PHP | 8.5.12 | Le langage. Version de l'image Docker. `composer.json` exige `>=8.5.12` : la 8.5.11 a des failles activement exploitées |
| Symfony | `8.1.*` | Le framework. Tous les paquets Symfony sont limités à cette branche |
| FrankenPHP | 1.13.5 | Le serveur web et PHP, voir [docker.md](docker.md) |
| PostgreSQL | 16 | La base de données, image `postgres:16-alpine` lancée par [compose.yaml](../compose.yaml) |

L'image Docker `dunglas/frankenphp:1.13.5-php8.5.12-trixie` repose sur Debian 13, dite `trixie`, et embarque Caddy 2.11.9.

La CI demande PHP `8.5` : elle reçoit la dernière version disponible de cette branche, qui doit rester au moins égale à 8.5.12.

### PostgreSQL : un numéro majeur seulement

L'image s'écrit `postgres:${POSTGRES_VERSION:-16}-alpine` : la version **16**, sauf si la variable `POSTGRES_VERSION` en donne une autre. Le tag `16-alpine` désigne la dernière version corrective de la branche 16 au moment du téléchargement, `16.13` lors de l'écriture de ce document. Contrairement à PHP, on ne fige pas le correctif : la recette Flex de Doctrine écrit l'image ainsi, et un correctif de PostgreSQL ne change pas le SQL accepté.

Le numéro `16` apparaît à trois endroits, qui doivent rester d'accord :

| Endroit | Valeur |
|---|---|
| [compose.yaml](../compose.yaml), service `database` | `image: postgres:${POSTGRES_VERSION:-16}-alpine` |
| [compose.yaml](../compose.yaml), service `app` | `serverVersion=${POSTGRES_VERSION:-16}` dans `DATABASE_URL` |
| [.env](../.env) | `serverVersion=16` dans `DATABASE_URL` |

[tests/Config/DatabaseConfigTest.php](../tests/Config/DatabaseConfigTest.php) vérifie que `.env` et l'image donnent le même numéro, et le test de la documentation vérifie la ligne PostgreSQL du tableau ci-dessus. La [fiche sur la connexion](formations/02-connexion-et-postgresql.md) explique à quoi sert `serverVersion`.

Les paquets Symfony n'ont pas tous le même numéro de correctif, par exemple `symfony/asset` et `symfony/console` ci-dessous. C'est normal : un composant sans changement ne reçoit pas de nouvelle version. La commande `php bin/console about` donne la version du framework.

## Paquets de production (`require`)

| Paquet | Version | Rôle |
|---|---|---|
| `doctrine/doctrine-bundle` | 3.4.1 | L'intégration de Doctrine dans Symfony : connexion, configuration, commandes `doctrine:*` |
| `doctrine/doctrine-migrations-bundle` | 4.0.1 | Les migrations et les commandes `doctrine:migrations:*` |
| `doctrine/orm` | 3.7.6 | L'ORM : entités, `EntityManager`, dépôts, QueryBuilder |
| `symfony/asset` | v8.1.12 | La fonction `asset()`, qui donne l'URL de la feuille de style |
| `symfony/console` | v8.1.12 | Les commandes `bin/console` |
| `symfony/dotenv` | v8.1.6 | La lecture des fichiers `.env` |
| `symfony/flex` | v2.12.0 | Le plugin Composer qui applique les recettes |
| `symfony/form` | v8.1.13 | Le composant Form, utilisé par le formulaire généré `NoteType` |
| `symfony/framework-bundle` | v8.1.12 | Le cœur de Symfony : Kernel, contrôleurs, configuration |
| `symfony/runtime` | v8.1.12 | Le démarrage de l'application depuis `public/index.php` |
| `symfony/security-csrf` | v8.1.0 | Les jetons CSRF du formulaire et de la suppression |
| `symfony/twig-bundle` | v8.1.9 | L'intégration de Twig |
| `symfony/validator` | v8.1.13 | Les contraintes `#[Assert\...]` de l'entité |
| `symfony/yaml` | v8.1.13 | La lecture des fichiers YAML et `lint:yaml` |

`composer.json` exige aussi deux extensions de PHP, `ext-ctype` et `ext-iconv`, présentes dans l'image Docker.

### D'où viennent les nouveaux paquets

Par rapport à Debutant-Nv2, ce niveau ajoute six paquets directs : les trois de Doctrine, `symfony/form`, `symfony/security-csrf` et `symfony/validator`. Ils viennent de deux commandes :

```bash
composer require symfony/twig-bundle symfony/asset symfony/orm-pack symfony/validator
composer require form security-csrf
```

Twig et Asset figurent dans la première parce que le squelette créé par `symfony new` ne les contient pas : ils étaient déjà là au niveau précédent.

`symfony/orm-pack` est un **pack** : un paquet vide qui ne sert qu'à en installer d'autres. Flex le remplace dans `composer.json` par ce qu'il contient, c'est pourquoi vous voyez `doctrine/doctrine-bundle`, `doctrine/doctrine-migrations-bundle` et `doctrine/orm`, mais pas `symfony/orm-pack`.

La seconde commande n'était pas prévue au départ : `make:crud` refuse de tourner sans Form et sans la protection CSRF, et affiche la commande à lancer. La [fiche sur le CRUD généré](formations/05-crud-genere.md) le raconte.

## Paquets de développement (`require-dev`)

| Paquet | Version | Rôle |
|---|---|---|
| `phpunit/phpunit` | 13.4.7 | Le lancement des tests |
| `symfony/browser-kit` | v8.1.13 | Le navigateur simulé des tests fonctionnels |
| `symfony/css-selector` | v8.1.10 | Les sélecteurs CSS dans les assertions |
| `symfony/maker-bundle` | 1.68.0 | Les commandes `make:*` qui génèrent du code |

Les trois premiers viennent de `symfony/test-pack`, un autre pack, installé avec `composer require --dev symfony/test-pack symfony/maker-bundle`.

## Dépendances indirectes

Les paquets ci-dessus en installent d'autres. Quelques-uns comptent pour ce niveau :

| Paquet | Pourquoi il compte ici |
|---|---|
| `doctrine/dbal` | La couche qui parle à la base : connexion, plateformes SQL, types. Installé par `doctrine/orm` |
| `doctrine/migrations` | Le moteur des migrations, que le bundle intègre à Symfony |
| `symfony/doctrine-bridge` | Le pont entre Symfony et Doctrine, dont la conversion de `{id}` en `Note` |
| `twig/twig` | Le moteur de templates |

Ils sont tous dans [composer.lock](../composer.lock), mais ce projet ne choisit directement que les paquets listés plus haut.

```bash
composer show --direct
composer show --tree
composer show doctrine/dbal
```

La première commande liste les paquets directs et leur version installée, la deuxième montre qui dépend de quoi, la troisième donne la version installée d'un paquet indirect.

## Durée de vie

Le parcours reste sur Symfony 8.1. La commande `php bin/console about` indique sa fin de maintenance, en janvier 2027 : ce n'est pas une version à support long.

PostgreSQL 16 est maintenu par le projet PostgreSQL jusqu'en novembre 2028.
