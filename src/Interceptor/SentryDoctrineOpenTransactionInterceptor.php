<?php

declare(strict_types=1);

namespace Vanta\Integration\Temporal\Doctrine\Interceptor;

use Composer\InstalledVersions;
use Doctrine\DBAL\Connection;
use LogicException;
use Sentry\Event;
use Sentry\Severity;
use Sentry\State\HubInterface as Hub;
use Temporal\Activity;
use Temporal\Interceptor\ActivityInbound\ActivityInput;
use Temporal\Interceptor\ActivityInboundInterceptor;

if (!InstalledVersions::isInstalled('sentry/sentry')) {
    throw new LogicException('You cannot use "Sentry\State\HubInterface" as the "sentry/sentry" package is not installed. Try running "composer require sentry/sentry".');
}

final readonly class SentryDoctrineOpenTransactionInterceptor implements ActivityInboundInterceptor
{
    public function __construct(
        private Hub $hub,
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
                $event = Event::createEvent();
                $event->setContext('Activity', [
                    'Id'        => Activity::getInfo()->id,
                    'Type'      => Activity::getInfo()->type->name,
                    'TaskQueue' => Activity::getInfo()->taskQueue,
                ]);
                $event->setContext('Workflow', [
                    'Namespace' => Activity::getInfo()->workflowNamespace,
                    'Type'      => Activity::getInfo()->workflowType?->name,
                    'Id'        => Activity::getInfo()->workflowExecution?->getID(),
                ]);

                $request = [];

                foreach (Activity::getInput()->getValues() as $value) {
                    $request[] = $value;
                }

                $event->setExtra([
                    'Args'    => $request,
                    'Headers' => iterator_to_array($input->header->getIterator()),
                ]);

                $event->setLevel(Severity::error());
                $event->setMessage('A activity opened a transaction but did not close it.');

                $this->hub->captureEvent($event);
            }
        }
    }
}
