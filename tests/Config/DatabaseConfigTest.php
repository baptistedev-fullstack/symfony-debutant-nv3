<?php

namespace App\Tests\Config;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DatabaseConfigTest extends KernelTestCase
{
    public function testTestsRunOnSqlite(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $this->assertInstanceOf(SQLitePlatform::class, $connection->getDatabasePlatform());
    }

    public function testServerVersionMatchesTheComposeImage(): void
    {
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');

        preg_match('/^DATABASE_URL=.*[?&]serverVersion=(\d+)/m', file_get_contents($projectDir.'/.env'), $env);
        preg_match('/image:\s*postgres:\$\{POSTGRES_VERSION:-(\d+)\}-alpine/', file_get_contents($projectDir.'/compose.yaml'), $compose);

        $this->assertNotEmpty($env, 'serverVersion introuvable dans .env.');
        $this->assertNotEmpty($compose, 'Image postgres introuvable dans compose.yaml.');
        $this->assertSame($compose[1], $env[1]);
    }
}
