<?php

namespace App\Tests\Controller;

use App\Entity\Note;
use App\Tests\ResetDatabaseTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class NoteControllerTest extends WebTestCase
{
    use ResetDatabaseTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $manager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $this->manager = static::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidSubmissions(): iterable
    {
        yield 'titre vide' => ['', 'x', 'Le titre est obligatoire.'];
        yield 'titre fait d\'espaces' => ['   ', 'x', 'Le titre est obligatoire.'];
        yield 'titre trop long' => [str_repeat('a', 121), 'x', 'Le titre ne doit pas dépasser 120 caractères.'];
        yield 'contenu vide' => ['x', '', 'Le contenu est obligatoire.'];
        yield 'contenu trop long' => ['x', str_repeat('a', 10001), 'Le contenu ne doit pas dépasser 10000 caractères.'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIds(): iterable
    {
        yield 'inexistant' => ['/note/999'];
        yield 'texte' => ['/note/abc'];
        yield 'zéro' => ['/note/0'];
        yield 'hors plage integer' => ['/note/99999999999'];
        yield 'texte à modifier' => ['/note/abc/edit'];
    }

    public function testHomeRedirectsToTheNotes(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/note');
    }

    public function testEmptyListShowsAMessage(): void
    {
        $this->client->request('GET', '/note');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Carnet de notes');
        self::assertSelectorTextContains('main', 'Aucune note pour le moment.');
    }

    public function testListShowsNewestFirst(): void
    {
        $this->createNote('Première', 'a');
        $this->createNote('Seconde', 'b');

        $this->client->request('GET', '/note');

        self::assertSelectorTextContains('.notes li:first-child', 'Seconde');
        self::assertSelectorTextContains('.notes li:last-child', 'Première');
    }

    public function testCreateNote(): void
    {
        $this->client->request('GET', '/note/new');
        $this->client->submitForm('Enregistrer', [
            'note[title]' => 'Courses',
            'note[content]' => 'Pain',
        ]);

        self::assertResponseStatusCodeSame(303);
        self::assertResponseRedirects('/note');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="status"]', 'Note créée.');
        $notes = $this->manager->getRepository(Note::class)->findAll();
        self::assertCount(1, $notes);
        self::assertSame('Courses', $notes[0]->getTitle());
    }

    #[DataProvider('invalidSubmissions')]
    public function testInvalidNoteIsNotSaved(string $title, string $content, string $message): void
    {
        $this->client->request('GET', '/note/new');
        $this->client->submitForm('Enregistrer', [
            'note[title]' => $title,
            'note[content]' => $content,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', $message);
        self::assertCount(0, $this->manager->getRepository(Note::class)->findAll());
    }

    public function testShowNote(): void
    {
        $note = $this->createNote('Courses', 'Pain et lait');

        $this->client->request('GET', '/note/'.$note->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Courses');
        self::assertSelectorTextContains('main', 'Pain et lait');
    }

    public function testMultilineContentKeepsItsLines(): void
    {
        $note = $this->createNote('Liste', 'a'."\n".'b');

        $this->client->request('GET', '/note/'.$note->getId());

        self::assertStringContainsString('<br />', $this->client->getResponse()->getContent());
    }

    public function testHtmlIsEscaped(): void
    {
        $note = $this->createNote('<script>alert(1)</script>', '<script>alert(1)</script>');

        foreach (['/note', '/note/'.$note->getId()] as $path) {
            $this->client->request('GET', $path);

            self::assertResponseIsSuccessful();
            self::assertStringNotContainsString('<script>', $this->client->getResponse()->getContent());
        }
    }

    #[DataProvider('invalidIds')]
    public function testInvalidIdIsNotFound(string $path): void
    {
        $this->client->request('GET', $path);

        self::assertResponseStatusCodeSame(404);
    }

    public function testEditNote(): void
    {
        $note = $this->createNote('Avant', 'x');
        $id = $note->getId();

        $this->client->request('GET', '/note/'.$id.'/edit');
        $this->client->submitForm('Mettre à jour', ['note[title]' => 'Après']);

        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="status"]', 'Note modifiée.');
        $this->manager->clear();
        $saved = $this->manager->find(Note::class, $id);
        self::assertSame('Après', $saved->getTitle());
        self::assertNotNull($saved->getUpdatedAt());
    }

    public function testDeleteNote(): void
    {
        $note = $this->createNote('À supprimer', 'x');

        $this->client->request('GET', '/note/'.$note->getId());
        $this->client->submitForm('Supprimer');

        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="status"]', 'Note supprimée.');
        self::assertCount(0, $this->manager->getRepository(Note::class)->findAll());
    }

    public function testDeleteWithAnInvalidTokenKeepsTheNote(): void
    {
        $note = $this->createNote('À garder', 'x');

        $this->client->request('POST', '/note/'.$note->getId(), ['_token' => 'faux']);

        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'La note n\'a pas été supprimée : le formulaire a expiré, réessayez.');
        self::assertCount(1, $this->manager->getRepository(Note::class)->findAll());
    }

    public function testDeleteWithGetIsNotAllowed(): void
    {
        $note = $this->createNote('À garder', 'x');

        $this->client->request('GET', '/note/'.$note->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'À garder');
        $this->manager->clear();
        self::assertCount(1, $this->manager->getRepository(Note::class)->findAll());
    }

    private function createNote(string $title, string $content): Note
    {
        $note = (new Note())->setTitle($title)->setContent($content);
        $this->manager->persist($note);
        $this->manager->flush();

        return $note;
    }
}
