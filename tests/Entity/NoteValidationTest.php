<?php

namespace App\Tests\Entity;

use App\Entity\Note;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class NoteValidationTest extends KernelTestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidNotes(): iterable
    {
        yield 'titre vide' => ['', 'x', 'Le titre est obligatoire.'];
        yield 'titre fait d\'espaces' => ['   ', 'x', 'Le titre est obligatoire.'];
        yield 'titre trop long' => [str_repeat('a', 121), 'x', 'Le titre ne doit pas dépasser 120 caractères.'];
        yield 'contenu vide' => ['x', '', 'Le contenu est obligatoire.'];
        yield 'contenu trop long' => ['x', str_repeat('a', 10001), 'Le contenu ne doit pas dépasser 10000 caractères.'];
    }

    #[DataProvider('invalidNotes')]
    public function testInvalidNoteIsRejected(string $title, string $content, string $message): void
    {
        $violations = $this->validator()->validate((new Note())->setTitle($title)->setContent($content));

        $this->assertCount(1, $violations);
        $this->assertSame($message, $violations[0]->getMessage());
    }

    public function testLimitsAreAccepted(): void
    {
        $note = (new Note())->setTitle(str_repeat('a', 120))->setContent(str_repeat('a', 10000));

        $this->assertCount(0, $this->validator()->validate($note));
    }

    private function validator(): ValidatorInterface
    {
        return static::getContainer()->get('validator');
    }
}
