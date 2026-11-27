# 1. L'ORM et les entités

## Ce que c'est

Une base de données relationnelle range ses données dans des **tables**, faites de lignes et de colonnes. PHP, lui, manipule des **objets**. Les deux ne parlent pas la même langue : une table ne connaît ni méthodes ni classes, et un objet ne connaît pas `VARCHAR`.

Un **ORM**, pour _Object-Relational Mapper_, fait la traduction. Vous décrivez une fois la correspondance entre une classe et une table, puis vous travaillez avec des objets. L'ORM écrit le SQL.

Symfony utilise l'ORM **Doctrine**. Ses pièces principales :

| Pièce | Rôle | Dans ce projet |
|---|---|---|
| Entité | Une classe PHP dont les objets correspondent aux lignes d'une table | `Note`, pour la table `note` |
| Mapping | La description de la correspondance, ici par des attributs PHP | Les `#[ORM\...]` de `Note` |
| `EntityManager` | L'objet qui enregistre et supprime les entités | Utilisé par le contrôleur, voir [la fiche 4](04-entity-manager-et-repository.md) |
| Dépôt (_repository_) | L'objet qui lit les entités | `NoteRepository`, voir [la fiche 4](04-entity-manager-et-repository.md) |
| DBAL | La couche du dessous, qui se connecte et parle SQL | Configurée par `DATABASE_URL`, voir [la fiche 2](02-connexion-et-postgresql.md) |

## Pourquoi ça existe

Sans ORM, il faudrait écrire chaque requête à la main, et convertir chaque résultat : la date arrive de la base comme une chaîne `'2026-11-13 11:59:10'`, à transformer en objet `DateTimeImmutable`, et l'inverse à l'écriture. Il faudrait aussi adapter le SQL à chaque moteur : PostgreSQL et SQLite n'écrivent pas une table de la même façon, comme le montre [la fiche 7](07-tests-avec-base-de-donnees.md).

Avec Doctrine, le code de l'application ne contient aucune requête SQL. Le contrôleur dit « enregistre cette note », et Doctrine produit le bon `INSERT` pour le bon moteur.

Le prix : une couche de plus à comprendre. Quand une page est lente ou qu'une erreur SQL apparaît, il faut savoir lire ce que Doctrine a généré. Le profileur web, absent de ce niveau, et la commande `dbal:run-sql` aident à le faire.

## Où le voir dans ce projet

- [src/Entity/Note.php](../../src/Entity/Note.php) : l'entité et son mapping.
- [src/Repository/NoteRepository.php](../../src/Repository/NoteRepository.php) : son dépôt, généré en même temps.
- [config/packages/doctrine.yaml](../../config/packages/doctrine.yaml) : où Doctrine cherche les entités, et comment il nomme les colonnes.
- [migrations/Version20261113114813.php](../../migrations/Version20261113114813.php) : la table que ce mapping a produite sur PostgreSQL.
- [tests/Repository/NoteRepositoryTest.php](../../tests/Repository/NoteRepositoryTest.php) : la preuve que les dates se remplissent seules.

## Comment lire ce code

### L'entité est une classe ordinaire

Ouvrez [src/Entity/Note.php](../../src/Entity/Note.php). Retirez par la pensée les lignes qui commencent par `#[` : il reste une classe PHP banale, avec des propriétés privées, des accesseurs (`getTitle()`) et des mutateurs (`setTitle()`). Elle n'hérite de rien. On peut écrire `new Note()` n'importe où, sans base de données : c'est exactement ce que fait [tests/Entity/NoteValidationTest.php](../../tests/Entity/NoteValidationTest.php).

Les attributs `#[ORM\...]` ne changent pas le comportement de la classe. Doctrine les lit pour savoir comment la stocker.

### Les attributs de mapping

```php
#[ORM\Entity(repositoryClass: NoteRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Note
```

- `#[ORM\Entity]` déclare la classe comme entité. Sans nom de table précisé, Doctrine prend le nom de la classe, converti en `note`.
- `repositoryClass` dit quel dépôt correspond à cette entité.
- `#[ORM\HasLifecycleCallbacks]` active les méthodes de rappel, expliquées plus bas.

```php
#[ORM\Id]
#[ORM\GeneratedValue]
#[ORM\Column]
private ?int $id = null;
```

