<?php

declare(strict_types=1);

namespace Vanta\Integration\Temporal\Doctrine\Test;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

use function PHPUnit\Framework\assertEquals;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger as DefaultLogger;
use ReflectionException;
use Spiral\Attributes\AttributeReader;
use Stringable;
use Temporal\Activity;
use Temporal\Activity\ActivityInfo;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\Interceptor\ActivityInbound\ActivityInput;
use Temporal\Interceptor\Header;
use Temporal\Internal\Activity\ActivityContext;
use Temporal\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Internal\Marshaller\Marshaller;
use Temporal\Worker\Transport\Goridge;
use Vanta\Integration\Temporal\Doctrine\Interceptor\PsrLoggingDoctrineOpenTransactionInterceptor;
use Vanta\Integration\Temporal\Doctrine\Test\Stub\DummyActivityContext;

#[CoversClass(PsrLoggingDoctrineOpenTransactionInterceptor::class)]
final class PsrLoggingDoctrineOpenTransactionInterceptorTest extends TestCase
{
    /**
     * @throws Exception
     * @throws ReflectionException
     */
    public function testLoggingOpenTransaction(): void
    {
        $connection = new class() extends Connection {
            private int $txLevel;

            public function __construct()
            {
                $this->txLevel = 0;
            }


            public function beginTransaction(): void
            {
                $this->txLevel++;
            }

            public function getTransactionNestingLevel(): int
            {
                return $this->txLevel;
            }
        };


        $logger = new class() extends DefaultLogger {
            public function log($level, Stringable|string $message, array $context = []): void
            {
                assertEquals('critical', $level);
                assertEquals('A activity opened a transaction but did not close it.', $message);
                assertEquals([
                    'Workflow' => [
                        'Namespace' => 'Test',
                        'Type'      => 'Test',
                        'Id'        => 'f06e87b1-5e56-4c5d-a789-3f68a7a3af14',
                    ],
                    'Activity' => [
                        'TaskQueue' => 'Test',
                        'Type'      => 'Test',
                        'Id'        => '92dbc19f-2206-4229-85b7-2ca5cb6ada4a',
                    ],
                ], $context);
            }
        };

        $activityInfo = new ActivityInfo();
        $marshaller   = new Marshaller(new AttributeMapperFactory(new AttributeReader()));
        $activityInfo = $marshaller->unmarshal([
            'ActivityID'        => '92dbc19f-2206-4229-85b7-2ca5cb6ada4a',
            'ActivityType'      => ['Name' => 'Test'],
            'TaskQueue'         => 'Test',
            'WorkflowNamespace' => 'Test',
            'WorkflowType'      => ['Name' => 'Test'],
            'WorkflowExecution' => ['ID' => 'f06e87b1-5e56-4c5d-a789-3f68a7a3af14', 'RunID' => '236a53db-3310-4e11-bd04-c13da5cf8f9d'],
        ], $activityInfo);


        Activity::setCurrentContext(
            new DummyActivityContext(
                new ActivityContext(
                    Goridge::create(),
                    DataConverter::createDefault(),
                    EncodedValues::fromValues([true, ['test' => 'test']]),
                    Header::empty()
                ),
                $activityInfo
            ),
        );

        $interceptor = new PsrLoggingDoctrineOpenTransactionInterceptor($logger, $connection);
        $input       = new ActivityInput(EncodedValues::empty(), Header::empty());
        $handler     = static function (ActivityInput $input) use ($connection): void {
            $connection->beginTransaction();
        };


        $interceptor->handleActivityInbound($input, $handler);
    }
}
