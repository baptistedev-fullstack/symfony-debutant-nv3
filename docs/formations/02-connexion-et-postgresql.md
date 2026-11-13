# 2. La connexion et PostgreSQL

## Ce que c'est

Pour parler à une base, Doctrine doit savoir **où** elle est, **comment** s'y connecter et **quel moteur** lui répond. Tout tient dans une seule variable d'environnement, `DATABASE_URL`, écrite sous la forme d'une URL.

**PostgreSQL** est le moteur de base de données de ce projet. C'est un serveur : un programme séparé, qui tourne dans son propre conteneur Docker et attend des connexions sur le port `5432`.

## Pourquoi ça existe

L'adresse de la base change selon l'endroit où tourne le code :

| Qui se connecte | Adresse de la base |
|---|---|
| Le conteneur `app` | `database:5432`, par le réseau de Docker Compose |
| `symfony console` sur votre machine | `127.0.0.1` et un port choisi par Docker |
| `php bin/phpunit` | Aucune : un fichier SQLite |

Le code, lui, ne change pas. C'est le rôle d'une variable d'environnement, vu au [niveau précédent](https://github.com/baptistedev-fullstack/symfony-debutant-nv2/blob/main/docs/formations/04-configuration.md) : une valeur qui dépend de la machine reste hors du code. `DATABASE_URL` en est l'exemple le plus courant dans une application Symfony.

## Où le voir dans ce projet

- [.env](../../.env) : la valeur par défaut, écrite par la recette.
- [config/packages/doctrine.yaml](../../config/packages/doctrine.yaml) : l'endroit où Doctrine lit la variable.
- [compose.yaml](../../compose.yaml) : le service `database`, et la valeur donnée au conteneur `app`.
- [compose.override.yaml](../../compose.override.yaml) : le port publié sur votre machine.
- [phpunit.dist.xml](../../phpunit.dist.xml) : la valeur imposée pendant les tests.
- [tests/Config/DatabaseConfigTest.php](../../tests/Config/DatabaseConfigTest.php) : la vérification que `serverVersion` suit l'image.

## Comment lire ce code

### `DATABASE_URL`, morceau par morceau

Dans [.env](../../.env) :

```dotenv
DATABASE_URL="postgresql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=16&charset=utf8"
```

| Morceau | Valeur | Sens |
|---|---|---|
| `postgresql://` | Le schéma | Le pilote à utiliser : PostgreSQL, par l'extension PHP `pdo_pgsql` |
| `app` | L'utilisateur | Le compte PostgreSQL |
| `!ChangeMe!` | Le mot de passe | Placé entre `:` et `@` |
| `127.0.0.1` | L'hôte | La machine où tourne PostgreSQL |
| `5432` | Le port | Le port standard de PostgreSQL |
| `/app` | La base | Le nom de la base sur ce serveur |
| `serverVersion=16` | Une option | La version de PostgreSQL, expliquée plus bas |
| `charset=utf8` | Une option | L'encodage des caractères échangés |

Un mot de passe contenant `@`, `/` ou `#` casserait ce découpage. Il faudrait alors l'encoder, par exemple `%40` pour `@`. `!ChangeMe!` ne pose pas ce problème.

Au-dessus de cette ligne, la recette a laissé en commentaire des exemples pour SQLite, MySQL et MariaDB. Changer de moteur, c'est d'abord changer cette URL.

### Comment Doctrine la lit

Dans [config/packages/doctrine.yaml](../../config/packages/doctrine.yaml) :

```yaml
doctrine:
    dbal:
        url: '%env(resolve:DATABASE_URL)%'
```

`%env(DATABASE_URL)%` lit la variable au moment de l'exécution. Le préfixe `resolve:` remplace en plus les paramètres écrits entre `%` dans la valeur. L'URL de PostgreSQL n'en contient aucun, mais celle des tests, `sqlite:///%kernel.project_dir%/var/test.db`, en a besoin : `%kernel.project_dir%` devient le chemin du projet.