- `#[ORM\Id]` : cette propriété est la **clé primaire**, ce qui identifie une ligne.
- `#[ORM\GeneratedValue]` : la base choisit la valeur à l'insertion. Le code ne remplit jamais `id`, c'est pourquoi la classe n'a pas de `setId()`.
- `#[ORM\Column]` : la propriété correspond à une colonne.

```php
#[ORM\Column(length: 120)]
private ?string $title = null;

#[ORM\Column(type: Types::TEXT)]
private ?string $content = null;

#[ORM\Column]
private ?\DateTimeImmutable $createdAt = null;

#[ORM\Column(nullable: true)]
private ?\DateTimeImmutable $updatedAt = null;
```

Les colonnes sont `NOT NULL` par défaut. Seule `updatedAt` accepte l'absence de valeur, avec `nullable: true` : une note jamais modifiée n'a pas de date de modification.

Le `?` des types PHP est une autre affaire. Il permet à un objet tout juste créé avec `new Note()` d'avoir des propriétés vides, avant que le formulaire ne les remplisse. Il ne rend pas la colonne facultative.

### Types PHP, types Doctrine, types SQL

Doctrine a ses propres types, qui font le lien entre les deux mondes. Quand l'attribut n'en précise pas, Doctrine le déduit du type de la propriété PHP.

| Propriété | Type PHP | Type Doctrine | Type SQL sur PostgreSQL | Type SQL sur SQLite |
|---|---|---|---|---|
| `id` | `?int` | `integer`, déduit | `INT` | `INTEGER` |
| `title` | `?string` | `string`, déduit, avec `length: 120` | `VARCHAR(120)` | `VARCHAR(120)` |
| `content` | `?string` | `text`, imposé par `Types::TEXT` | `TEXT` | `CLOB` |
| `createdAt` | `?\DateTimeImmutable` | `datetime_immutable`, déduit | `TIMESTAMP(0) WITHOUT TIME ZONE` | `DATETIME` |
| `updatedAt` | `?\DateTimeImmutable` | `datetime_immutable`, déduit | `TIMESTAMP(0) WITHOUT TIME ZONE` | `DATETIME` |

Pourquoi `content` doit-il préciser son type ? Une chaîne PHP donne par défaut le type `string`, qui devient un `VARCHAR` de longueur limitée. Un texte libre, potentiellement long, demande `text`. `Types::TEXT` est une constante qui vaut `'text'` : elle évite une faute de frappe.

Pourquoi `DateTimeImmutable` ? Un objet `DateTime` peut être modifié sur place, et Doctrine ne remarquerait pas toujours le changement. Un `DateTimeImmutable` ne change jamais : toute modification crée un nouvel objet, que Doctrine voit.

Vous pouvez demander à Doctrine ce qu'il a compris :

```bash
php bin/console doctrine:mapping:describe Note
```

La sortie liste chaque champ avec `type`, `columnName`, `length` et `nullable`. On y voit `createdAt` devenir `created_at` : c'est la stratégie de nommage `underscore` de [config/packages/doctrine.yaml](../../config/packages/doctrine.yaml), qui transforme les majuscules en `_`.

### Où Doctrine cherche les entités

Dans [config/packages/doctrine.yaml](../../config/packages/doctrine.yaml), écrit par la recette :

```yaml
mappings:
    App:
        type: attribute
        is_bundle: false
        dir: '%kernel.project_dir%/src/Entity'
        prefix: 'App\Entity'
```

Toute classe de `src/Entity/` portant `#[ORM\Entity]` est une entité, lue par ses attributs. Il n'y a rien à déclarer ailleurs.

### Les rappels `PrePersist` et `PreUpdate`

Les dates de création et de modification ne viennent pas du formulaire. Elles sont remplies par l'entité elle-même, au bon moment :

```php
#[ORM\PrePersist]
public function onPrePersist(): void
{
    $this->createdAt = new \DateTimeImmutable();
}

#[ORM\PreUpdate]
public function onPreUpdate(): void
{
    $this->updatedAt = new \DateTimeImmutable();
}
```

Ce sont des **rappels de cycle de vie** (_lifecycle callbacks_) : Doctrine les appelle lui-même, à condition que la classe porte `#[ORM\HasLifecycleCallbacks]`.

