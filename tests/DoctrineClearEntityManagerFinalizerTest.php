<?php

declare(strict_types=1);

namespace Vanta\Integration\Temporal\Doctrine\Test;

use Doctrine\Persistence\AbstractManagerRegistry as ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;

use function PHPUnit\Framework\assertEquals;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Vanta\Integration\Temporal\Doctrine\Finalizer\DoctrineClearEntityManagerFinalizer;

#[CoversClass(DoctrineClearEntityManagerFinalizer::class)]
final class DoctrineClearEntityManagerFinalizerTest extends TestCase
{
    public function testClearUnitOfWork(): void
    {
        $managerRegistry = new class(
            'test',
            ['test-connection' => 'test-connection'],
            ['test-em'         => 'test-em','test-em1' => 'test-em1'],
            'test-connection',
            'test-em',
            stdClass::class,
        ) extends ManagerRegistry {
            private int $countCallReset = 0;

            public function getCountCallReset(): int
            {
                return $this->countCallReset;
            }

            protected function getService(string $name): object
            {
                return new class($this->countCallReset) implements ObjectManager {
                    public function __construct(
                        private int &$callReset,
                    ) {
                    }

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
                        $this->callReset++;
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


        foreach ($managerRegistry->getManagers() as $manager) {
            $manager->clear();
        }


        assertEquals(2, $managerRegistry->getCountCallReset(), 'Incorrect number of calls reset');
    }
}
