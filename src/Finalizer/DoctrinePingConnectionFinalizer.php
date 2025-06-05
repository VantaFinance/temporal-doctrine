<?php

/**
 * Temporal Bundle
 *
 * @author Vlad Shashkov <v.shashkov@pos-credit.ru>
 * @copyright Copyright (c) 2023, The Vanta
 */

declare(strict_types=1);

namespace Vanta\Integration\Temporal\Doctrine\Finalizer;

use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface as EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
use Psr\Log\LoggerInterface as Logger;

final readonly class DoctrinePingConnectionFinalizer implements Finalizer
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private string $entityManagerName,
        private ?Logger $logger = null,
    ) {
    }


    /**
     * @throws DBALException
     */
    public function finalize(): void
    {
        try {
            $entityManager = $this->managerRegistry->getManager($this->entityManagerName);
        } catch (InvalidArgumentException $e) {
            $this->logger?->error(sprintf('Failed to initialize Doctrine Ping connection, reason: %s', $e->getMessage()), [
                'e' => $e,
            ]);


            return;
        }

        if (!$entityManager instanceof EntityManager) {
            $this->logger?->error(
                sprintf('Failed to initialize Doctrine Ping connection, reason: %s', 'Entity Manager must be an instance of Doctrine\ORM\EntityManagerInterface')
            );


            return;
        }

        $connection = $entityManager->getConnection();

        try {
            $connection->executeQuery($connection->getDatabasePlatform()->getDummySelectSQL());
        } catch (DBALException) {
            $connection->close();

            // Attempt to reestablish the lazy connection by sending another query.
            $connection->executeQuery($connection->getDatabasePlatform()->getDummySelectSQL());
        }

        if (!$entityManager->isOpen()) {
            $this->managerRegistry->resetManager($this->entityManagerName);
        }
    }
}