| Attribut | Appelé quand | Effet ici |
|---|---|---|
| `#[ORM\PrePersist]` | Au `persist()` d'une nouvelle entité, avant son premier `INSERT` | `createdAt` reçoit la date du moment |
| `#[ORM\PreUpdate]` | Au `flush()`, avant l'`UPDATE` d'une entité **qui a changé** | `updatedAt` reçoit la date du moment |

Le « qui a changé » compte : si vous ouvrez le formulaire de modification et l'envoyez sans rien toucher, Doctrine ne voit aucune différence, n'écrit pas d'`UPDATE`, et `updatedAt` ne bouge pas. C'est voulu : rien n'a été modifié.

[tests/Repository/NoteRepositoryTest.php](../../tests/Repository/NoteRepositoryTest.php) vérifie les deux : `testPersistSetsCreatedAtAndNotUpdatedAt()` et `testUpdateSetsUpdatedAt()`.

`make:entity` a aussi généré `setCreatedAt()` et `setUpdatedAt()`. Ils sont restés, mais l'application n'en a pas besoin : appeler `setCreatedAt()` avant le `persist()` ne servirait à rien, puisque `onPrePersist()` écrase la valeur. Les tests du tri s'en servent, eux, **après** le `persist()`, pour reculer une date : voir [la fiche 4](04-entity-manager-et-repository.md).

### Ce que l'entité ne contient pas

Aucune requête, aucune connexion, aucun `EntityManager`. L'entité décrit une donnée et ses règles. L'enregistrement se fait ailleurs, dans le contrôleur, et la lecture dans le dépôt. La [fiche 4](04-entity-manager-et-repository.md) suit ce chemin.

Les attributs `#[Assert\...]` posés sur `title` et `content` ne sont pas du mapping : ils viennent du Validator, et la [fiche 6](06-validation-et-csrf.md) les explique.

## Commandes officielles utilisées

```bash
composer require symfony/orm-pack
```

Installe Doctrine : l'ORM, le bundle qui l'intègre à Symfony et les migrations. La recette Flex crée `config/packages/doctrine.yaml`, `config/packages/doctrine_migrations.yaml`, le dossier `migrations/`, la variable `DATABASE_URL` de `.env` et le service `database` de `compose.yaml`.

```bash
php bin/console make:entity --help
symfony console make:entity Note
```

Lisez toujours le `--help` d'une commande `make:*` avant de la lancer. `make:entity` pose ensuite des questions, champ par champ : nom, type, longueur, nullable. Les réponses données pour ce projet :

| Champ | Type | Longueur | Peut être nul |
|---|---|---|---|
| `title` | `string` | 120 | non |
| `content` | `text` | | non |
| `createdAt` | `datetime_immutable` | | non |
| `updatedAt` | `datetime_immutable` | | oui |

Une ligne vide termine la saisie. La commande crée `src/Entity/Note.php` et `src/Repository/NoteRepository.php`. Les contraintes, `#[ORM\HasLifecycleCallbacks]` et les deux rappels ont été ajoutés ensuite à la main : `make:entity` ne les propose pas.

Relancer `make:entity Note` sur une entité existante permet d'y ajouter des champs, sans toucher à ce qui existe.

```bash
php bin/console doctrine:mapping:describe Note
php bin/console doctrine:schema:validate --skip-sync
```

La première montre le mapping tel que Doctrine le comprend. La seconde vérifie qu'il est cohérent, sans se connecter à la base : la CI la lance à chaque changement.

## À retenir

- Un ORM traduit entre objets PHP et tables SQL ; Doctrine écrit le SQL à votre place.
- Une entité est une classe ordinaire, décrite par des attributs `#[ORM\...]`.
- Doctrine déduit le type de colonne du type PHP, sauf quand on le précise, comme `Types::TEXT`.
- Une colonne est `NOT NULL` par défaut ; le `?` du type PHP ne la rend pas facultative.
- `#[ORM\PrePersist]` et `#[ORM\PreUpdate]` remplissent les dates au bon moment, si la classe porte `#[ORM\HasLifecycleCallbacks]`.
- `PreUpdate` ne se déclenche que si l'entité a vraiment changé.
