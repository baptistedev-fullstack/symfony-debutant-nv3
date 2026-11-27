<?php

namespace App\Tests\Routing;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouterInterface;

/**
 * Vérifie les exigences de route sans passer par la base.
 *
 * Sur SQLite, un identifiant comme « abc » donne simplement « aucune ligne », donc une 404,
 * même sans exigence. Seul le routeur montre que ces adresses sont refusées avant Doctrine,
 * comme il le faut pour PostgreSQL.
 */
class NoteRoutesTest extends KernelTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectedPaths(): iterable
    {
        yield 'texte' => ['/note/abc'];
        yield 'zéro' => ['/note/0'];
        yield 'hors plage integer' => ['/note/99999999999'];
        yield 'zéro en tête' => ['/note/0123'];
        yield 'texte à modifier' => ['/note/abc/edit'];
    }

    #[DataProvider('rejectedPaths')]
    public function testInvalidIdMatchesNoRoute(string $path): void
    {
        $router = static::getContainer()->get(RouterInterface::class);

        $this->expectException(ResourceNotFoundException::class);
        $router->match($path);
    }

    public function testLargestAcceptedIdMatchesTheShowRoute(): void
    {
        $router = static::getContainer()->get(RouterInterface::class);

        $this->assertSame('app_note_show', $router->match('/note/999999999')['_route']);
    }
}
