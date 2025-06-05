<?php

declare(strict_types=1);

namespace Vanta\Integration\Temporal\Doctrine\Test;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractException;
use Doctrine\DBAL\Driver\Result as DriverResult;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\ConnectionLost;
use Doctrine\DBAL\Query;
use Doctrine\DBAL\Result;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\Persistence\AbstractManagerRegistry as ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use InvalidArgumentException;

use function PHPUnit\Framework\assertArrayHasKey;
use function PHPUnit\Framework\assertContains;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertInstanceOf;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger as Logger;
use RuntimeException;
use stdClass;
use Stringable;
use Throwable;
use Vanta\Integration\Temporal\Doctrine\Finalizer\DoctrinePingConnectionFinalizer;

#[CoversClass(DoctrinePingConnectionFinalizer::class)]
final class DoctrinePingConnectionFinalizerTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testReconnectIfLostConnection(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/src'],
            isDevMode: true,
        );

        $connection = DriverManager::getConnection([
            'driver'       => 'pdo_sqlite',
            'wrapperClass' => ConnectionWithLost::class,
        ], $config);

        $entityManager   = new EntityManager($connection, $config);
        $managerRegistry = new DummyManagerRegistry(
            'test',
            ['test-connection' => 'test-connection'],
            ['test-em'         => 'test-em'],
            'test-connection',
            'test-em',
            stdClass::class,
            ['test-em' => $entityManager,  'test-connection' => $connection]
        );

        (new DoctrinePingConnectionFinalizer($managerRegistry, 'test-em'))->finalize();

        $wrapperConnection = $entityManager->getConnection();

        assertInstanceOf(ConnectionWithLost::class, $wrapperConnection);
        assertEquals(2, $wrapperConnection->countExecQuery, 'Retry connection failed');
    }


    public function testClosedEntityManager(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/src'],
            isDevMode: true,
        );

        $connection      = DriverManager::getConnection(['driver' => 'pdo_sqlite'], $config);
        $entityManager   = new EntityManager($connection, $config);
        $managerRegistry = new class(
            'test',
            ['test-connection' => 'test-connection'],
            ['test-em'         => 'test-em'],
            'test-connection',
            'test-em',
            stdClass::class,
            ['test-em' => $entityManager,  'test-connection' => $connection]
        ) extends DummyManagerRegistry {
            public int $countResetManager = 0;

            public function resetManager(?string $name = null): ObjectManager
            {
                $this->countResetManager++;

                return parent::resetManager($name);
            }
        };

        $entityManager->close();

        (new DoctrinePingConnectionFinalizer($managerRegistry, 'test-em'))->finalize();

        assertEquals(1, $managerRegistry->countResetManager, 'Reset entity manager failed');
    }


    public function testFailedFinalizeIfFailedGetService(): void
    {
        $managerRegistry = new class(
            'test',
            ['test-connection' => 'test-connection'],
            ['test-em'         => 'test-em'],
            'test-connection',
            'test-em',
            stdClass::class,
        ) extends ManagerRegistry {
            protected function getService(string $name): object
            {
                throw new InvalidArgumentException(sprintf('Not found service: %s', $name));
            }

            protected function resetService(string $name): void
            {
                // TODO: Implement resetService() method.
            }
        };


        $logger = new class() extends Logger {
            public function log($level, Stringable|string $message, array $context = []): void
            {
                assertEquals('error', $level);
                assertEquals('Failed to initialize Doctrine Ping connection, reason: Not found service: test-em', $message);

                assertArrayHasKey('e', $context);
                assertInstanceOf(Throwable::class, $context['e']);
                assertEquals('Not found service: test-em', $context['e']->getMessage());
            }
        };


        (new DoctrinePingConnectionFinalizer($managerRegistry, 'test-em', $logger))->finalize();
    }

    public function testFailedFinalizeIfIsNotInstanceOfEntityManager(): void
    {
        $managerRegistry = new class(
            'test',
            ['test-connection' => 'test-connection'],
            ['test-em'         => 'test-em'],
            'test-connection',
            'test-em',
            stdClass::class,
        ) extends ManagerRegistry {
            protected function getService(string $name): object
            {
                return new class() implements ObjectManager {
                    public function find(string $className, mixed $id): object|null
                    {
                        // TODO: Implement find() method.
                    }

                    public function persist(object $object): void
                    {
                        // TODO: Implement persist() method.
                    }

                    public function remove(object $object): void
                    {
                        // TODO: Implement remove() method.
                    }

                    public function clear(): void
                    {
                        // TODO: Implement clear() method.
                    }

                    public function detach(object $object): void
                    {
                        // TODO: Implement detach() method.
                    }

                    public function refresh(object $object): void
                    {
                        // TODO: Implement refresh() method.
                    }

                    public function flush(): void
                    {
                        // TODO: Implement flush() method.
                    }

                    public function getRepository(string $className): ObjectRepository
                    {
                        // TODO: Implement getRepository() method.
                    }

                    public function getClassMetadata(string $className): ClassMetadata
                    {
                        // TODO: Implement getClassMetadata() method.
                    }

                    public function getMetadataFactory(): ClassMetadataFactory
                    {
                        // TODO: Implement getMetadataFactory() method.
                    }

                    public function initializeObject(object $obj): void
                    {
                        // TODO: Implement initializeObject() method.
                    }

                    public function isUninitializedObject(mixed $value): bool
                    {
                        // TODO: Implement isUninitializedObject() method.
                    }

                    public function contains(object $object): bool
                    {
                        // TODO: Implement contains() method.
                    }
                };
            }

            protected function resetService(string $name): void
            {
                // TODO: Implement resetService() method.
            }
        };


        $logger = new class() extends Logger {
            public function log($level, Stringable|string $message, array $context = []): void
            {
                assertEquals('error', $level);
                assertEquals('Failed to initialize Doctrine Ping connection, reason: Entity Manager must be an instance of Doctrine\ORM\EntityManagerInterface', $message);
            }
        };


        (new DoctrinePingConnectionFinalizer($managerRegistry, 'test-em', $logger))->finalize();
    }
}


