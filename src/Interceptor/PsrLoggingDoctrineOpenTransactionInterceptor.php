<?php

/**
 * Temporal Bundle
 *
 * @author Vlad Shashkov <v.shashkov@pos-credit.ru>
 * @copyright Copyright (c) 2025, The Vanta
 */

declare(strict_types=1);

namespace Vanta\Integration\Temporal\Doctrine\Interceptor;

use Composer\InstalledVersions;
use Doctrine\DBAL\Connection;
use LogicException;
use Psr\Log\LoggerInterface as Logger;
use Temporal\Activity;
use Temporal\Interceptor\ActivityInbound\ActivityInput;
use Temporal\Interceptor\ActivityInboundInterceptor;

if (!InstalledVersions::isInstalled('psr/log')) {
    throw new LogicException('You cannot use "Psr\Log\LoggerInterface" as the "psr/log" package is not installed. Try running "composer require psr/log".');
}


final readonly class PsrLoggingDoctrineOpenTransactionInterceptor implements ActivityInboundInterceptor
{
    public function __construct(
        private Logger $logger,
        private Connection $connection,
    ) {
    }

    public function handleActivityInbound(ActivityInput $input, callable $next): mixed
    {
        $initialTransactionLevel = $this->connection->getTransactionNestingLevel();

        try {
            return $next($input);
        } finally {
            if ($this->connection->getTransactionNestingLevel() > $initialTransactionLevel) {
                $this->logger->critical('A activity opened a transaction but did not close it.', [
                    'Workflow' => [
                        'Namespace' => Activity::getInfo()->workflowNamespace,
                        'Type'      => Activity::getInfo()->workflowType?->name,
                        'Id'        => Activity::getInfo()->workflowExecution?->getID(),
                    ],
                    'Activity' => [
                        'Id'        => Activity::getInfo()->id,
                        'Type'      => Activity::getInfo()->type->name,
                        'TaskQueue' => Activity::getInfo()->taskQueue,
                    ],
                ]);
            }
        }
    }
}