D'où vient la valeur ? Du premier qui la définit, dans cet ordre : une vraie variable d'environnement, puis les fichiers `.env` vus au [premier niveau](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/06-configuration-et-environnements.md). Pour voir la valeur retenue :

```bash
php bin/console debug:dotenv DATABASE_URL
```

### `serverVersion` : pourquoi le donner

Doctrine écrit un SQL légèrement différent selon la version du moteur. Il doit donc la connaître **avant** d'écrire la moindre requête. Deux façons de l'obtenir :

- **Demander au serveur.** Doctrine se connecte, pose la question, puis continue. Si la base est arrêtée, même une commande qui n'a pas besoin de données échoue.
- **La lire dans l'URL.** Avec `serverVersion=16`, Doctrine sait tout de suite à qui il parle, sans se connecter.

C'est pourquoi la recette écrit en majuscules, dans `.env` comme dans `doctrine.yaml` : `IMPORTANT: You MUST configure your server version`.

L'essai a été fait avec `doctrine:schema:validate --skip-sync`, qui ne vérifie que le mapping, et une URL vers un port où rien n'écoute. Sans `serverVersion`, la commande échoue sur `Connection refused` dès la partie `Mapping`. Avec `serverVersion=16`, elle affiche `[OK] The mapping files are correct.` sans jamais se connecter. C'est ce qui permet de la lancer dans le job `quality` de la CI, où aucune base n'existe.

La valeur doit dire la vérité. [tests/Config/DatabaseConfigTest.php](../../tests/Config/DatabaseConfigTest.php) le vérifie :

```php
preg_match('/^DATABASE_URL=.*[?&]serverVersion=(\d+)/m', file_get_contents($projectDir.'/.env'), $env);
preg_match('/image:\s*postgres:\$\{POSTGRES_VERSION:-(\d+)\}-alpine/', file_get_contents($projectDir.'/compose.yaml'), $compose);
// ...
$this->assertSame($compose[1], $env[1]);
```

Le test lit les deux fichiers comme du texte et compare les numéros. Si quelqu'un passe l'image à `postgres:17-alpine` sans toucher `.env`, il échoue.

### Le service `database` de la recette