final class ConnectionWithLost extends Connection
{
    public int $countExecQuery = 0;

    public function executeQuery(string $sql, array $params = [], array $types = [], ?QueryCacheProfile $qcp = null): Result
    {
        $this->countExecQuery++;

        assertEquals('SELECT 1', $sql);
        assertContains($this->countExecQuery, [1,2], 'Retry connection failed');

        if ($this->countExecQuery == 1) {
            throw new ConnectionLost(
                new class('test') extends AbstractException {},
                new Query('SELECT 1', [], [])
            );
        }

        return new Result(
            new DummyDriverResult(),
            $this,
        );
    }
}


final class DummyDriverResult implements DriverResult
{
    public function fetchNumeric(): array|false
    {
        return [1];
    }

    public function fetchAssociative(): array|false
    {
        return [];
    }

    public function fetchOne(): mixed
    {
        return null;
    }

    public function fetchAllNumeric(): array
    {
        return [];
    }

    public function fetchAllAssociative(): array
    {
        return [];
    }

    public function fetchFirstColumn(): array
    {
        return [];
    }

    public function rowCount(): int|string
    {
        return 1;
    }

    public function columnCount(): int
    {
        return 1;
    }

    public function free(): void
    {
        // TODO: Implement free() method.
    }

    public function __call(string $name, array $arguments): void
    {
        // TODO: Implement @method string getColumnName(int $index)
    }
}

class DummyManagerRegistry extends ManagerRegistry
{
    /**
     * @param array<string, string> $connections
     * @param array<string, string> $managers
     * @phpstan-param class-string $proxyInterfaceName
     * @param array<non-empty-string, object> $services
     */
    public function __construct(
        string $name,
        array $connections,
        array $managers,
        string $defaultConnection,
        string $defaultManager,
        string $proxyInterfaceName,
        private readonly array $services
    ) {
        parent::__construct(
            $name,
            $connections,
            $managers,
            $defaultConnection,
            $defaultManager,
            $proxyInterfaceName
        );
    }

    protected function getService(string $name): object
    {
        return $this->services[$name] ?? throw new RuntimeException(sprintf('Service "%s" not found', $name));
    }

    protected function resetService(string $name): void
    {
        // TODO: Implement resetService() method.
    }
}
