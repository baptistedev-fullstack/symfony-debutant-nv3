# 7. Les tests avec une base de données

Les niveaux précédents ont présenté [les tests fonctionnels](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/07-tests.md), puis [les tests unitaires et `KernelTestCase`](https://github.com/baptistedev-fullstack/symfony-debutant-nv2/blob/main/docs/formations/07-tests-unitaires-et-doublures.md). Leurs tests n'avaient pas d'état : la même requête donnait toujours la même réponse. Avec une base, chaque test écrit des données, et le suivant risque de les retrouver. Cette fiche montre comment ce projet garde ses tests isolés, et raconte un piège rencontré en chemin.

## Ce que c'est

Les tests de ce projet n'utilisent **pas** PostgreSQL. Ils utilisent **SQLite**, un moteur de base de données qui tient dans un simple fichier et ne demande aucun serveur. Ici, ce fichier est `var/test.db`. Avant chaque test qui touche la base, ce fichier est vidé et le schéma recréé.

| Classe de test | Classe parente | Tests |
|---|---|---|
| `NoteControllerTest` | `WebTestCase` | 24 |
| `NoteValidationTest` | `KernelTestCase` | 6 |
| `NoteRoutesTest` | `KernelTestCase` | 6 |
| `DocsConsistencyTest` | `TestCase` | 5 |
| `NoteRepositoryTest` | `KernelTestCase` | 5 |
| `DatabaseConfigTest` | `KernelTestCase` | 2 |

Soit 48 tests en tout. `NoteValidationTest` ne touche pas la base : il valide des objets `Note` en mémoire. `NoteRoutesTest` non plus : il n'interroge que le routeur. `DatabaseConfigTest` la regarde sans y écrire. `NoteControllerTest` et `NoteRepositoryTest` y écrivent.

## Pourquoi ça existe

Deux exigences guident ce choix.

**Ne jamais toucher la base de développement.** Vos notes d'essai vivent dans PostgreSQL. Un test qui viderait la table `note` les effacerait. Les tests doivent donc écrire ailleurs, à coup sûr, quel que soit l'endroit où on les lance.

**Des tests rapides et sans prérequis.** `php bin/phpunit` doit marcher sur une machine où Docker ne tourne pas, et dans le job `quality` de la CI, qui n'a pas de PostgreSQL. SQLite est inclus dans PHP par l'extension `pdo_sqlite` : rien à démarrer.

Le parcours de formation l'autorise explicitement au palier Debutant. Il a un prix, détaillé plus bas : SQLite n'est pas PostgreSQL, et certaines erreurs n'apparaissent que sur le vrai moteur.

## Où le voir dans ce projet

- [phpunit.dist.xml](../../phpunit.dist.xml) : les deux lignes qui imposent SQLite.
- [tests/bootstrap.php](../../tests/bootstrap.php) : le chargement des fichiers `.env`, avant les tests.
- [tests/Config/DatabaseConfigTest.php](../../tests/Config/DatabaseConfigTest.php) : le garde-fou, qui échoue si les tests ne tournent pas sur SQLite.
- [tests/ResetDatabaseTrait.php](../../tests/ResetDatabaseTrait.php) : la remise à zéro avant chaque test.
- [tests/Repository/NoteRepositoryTest.php](../../tests/Repository/NoteRepositoryTest.php) et [tests/Controller/NoteControllerTest.php](../../tests/Controller/NoteControllerTest.php) : les tests qui écrivent en base.
- [tests/Routing/NoteRoutesTest.php](../../tests/Routing/NoteRoutesTest.php) : un test qui prouve sans la base ce que SQLite ne montre pas.
- [config/packages/doctrine.yaml](../../config/packages/doctrine.yaml) : le réglage `dbname_suffix` de l'environnement `test`.
- [compose.yaml](../../compose.yaml) : la variable `DATABASE_URL` du conteneur, au cœur du piège.

## Comment lire ce code

### Deux lignes pour imposer SQLite

Dans [phpunit.dist.xml](../../phpunit.dist.xml) :

```xml
<server name="DATABASE_URL" value="sqlite:///%kernel.project_dir%/var/test.db" force="true" />
<!-- Indispensable dans le conteneur : ... -->
<env name="DATABASE_URL" value="sqlite:///%kernel.project_dir%/var/test.db" force="true" />
```

L'URL `sqlite:///%kernel.project_dir%/var/test.db` désigne un fichier. Les trois `/` sont le `//` d'une URL suivi du début d'un chemin. `%kernel.project_dir%` est remplacé par le chemin du projet grâce au préfixe `resolve:` de `doctrine.yaml`, vu dans [la fiche 2](02-connexion-et-postgresql.md). Le fichier est créé au premier test ; il est dans `var/`, donc ignoré par Git.

Pourquoi deux lignes presque identiques ? La première a d'abord semblé suffire. La seconde a été ajoutée après un échec, et cet échec est la meilleure leçon de ce niveau.

### Le piège : `$_ENV` passe avant `$_SERVER`

PHP range les variables d'environnement à deux endroits : le tableau `$_SERVER`, et le tableau `$_ENV`. Les deux balises de PHPUnit n'écrivent pas au même endroit :

| Balise | Ce que fait PHPUnit |
|---|---|
| `<server name="X" value="...">` | Écrit `$_SERVER['X']`, toujours |
| `<env name="X" value="..." force="true">` | Écrit `$_ENV['X']` et appelle `putenv()`, même si la variable existe déjà |

Sans `force="true"`, `<env>` laisserait en place une variable déjà définie. Sur `<server>`, l'attribut `force` ne change rien avec PHPUnit 13 : la valeur est toujours écrite.

Symfony, de son côté, lit une variable d'environnement dans cet ordre, dans la classe `EnvVarProcessor` :

1. `$_ENV`
2. `$_SERVER`
3. `getenv()`

**`$_ENV` passe en premier.** Tant que `$_ENV` ne contient pas `DATABASE_URL`, la ligne `<server>` suffit. Mais si `$_ENV` en contient une autre valeur, c'est elle qui gagne.

Qui remplit `$_ENV` ? PHP lui-même, au démarrage, selon le réglage `variables_order` de son fichier `php.ini`. Si ce réglage contient la lettre `E`, PHP copie toutes les variables d'environnement réelles dans `$_ENV`. Sinon, `$_ENV` reste vide.

| Où | `php.ini` | `variables_order` | `$_ENV` au démarrage |
|---|---|---|---|
| PHP installé par Homebrew sur macOS | Celui de Homebrew | `GPCS` | Vide |
| L'image `dunglas/frankenphp` | Aucun | `EGPCS`, la valeur par défaut de PHP | Toutes les variables réelles |

Vous pouvez le vérifier :

```bash
php -r 'echo ini_get("variables_order"), PHP_EOL;'
docker compose exec app php -r 'echo ini_get("variables_order"), PHP_EOL;'
```

Assemblez maintenant les pièces, dans le conteneur `app` :

1. [compose.yaml](../../compose.yaml) définit une vraie variable `DATABASE_URL`, vers PostgreSQL.
2. PHP démarre avec `EGPCS` et la copie dans `$_ENV`.
3. PHPUnit écrit l'URL SQLite dans `$_SERVER`, avec la seule ligne `<server>`.
4. [tests/bootstrap.php](../../tests/bootstrap.php) lit les fichiers `.env`. Il ne remplace jamais une variable déjà présente dans `$_ENV` : il n'y touche pas.
5. Doctrine demande `DATABASE_URL`. Symfony regarde `$_ENV` d'abord, et y trouve **PostgreSQL**.

Le premier lancement de PHPUnit dans le conteneur a donné 25 erreurs et un échec. L'échec venait de `DatabaseConfigTest` : la plateforme était PostgreSQL. Les erreurs disaient que la base `app_test` n'existait pas.

Sur macOS, rien de tout cela : `$_ENV` est vide au démarrage, [tests/bootstrap.php](../../tests/bootstrap.php) y recopie la valeur SQLite de `$_SERVER`, et Doctrine la trouve. Le même fichier de configuration donnait donc deux résultats selon la machine.

La ligne `<env ... force="true">` règle le problème : elle écrit l'URL SQLite dans `$_ENV`, par-dessus la vraie variable. `$_ENV` et `$_SERVER` disent alors la même chose, partout. La ligne `<server>` est gardée pour cette raison : avec les deux, aucun code ne peut lire une autre URL que celle des tests, qu'il regarde `$_ENV` ou `$_SERVER`. Un commentaire dans [phpunit.dist.xml](../../phpunit.dist.xml) rappelle pourquoi la ligne `<env>` existe, pour que personne ne la supprime en la croyant en double.

### Reproduire le piège sur votre machine

Pas besoin de Docker. L'option `-d` de PHP change un réglage pour une seule commande, et un fichier `phpunit.xml` à la racine remplace `phpunit.dist.xml` ; il est ignoré par Git. La première commande crée une copie de la configuration sans la ligne `<env>` :

```bash
grep -v '<env name="DATABASE_URL"' phpunit.dist.xml > phpunit.xml
DATABASE_URL='postgresql://app:x@127.0.0.1:1/app?serverVersion=16&charset=utf8' \
  php -d variables_order=EGPCS bin/phpunit -c phpunit.xml tests/Config
rm phpunit.xml
```

La deuxième commande imite le conteneur : une vraie variable vers PostgreSQL, et `$_ENV` rempli au démarrage. Résultat :

```text
1) App\Tests\Config\DatabaseConfigTest::testTestsRunOnSqlite
Failed asserting that an instance of class Doctrine\DBAL\Platforms\PostgreSQL120Platform is an instance of class Doctrine\DBAL\Platforms\SQLitePlatform.
```

Avec `variables_order=GPCS`, ou avec la configuration complète, les deux tests passent. `PostgreSQL120Platform` est la classe de Doctrine qui écrit le SQL de PostgreSQL 12 et des versions suivantes, dont la 16.

### Ce qui a évité le pire : `dbname_suffix`

Pendant ce premier lancement raté, les tests visaient PostgreSQL. Pourquoi n'ont-ils pas vidé la table `note` de développement ? Grâce à ces lignes de [config/packages/doctrine.yaml](../../config/packages/doctrine.yaml), écrites par la recette :

```yaml
when@test:
    doctrine:
        dbal:
            # "TEST_TOKEN" is typically set by ParaTest
            dbname_suffix: '_test%env(default::TEST_TOKEN)%'
```

En environnement `test`, Doctrine ajoute `_test` au nom de la base. Les tests cherchaient donc la base `app_test`, qui n'existait pas, d'où les 25 erreurs. La base `app`, celle de vos notes, n'a pas été touchée.

C'est une protection de la recette, pas de ce projet. Elle n'a rien à faire avec SQLite : l'URL désigne un fichier, sans nom de base, et le fichier reste `var/test.db`.

### La preuve du test témoin

Un test vert ne prouve pas à lui seul qu'il a tourné sur la bonne base. Pour le vérifier, le projet a fait l'essai suivant dans le conteneur, base de développement migrée :

```bash
docker compose exec -T app php bin/console dbal:run-sql "INSERT INTO note (title, content, created_at) VALUES ('Témoin', 'Ne pas effacer', NOW())"
docker compose exec -T app php bin/phpunit
docker compose exec -T app php bin/console dbal:run-sql "SELECT count(*) FROM note"
```

1. Une note **témoin** est écrite directement dans PostgreSQL.
2. Toute la suite de tests tourne. Chacun de ses tests vide la table `note` de la base qu'il utilise.
3. On compte les notes de PostgreSQL.

Le comptage final vaut `1` : la note témoin est toujours là. Les tests ont donc vidé une autre base, SQLite, et jamais celle de développement. Sans la ligne `<env>`, le même essai aurait échoué à l'étape 2.

### Le garde-fou permanent

L'essai du témoin se fait une fois, à la main. [tests/Config/DatabaseConfigTest.php](../../tests/Config/DatabaseConfigTest.php) prend le relais à chaque lancement :

```php
public function testTestsRunOnSqlite(): void
{
    $connection = static::getContainer()->get(Connection::class);

    $this->assertInstanceOf(SQLitePlatform::class, $connection->getDatabasePlatform());
}
```

Il demande la connexion de Doctrine au conteneur, puis la **plateforme**, l'objet qui sait écrire le SQL d'un moteur. Si ce n'est pas SQLite, il échoue. Ce test tourne dans les deux jobs de la CI : sur le runner du job `quality`, et dans le conteneur du job `docker`, là où le piège s'est produit. Si quelqu'un supprimait la ligne `<env>`, le job `docker` deviendrait rouge.

Son voisin, `testServerVersionMatchesTheComposeImage()`, vérifie que `serverVersion` de `.env` suit l'image PostgreSQL de `compose.yaml`, comme l'explique [la fiche 2](02-connexion-et-postgresql.md).

### `ResetDatabaseTrait` et `SchemaTool`

Chaque test qui écrit en base commence par la remettre à zéro. [tests/ResetDatabaseTrait.php](../../tests/ResetDatabaseTrait.php) :

```php
private function resetDatabase(): void
{
    $entityManager = static::getContainer()->get(EntityManagerInterface::class);
    $schemaTool = new SchemaTool($entityManager);
    $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

    $schemaTool->dropSchema($metadata);
    $schemaTool->createSchema($metadata);
    $entityManager->clear();
}
```

| Ligne | Effet |
|---|---|
| `getAllMetadata()` | Le mapping de toutes les entités, ici `Note` seule |
| `new SchemaTool(...)` | L'outil de Doctrine qui écrit le SQL de création à partir du mapping, pour le moteur connecté |
| `dropSchema()` | Supprime les tables des entités, et leurs données |
| `createSchema()` | Les recrée, vides |
| `clear()` | Vide la mémoire de l'`EntityManager`, voir [la fiche 4](04-entity-manager-et-repository.md) |

Un **trait** est un morceau de classe que plusieurs classes peuvent inclure avec `use`. `NoteRepositoryTest` et `NoteControllerTest` l'utilisent tous les deux, dans leur `setUp()`, qui s'exécute avant chaque test.

Sur SQLite, `SchemaTool` écrit ceci :

```sql
CREATE TABLE note (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(120) NOT NULL, content CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL)
```

Comparez avec la migration, écrite pour PostgreSQL : `INTEGER PRIMARY KEY AUTOINCREMENT` au lieu de `INT GENERATED BY DEFAULT AS IDENTITY`, `CLOB` au lieu de `TEXT`, `DATETIME` au lieu de `TIMESTAMP(0) WITHOUT TIME ZONE`. C'est pourquoi les tests ne jouent pas la migration : elle ne fonctionnerait pas sur SQLite. Comme l'explique [la fiche 3](03-migrations.md), `SchemaTool` est le bon outil pour une base jetable, et le mauvais pour une base qui contient des données.

Pourquoi tout recréer plutôt que supprimer les lignes ? Le schéma suit toujours l'entité actuelle : un champ ajouté à `Note` apparaît dans la base de test sans rien faire. Et un test qui laisserait la base dans un état bizarre ne gêne pas le suivant.

L'ordre compte dans [tests/Controller/NoteControllerTest.php](../../tests/Controller/NoteControllerTest.php) :

```php
protected function setUp(): void
{
    $this->client = static::createClient();
    $this->resetDatabase();
    $this->manager = static::getContainer()->get(EntityManagerInterface::class);
}
```

`createClient()` démarre l'application ; `resetDatabase()` a besoin de son conteneur. Dans [tests/Repository/NoteRepositoryTest.php](../../tests/Repository/NoteRepositoryTest.php), c'est `self::bootKernel()` qui joue ce rôle.

### Ce que SQLite ne prouve pas

SQLite fait tourner le même code, mais ce n'est pas le même moteur. Ce projet en a rencontré plusieurs différences :

| Sujet | PostgreSQL | SQLite | Conséquence |
|---|---|---|---|
| La migration | Son SQL est du PostgreSQL | Ne la comprend pas | Les tests ne prouvent pas que la migration est juste |
| `/note/abc` sans exigence de route | Erreur `invalid input syntax for type integer`, donc une 500 | Aucune ligne, donc une 404 | La 404 d'un test HTTP ne prouve pas l'exigence : `NoteRoutesTest` la vérifie sur le routeur, voir [la fiche 4](04-entity-manager-et-repository.md) |
| Un titre de 121 caractères sans `Length` | Erreur `value too long`, donc une 500 | Accepté | La 500 est invisible en test ; le 422 attendu prouve que `Length` arrête la saisie, voir [la fiche 6](06-validation-et-csrf.md) |
| Les types de colonnes | `TIMESTAMP(0)`, `TEXT` | `DATETIME`, `CLOB` | Les tests ne voient pas les vrais types |

Pour les deux erreurs, le code a été écrit pour que PostgreSQL ne les voie jamais : l'exigence de route et la contrainte `Length` arrêtent la saisie avant la base. Les tests vérifient ce comportement, qui est le même sur les deux moteurs.

Pour la migration, il faut un vrai PostgreSQL. C'est le travail du job `docker` de la [CI](../ci-cd.md) : il démarre le service `database`, joue la migration, puis lance `doctrine:schema:validate` et `doctrine:migrations:up-to-date` contre PostgreSQL. Si la migration oubliait une colonne, ou si l'entité changeait sans nouvelle migration, ce job échouerait.

### La suite : PostgreSQL dans le job `quality`

Au palier Junior, les tests eux-mêmes tourneront sur PostgreSQL, avec un service de base dans le job `quality` de la CI. Les différences du tableau ci-dessus disparaîtront, au prix de tests un peu plus lents et d'une base à démarrer. Ce niveau-ci garde SQLite, plus simple à comprendre, et compense par le job `docker`.

## Commandes officielles utilisées

```bash
php bin/console make:test KernelTestCase 'Repository\NoteRepositoryTest'
php bin/console make:test KernelTestCase 'Config\DatabaseConfigTest'
php bin/console make:test KernelTestCase 'Entity\NoteValidationTest'
php bin/console make:test KernelTestCase 'Routing\NoteRoutesTest'
```

Génèrent les squelettes de test, comme au niveau précédent. Le nom de la classe s'écrit avec son sous-dossier et un `\`, entre guillemets simples pour le shell. `NoteControllerTest` vient de `make:crud`, voir [la fiche 5](05-crud-genere.md).

```bash
php bin/phpunit
php bin/phpunit tests/Config
docker compose exec app php bin/phpunit
```

Toute la suite, le garde-fou seul, puis toute la suite dans le conteneur, là où le piège se produisait.

```bash
php -r 'echo ini_get("variables_order"), PHP_EOL;'
php --ini
```

Le réglage qui décide si `$_ENV` est rempli, et le fichier `php.ini` qui le fixe.

## À retenir

- Les tests tournent sur SQLite, dans `var/test.db`, et ne doivent jamais toucher PostgreSQL.
- Symfony lit `$_ENV` avant `$_SERVER` ; une vraie variable d'environnement peut donc battre `<server>`.
- Selon `variables_order`, `$_ENV` est rempli ou non : le même test peut passer sur une machine et échouer dans un conteneur.
- `<env ... force="true">` impose la valeur dans `$_ENV` ; avec `<server>`, les deux tableaux disent la même chose.
- Un test témoin prouve une fois l'isolement ; `DatabaseConfigTest` le surveille à chaque lancement.
- `SchemaTool` recrée le schéma avant chaque test ; les migrations restent réservées à PostgreSQL.
- SQLite ne prouve pas la migration : le job `docker` de la CI la vérifie sur un vrai PostgreSQL.
