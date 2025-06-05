# Temporal Doctrine

[Temporal](https://temporal.io/) is the simple, scalable open source way to write and run reliable cloud applications.


## Introduction

Doctrine ORM for [`temporalio/sdk-php`](https://github.com/temporalio/sdk-php)



## Installation


```bash
composer require vanta/temporal-doctrine
```



## Usage


#### Report open transaction to sentry

```php
<?php

declare(strict_types=1);

use Sentry\SentrySdk;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\WorkerFactory;
use Vanta\Integration\Temporal\Doctrine\Interceptor\SentryDoctrineOpenTransactionInterceptor;
use function Sentry\init;

require_once __DIR__ . '/vendor/autoload.php';

init(['dsn' => 'https://1a36864711324ed8a04ba0fa2c89ac5a@sentry.temporal.local/52']);

$hub     = SentrySdk::getCurrentHub();
$client  = $hub->getClient() ?? throw new \RuntimeException('Not Found client');
$factory = WorkerFactory::create();

$worker = $factory->newWorker(
    interceptorProvider: new SimplePipelineProvider([
        new SentryDoctrineOpenTransactionInterceptor($hub, $client->getStacktraceBuilder()),
    ])
);
```


#### Report open transaction to monolog

```php
<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\ORMSetup;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\WorkerFactory;
use Vanta\Integration\Temporal\Doctrine\Interceptor\PsrLoggingDoctrineOpenTransactionInterceptor;

require_once __DIR__ . '/vendor/autoload.php';

$config = ORMSetup::createAttributeMetadataConfiguration(
    paths: [__DIR__ . '/src'],
    isDevMode: true,
);
$connection = DriverManager::getConnection([
    'driver' => 'pdo_sqlite',
    'path'   => __DIR__ . '/db.sqlite',
], $config);


$logger = new Logger('stdout-logger');
$logger->pushHandler(new StreamHandler('php://stderr', Level::Debug));

$factory = WorkerFactory::create();

$worker = $factory->newWorker(
    interceptorProvider: new SimplePipelineProvider([
        new PsrLoggingDoctrineOpenTransactionInterceptor($logger, $connection),
    ])
);
```



#### Clear UnitOfWork and reconnect db connection if connection lost

```php
<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\Persistence\AbstractManagerRegistry as ManagerRegistry;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\WorkerFactory;
use Vanta\Integration\Temporal\Doctrine\Finalizer\DoctrineClearEntityManagerFinalizer;
use Vanta\Integration\Temporal\Doctrine\Finalizer\DoctrinePingConnectionFinalizer;
use Vanta\Integration\Temporal\Doctrine\Interceptor\DoctrineHandlerThrowsActivityInboundInterceptor;

require_once __DIR__ . '/vendor/autoload.php';

$config = ORMSetup::createAttributeMetadataConfiguration(
    paths: [__DIR__ . '/src'],
    isDevMode: true,
);
$connection = DriverManager::getConnection([
    'driver' => 'pdo_sqlite',
    'path'   => __DIR__ . '/db.sqlite',
], $config);

$entityManager = new EntityManager($connection, $config);


$managerRegistry = new class(
    'test',
    ['default-connection' => 'default-connection'],
    ['default-manager' => 'default-manager'],
    'default-connection',
    'default-manager',
    \stdClass::class,
    ['default-connection' => $connection, 'default-manager' => $entityManager],
) extends ManagerRegistry {
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
};

$doctrinePingConnectionFinalizer     = new DoctrinePingConnectionFinalizer($managerRegistry, 'default-manager');
$doctrineClearEntityManagerFinalizer = new DoctrineClearEntityManagerFinalizer($managerRegistry);

$finalizers = [
    $doctrinePingConnectionFinalizer,
    $doctrineClearEntityManagerFinalizer,
];

$factory = WorkerFactory::create();

$worker = $factory->newWorker(
    interceptorProvider: new SimplePipelineProvider([
        new DoctrineHandlerThrowsActivityInboundInterceptor($doctrinePingConnectionFinalizer),
    ])
);


$worker->registerActivityFinalizer(function () use ($finalizers): void {
    foreach ($finalizers as $finalizer) {
        try {
            $finalizer->finalize();
        } catch (\Throwable) {
            continue;
        }
    }
});
```