La recette Flex de Doctrine a ajouté à [compose.yaml](../../compose.yaml) un service `database` : l'image `postgres:16-alpine`, trois variables qui créent la base `app` et l'utilisateur `app` au premier démarrage, un `healthcheck` et un volume pour les données. [docker.md](../docker.md#le-service-database-venu-de-la-recette) l'explique ligne par ligne.

Les valeurs de `.env` et du service sont les mêmes, `app`, `!ChangeMe!`, `app` et `16` : la recette les a écrites ensemble pour qu'elles s'accordent.

### Trois URL pour la même base

Le conteneur `app` ne peut pas utiliser l'URL de `.env` : pour lui, `127.0.0.1` est le conteneur lui-même, où PostgreSQL ne tourne pas. [compose.yaml](../../compose.yaml) lui donne donc une autre URL :

```yaml
DATABASE_URL: "postgresql://${POSTGRES_USER:-app}:${POSTGRES_PASSWORD:-!ChangeMe!}@database:5432/${POSTGRES_DB:-app}?serverVersion=${POSTGRES_VERSION:-16}&charset=utf8"
```

L'hôte est `database`, le nom du service, que Docker Compose rend joignable sur son réseau interne. C'est une vraie variable d'environnement : elle l'emporte sur `.env`.

| Qui | URL utilisée | Hôte et port |
|---|---|---|
| Le conteneur `app` | Celle de `compose.yaml` | `database:5432` |
| `symfony console` sur votre machine | Celle de la Symfony CLI | `127.0.0.1` et le port publié |
| `php bin/console` sur votre machine | Celle de `.env` | `127.0.0.1:5432`, qui ne répond pas |
| `php bin/phpunit`, partout | Celle de `phpunit.dist.xml` | Le fichier `var/test.db` |

### Pourquoi `symfony console` trouve la base, et `php bin/console` non

[compose.override.yaml](../../compose.override.yaml) publie le port de la base ainsi :

```yaml
ports:
  - "127.0.0.1::5432"
```

Le port de votre machine est laissé vide : Docker en choisit un libre, au hasard. Voyez-le :

```bash
docker compose up -d --wait database
docker compose port database 5432
```

```text
127.0.0.1:49794
```

`.env` dit `127.0.0.1:5432`. Rien n'écoute sur ce port, et `php bin/console` échoue :

```bash
php bin/console doctrine:migrations:status
```

```text
An exception occurred in the driver: SQLSTATE[08006] [7] connection to server at "127.0.0.1", port 5432 failed: Connection refused
```

La **Symfony CLI** connaît Docker Compose. Quand elle voit un service `database` lancé, elle lit son port publié et définit elle-même `DATABASE_URL` avant de lancer la commande. Vous pouvez voir ce qu'elle prépare :

```bash
symfony var:export --multiline
```

```text
export DATABASE_URL='postgres://app:!ChangeMe!@127.0.0.1:49794/app?sslmode=disable&charset=utf8&serverVersion=16.13'
```

Le port est le bon, et la CLI a même lu la version exacte de PostgreSQL. Cette variable est réelle : elle l'emporte sur `.env`. `symfony console` est donc l'équivalent de `php bin/console`, avec la bonne adresse :

```bash
symfony console doctrine:migrations:status
```

Pourquoi ne pas publier simplement le port 5432 et garder `php bin/console` ? Parce qu'un PostgreSQL déjà installé sur votre machine l'occuperait peut-être, et le conteneur refuserait de démarrer. Le port au hasard évite ce conflit ; la Symfony CLI rend ce hasard invisible.

### Le mot de passe et le port limité à `127.0.0.1`

`!ChangeMe!` est le mot de passe de la recette, écrit dans `.env` et dans `compose.yaml`, deux fichiers publics. Toute application créée avec cette recette a le même. Ce n'est pas un secret, et il ne protège rien.

Ce qui protège la base, c'est le `127.0.0.1` devant le port. La recette écrit `"5432"` seul, ce qui ouvrirait le port sur toutes les interfaces réseau de votre machine : toute personne sur le même Wi-Fi pourrait alors s'y connecter avec un mot de passe connu de tous. Ce projet a remplacé cette ligne par `"127.0.0.1::5432"` : seule votre machine peut joindre la base.

En production, la règle change : un vrai mot de passe, gardé dans un secret ou dans une variable du serveur, jamais dans un fichier versionné. Ce sera le sujet du palier Junior++.

## Commandes officielles utilisées

```bash
docker compose up -d --wait database
```

Démarre seulement la base, et attend qu'elle réponde à son `healthcheck`.

```bash
docker compose port database 5432
```

Affiche le port choisi par Docker sur votre machine.

```bash
symfony var:export --multiline
```

Montre les variables que la Symfony CLI ajoute, dont `DATABASE_URL`.

```bash
php bin/console debug:dotenv DATABASE_URL
```

Montre les fichiers `.env` lus, la valeur proposée par chacun, et dans la colonne `Value` la valeur finalement retenue. Lancée par `symfony console`, la colonne `Value` montre l'URL de la Symfony CLI : une vraie variable l'emporte sur les fichiers.

```bash
symfony console dbal:run-sql "SELECT version()"
```

Pose une question SQL à la base, ici sa version exacte.

## À retenir

- `DATABASE_URL` dit tout : pilote, utilisateur, mot de passe, hôte, port, base et options.
- Une vraie variable d'environnement l'emporte sur `.env` : c'est ainsi que le conteneur `app` et la Symfony CLI donnent leur propre URL.
- `serverVersion` évite à Doctrine de se connecter pour connaître le moteur ; il doit suivre l'image Docker.
- Dans un conteneur, `127.0.0.1` désigne le conteneur lui-même ; on joint un autre service par son nom.
- Sur votre machine, utilisez `symfony console` : elle connaît le port choisi par Docker.
- `!ChangeMe!` n'est pas un secret ; c'est le port limité à `127.0.0.1` qui protège la base.
