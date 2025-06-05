<?php

declare(strict_types=1);

namespace Vanta\Integration\Temporal\Doctrine\Test;

use Doctrine\DBAL\Driver\AbstractException;
use Doctrine\DBAL\Exception\ConnectionLost;
use Doctrine\DBAL\Query;
use Doctrine\ORM\Exception\EntityManagerClosed;

use function PHPUnit\Framework\assertEquals;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use Spiral\Attributes\AttributeReader;
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
use Throwable;
use Vanta\Integration\Temporal\Doctrine\Finalizer\Finalizer;
use Vanta\Integration\Temporal\Doctrine\Interceptor\DoctrineHandlerThrowsActivityInboundInterceptor;
use Vanta\Integration\Temporal\Doctrine\Test\Stub\DummyActivityContext;

#[CoversClass(DoctrineHandlerThrowsActivityInboundInterceptor::class)]
final class DoctrineHandlerThrowsActivityInboundInterceptorTest extends TestCase
{
    /**
     * @throws ReflectionException
     * @throws Throwable
     */
    public function testFinalizeIfThrowEntityManagerClosed(): void
    {
        $exception = new EntityManagerClosed('Test manager closed');

        $this->expectExceptionObject($exception);


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

        $finalizer = new class() implements Finalizer {
            public int $call = 0;


            public function finalize(): void
            {
                $this->call++;
            }
        };


        $interceptor = new DoctrineHandlerThrowsActivityInboundInterceptor($finalizer);
        $input       = new ActivityInput(EncodedValues::empty(), Header::empty());
        $handler     = static function (ActivityInput $input) use ($exception): void {
            throw $exception;
        };

        try {
            $interceptor->handleActivityInbound($input, $handler);
        } catch (Throwable $e) {
            assertEquals(1, $finalizer->call, 'Failed finalizing if throw EntityManagerClosed');

            throw $e;
        }
    }



    public function testFinalizeIfThrowDriverException(): void
    {
        $exception = new ConnectionLost(
            new class('test') extends AbstractException {},
            new Query('SELECT 1', [], [])
        );

        $this->expectExceptionObject($exception);


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

        $finalizer = new class() implements Finalizer {
            public int $call = 0;


            public function finalize(): void
            {
                $this->call++;
            }
        };


        $interceptor = new DoctrineHandlerThrowsActivityInboundInterceptor($finalizer);
        $input       = new ActivityInput(EncodedValues::empty(), Header::empty());
        $handler     = static function (ActivityInput $input) use ($exception): void {
            throw $exception;
        };

        try {
            $interceptor->handleActivityInbound($input, $handler);
        } catch (Throwable $e) {
            assertEquals(1, $finalizer->call, 'Failed finalizing if throw DriverException');

            throw $e;
        }
    }
}
