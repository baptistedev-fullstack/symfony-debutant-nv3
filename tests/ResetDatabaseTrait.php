<?php

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

trait ResetDatabaseTrait
{
    private function resetDatabase(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        $entityManager->clear();
    }
}
