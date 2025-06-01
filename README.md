# Temporal Doctrine

[Temporal](https://temporal.io/) is the simple, scalable open source way to write and run reliable cloud applications.


## Introduction

Doctrine ORM for [`temporalio/sdk-php`](https://github.com/temporalio/sdk-php)



## Installation


```bash
composer require vanta/temporal-doctrine
```



## Usage


### Report open transaction to sentry

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


### Report open transaction to monolog



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
use Vanta\Integration\Temporal\Doctrine\Interceptor\MonologDoctrineOpenTransactionInterceptor;

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
        new MonologDoctrineOpenTransactionInterceptor($logger, $connection),
    ])
);
```



### Clear UnitOfWork and reconnect db connection if connection lost


```php
<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\Persistence\AbstractManagerRegistry;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\WorkerFactory;
use Vanta\Integration\Temporal\Doctrine\Finalizer\DoctrineClearEntityManagerFinalizer;
use Vanta\Integration\Temporal\Doctrine\Finalizer\DoctrinePingConnectionFinalizer;
use Vanta\Integration\Temporal\Doctrine\Interceptor\DoctrineActivityInboundInterceptor;

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
    ['default' => $connection],
    ['default' => $entityManager],
    'default',
    'default',
    'test'
) extends AbstractManagerRegistry {
    protected function getService(string $name): object
    {
        // TODO: Implement resetService() method.
    }

    protected function resetService(string $name): void
    {
        // TODO: Implement resetService() method.
    }
};

$doctrinePingConnectionFinalizer     = new DoctrinePingConnectionFinalizer($managerRegistry, 'test');
$doctrineClearEntityManagerFinalizer = new DoctrineClearEntityManagerFinalizer($managerRegistry);

$finalizers = [
    $doctrinePingConnectionFinalizer,
    $doctrineClearEntityManagerFinalizer,
];

$factory = WorkerFactory::create();

$worker = $factory->newWorker(
    interceptorProvider: new SimplePipelineProvider([
        new DoctrineActivityInboundInterceptor($doctrinePingConnectionFinalizer),
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




