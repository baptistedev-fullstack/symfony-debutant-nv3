<?php

namespace App\Tests\Repository;

use App\Entity\Note;
use App\Repository\NoteRepository;
use App\Tests\ResetDatabaseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class NoteRepositoryTest extends KernelTestCase
{
    use ResetDatabaseTrait;

    private EntityManagerInterface $entityManager;
    private NoteRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->resetDatabase();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = static::getContainer()->get(NoteRepository::class);
    }

    public function testPersistSetsCreatedAtAndNotUpdatedAt(): void
    {
        $note = $this->createNote('Titre', 'Contenu');

        $this->assertInstanceOf(\DateTimeImmutable::class, $note->getCreatedAt());
        $this->assertNull($note->getUpdatedAt());
    }

    public function testUpdateSetsUpdatedAt(): void
    {
        $note = $this->createNote('Titre', 'Contenu');

        $note->setTitle('Nouveau titre');
        $this->entityManager->flush();

        $this->assertInstanceOf(\DateTimeImmutable::class, $note->getUpdatedAt());
    }

    public function testFindAllNewestFirst(): void
    {
        // L'ancienne note est créée en second : l'ordre des id et l'ordre des dates s'opposent.
        $this->createNote('Récente', 'a');
        $old = (new Note())->setTitle('Ancienne')->setContent('b');
        $this->entityManager->persist($old);
        $old->setCreatedAt(new \DateTimeImmutable('-1 day'));
        $this->entityManager->flush();

        $titles = array_map(static fn (Note $note): ?string => $note->getTitle(), $this->repository->findAllNewestFirst());

        $this->assertSame(['Récente', 'Ancienne'], $titles);
    }

    public function testFindAllNewestFirstOnEmptyTable(): void
    {
        $this->assertSame([], $this->repository->findAllNewestFirst());
    }

    private function createNote(string $title, string $content): Note
    {
        $note = (new Note())->setTitle($title)->setContent($content);
        $this->entityManager->persist($note);
        $this->entityManager->flush();

        return $note;
    }
}
