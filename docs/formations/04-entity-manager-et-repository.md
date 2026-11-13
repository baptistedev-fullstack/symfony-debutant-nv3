# 4. L'EntityManager et le dépôt

## Ce que c'est

Doctrine sépare l'écriture et la lecture entre deux objets :

| Objet | Rôle | Méthodes vues ici |
|---|---|---|
| `EntityManager` | Écrire : enregistrer, modifier, supprimer | `persist()`, `flush()`, `remove()` |
| Dépôt (_repository_) | Lire : retrouver des entités | `find()`, `findAll()`, `findAllNewestFirst()` |

Les deux sont des [services](https://github.com/baptistedev-fullstack/symfony-debutant-nv2/blob/main/docs/formations/01-services.md), fournis par le conteneur comme n'importe quel autre. Le contrôleur les reçoit par [injection de dépendances](https://github.com/baptistedev-fullstack/symfony-debutant-nv2/blob/main/docs/formations/02-injection-de-dependances.md), dans les arguments de ses méthodes.

## Pourquoi ça existe

Écrire une ligne SQL à chaque modification d'objet serait lent et fragile. L'`EntityManager` fonctionne autrement : il **note** ce qui doit changer, puis écrit tout d'un coup, dans une seule transaction, au moment où on le lui demande. Si une requête échoue, aucune des autres n'est gardée.

Le dépôt, lui, regroupe au même endroit toutes les façons de chercher une entité. Le contrôleur demande « les notes, les plus récentes d'abord » ; il ne sait pas comment on les trouve.

## Où le voir dans ce projet

- [src/Controller/NoteController.php](../../src/Controller/NoteController.php) : `persist()`, `flush()` et `remove()`, et la conversion de `{id}` en `Note`.
- [src/Repository/NoteRepository.php](../../src/Repository/NoteRepository.php) : le dépôt et `findAllNewestFirst()`.
- [tests/Repository/NoteRepositoryTest.php](../../tests/Repository/NoteRepositoryTest.php) : l'ordre de tri, et les dates remplies au bon moment.
- [tests/Controller/NoteControllerTest.php](../../tests/Controller/NoteControllerTest.php) : les identifiants invalides, dans `invalidIds()`.

## Comment lire ce code

### Créer : `persist()` puis `flush()`

Dans `new()` de [src/Controller/NoteController.php](../../src/Controller/NoteController.php) :

```php
$note = new Note();
$form = $this->createForm(NoteType::class, $note);
$form->handleRequest($request);

if ($form->isSubmitted() && $form->isValid()) {
    $entityManager->persist($note);
    $entityManager->flush();
```

Les deux appels ont des rôles distincts :

- `persist($note)` dit à Doctrine : « cet objet est nouveau, prends-le en charge ». **Aucune requête n'est envoyée.** C'est à ce moment que `onPrePersist()` remplit `createdAt`, voir [la fiche 1](01-orm-et-entites.md).
- `flush()` dit : « écris maintenant tout ce que tu as noté ». Doctrine envoie l'`INSERT`, PostgreSQL attribue l'identifiant, et Doctrine le range dans `$note->id`.

Après le `flush()`, `$note->getId()` renvoie donc un nombre, alors qu'il renvoyait `null` juste avant.

### Modifier : `flush()` seul

Dans `edit()` :

```php
if ($form->isSubmitted() && $form->isValid()) {
    $entityManager->flush();
```

Pas de `persist()`. La note a été chargée depuis la base, donc Doctrine la suit déjà : il a gardé en mémoire ses valeurs d'origine. Le formulaire modifie l'objet, puis `flush()` compare l'objet à ces valeurs, trouve la différence et écrit l'`UPDATE`. C'est le moment où `onPreUpdate()` remplit `updatedAt`.

Si rien n'a changé, il n'y a pas d'`UPDATE`, et `updatedAt` ne bouge pas.

### Supprimer : `remove()` puis `flush()`

Dans `delete()` :

```php
$entityManager->remove($note);
$entityManager->flush();
```

Même principe : `remove()` note la suppression, `flush()` envoie le `DELETE`.

### Le cycle de vie d'une entité

Du point de vue de l'`EntityManager`, un objet `Note` passe par plusieurs états :

| État | Sens | Comment on y arrive |
|---|---|---|
| Nouveau (_new_) | Un objet PHP que Doctrine ne connaît pas | `new Note()` |
| Géré (_managed_) | Doctrine le suit et écrira ses changements au prochain `flush()` | `persist()`, ou un chargement par `find()` ou par une requête |
| Supprimé (_removed_) | Sa suppression est prévue au prochain `flush()` | `remove()` |
| Détaché (_detached_) | Doctrine l'a oublié ; ses changements ne seront plus écrits | `clear()`, ou la fin de la requête HTTP |

`flush()` ne regarde que les entités gérées. C'est pourquoi une entité nouvelle a besoin de `persist()`, et une entité chargée non.

L'`EntityManager` garde aussi une **carte d'identité** (_identity map_) : une seule instance de chaque ligne. Demander deux fois la note 3 pendant la même requête donne le même objet, et la seconde fois, Doctrine ne refait pas la requête SQL. Les tests s'en méfient : [tests/Controller/NoteControllerTest.php](../../tests/Controller/NoteControllerTest.php) appelle `$this->manager->clear()` avant de relire une note, pour être sûr de lire la base et non la mémoire.

### Le dépôt

[src/Repository/NoteRepository.php](../../src/Repository/NoteRepository.php) a été généré par `make:entity` :

```php
/**
 * @extends ServiceEntityRepository<Note>
 */
class NoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Note::class);
    }
```

En héritant de `ServiceEntityRepository`, il reçoit des méthodes toutes prêtes :

| Méthode | Résultat |
|---|---|
| `find(3)` | La note d'identifiant 3, ou `null` |
| `findAll()` | Toutes les notes, sans ordre garanti |
| `findBy(['title' => 'Courses'])` | Les notes qui ont ce titre |
| `findOneBy(['title' => 'Courses'])` | La première, ou `null` |

Le commentaire `@extends ServiceEntityRepository<Note>` ne change rien à l'exécution. Il indique aux éditeurs et aux outils d'analyse que ce dépôt renvoie des `Note`.

### `findAllNewestFirst()` et le QueryBuilder

`make:crud` avait généré `findAll()` dans `index()`. Il ne garantit aucun ordre : la base renvoie les lignes comme elle veut. Le projet a donc ajouté une méthode au dépôt :

```php
public function findAllNewestFirst(): array
{
    return $this->createQueryBuilder('n')
        ->orderBy('n.createdAt', 'DESC')
        ->addOrderBy('n.id', 'DESC')
        ->getQuery()
        ->getResult();
}
```

Le **QueryBuilder** construit une requête pas à pas, avec des méthodes PHP :

- `createQueryBuilder('n')` part de « toutes les notes », et nomme `n` la note en cours. C'est un **alias**, comme en SQL.
- `orderBy('n.createdAt', 'DESC')` trie par date de création, la plus récente d'abord. On écrit le nom de la **propriété**, `createdAt`, et non celui de la colonne.
- `addOrderBy('n.id', 'DESC')` ajoute un second critère, en cas d'égalité sur le premier.
- `getQuery()->getResult()` exécute la requête et renvoie un tableau de `Note`.

Le QueryBuilder produit du **DQL**, le langage de requêtes de Doctrine, qui parle d'entités et de propriétés :

```text
SELECT n FROM App\Entity\Note n ORDER BY n.createdAt DESC, n.id DESC
```

Doctrine le traduit ensuite en SQL pour le moteur utilisé, avec les vrais noms de colonnes :

```sql
SELECT n0_.id AS id_0, n0_.title AS title_1, n0_.content AS content_2, n0_.created_at AS created_at_3, n0_.updated_at AS updated_at_4 FROM note n0_ ORDER BY n0_.created_at DESC, n0_.id DESC
```

Pourquoi le second critère ? La colonne `created_at` est un `TIMESTAMP(0)` : elle garde la seconde, sans fraction. Deux notes créées dans la même seconde ont exactement la même date, et leur ordre ne serait plus garanti. L'identifiant grandit à chaque insertion : à date égale, le plus grand est le plus récent. C'est précisément le cas de `testFindAllNewestFirst()`, qui crée « Première » puis « Seconde » à la suite, et attend `['Seconde', 'Première']`.

### De `{id}` à un objet `Note`

Regardez la signature de `show()` :

```php
#[Route('/{id}', name: 'app_note_show', requirements: ['id' => self::ID_PATTERN], methods: ['GET'])]
public function show(Note $note): Response
```

L'URL contient un nombre, `/note/3`, et la méthode reçoit une `Note`. Personne n'a appelé `find()` : c'est Symfony qui l'a fait. Un **résolveur de valeur** (_value resolver_) fourni par Doctrine, `EntityValueResolver`, voit un argument dont le type est une entité, trouve le paramètre `{id}` dans la route et appelle `find('3')` sur le dépôt de `Note`.

Si `find()` renvoie `null`, le résolveur lève une erreur 404. La méthode `show()` n'est jamais appelée avec une note absente : elle n'a rien à vérifier. `edit()` et `delete()` profitent du même mécanisme.

### L'exigence `[1-9]\d{0,8}`

En haut du contrôleur :

```php
// Entier positif de 9 chiffres au plus : reste dans la plage `integer` de PostgreSQL.
private const ID_PATTERN = '[1-9]\d{0,8}';
```

Cette expression régulière est une **exigence** de route (_requirement_) : `{id}` ne correspond que si sa valeur la respecte. Elle se lit ainsi :

| Morceau | Sens |
|---|---|
| `[1-9]` | Un premier chiffre de 1 à 9 : ni `0`, ni zéro en tête |
| `\d{0,8}` | Puis de zéro à huit chiffres |

Elle accepte donc de `1` à `999999999`. Le plus grand identifiant possible d'une colonne `integer` de PostgreSQL est `2147483647`, soit dix chiffres : avec neuf chiffres au plus, on reste toujours dedans.

Sans cette exigence, que se passerait-il ? Le résolveur appellerait `find()` avec n'importe quel texte, et PostgreSQL refuserait la requête. L'essai direct sur PostgreSQL 16 donne :

```text
ERROR:  invalid input syntax for type integer: "abc"
ERROR:  value "99999999999" is out of range for type integer
```

Doctrine transformerait ces refus en exception, et la page afficherait une erreur 500 : une panne du serveur, pour une simple faute de frappe dans l'URL. Avec l'exigence, le routeur ne trouve aucune route qui correspond, et répond 404 **avant** la moindre requête SQL. C'est la bonne réponse : cette page n'existe pas.

SQLite, plus tolérant, renverrait simplement « aucune ligne ». C'est un cas où les tests sur SQLite ne suffiraient pas à voir le problème. Le fournisseur `invalidIds()` de [tests/Controller/NoteControllerTest.php](../../tests/Controller/NoteControllerTest.php) vérifie quand même chaque cas, `/note/abc`, `/note/0`, `/note/99999999999`, `/note/abc/edit` et une note inexistante, `/note/999` : tous doivent répondre 404.

Les deux 404 n'ont pas la même origine, et le journal des tests le montre quand on lance `php bin/phpunit` :

```text
NotFoundHttpException: ""App\Entity\Note" object not found by "Symfony\Bridge\Doctrine\ArgumentResolver\EntityValueResolver"."
NotFoundHttpException: "No route found for "GET http://localhost/note/abc""
```

La première vient du résolveur : la route correspondait, une requête SQL est partie, et la note `999` n'existe pas. La seconde vient du routeur : aucune route ne correspond à `abc`, et aucune requête n'est partie. Ces lignes `[error]` sont normales : ce sont les erreurs que les tests provoquent exprès.

L'exigence a un second effet : `/note/new` ne peut pas être pris pour la note d'identifiant `new`.

## Commandes officielles utilisées

```bash
php bin/console debug:router app_note_show
```

Affiche le détail d'une route, dont son exigence : `id: [1-9]\d{0,8}`.

```bash
php bin/console debug:container NoteRepository
php bin/console debug:autowiring EntityManager
```

Montrent que le dépôt et l'`EntityManager` sont des services, et sous quel type on peut les recevoir.

```bash
symfony console dbal:run-sql "SELECT id, title FROM note ORDER BY created_at DESC, id DESC"
```

Rejoue à la main la requête de `findAllNewestFirst()`, sur PostgreSQL.

## À retenir

- L'`EntityManager` écrit, le dépôt lit.
- `persist()` prend en charge une entité nouvelle ; `flush()` envoie toutes les requêtes notées, dans une transaction.
- Une entité chargée est déjà gérée : la modifier puis appeler `flush()` suffit.
- Le QueryBuilder construit du DQL, qui parle de propriétés ; Doctrine le traduit en SQL.
- Un tri sur une date à la seconde a besoin d'un second critère, ici l'identifiant.
- Un argument typé `Note` est rempli par Symfony à partir de `{id}`, avec une 404 si la note n'existe pas.
- Une exigence de route bloque une valeur absurde avant qu'elle n'atteigne la base.
