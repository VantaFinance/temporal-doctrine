<?php

/**
 * Temporal Bundle
 *
 * @author Vlad Shashkov <v.shashkov@pos-credit.ru>
 * @copyright Copyright (c) 2023, The Vanta
 */

declare(strict_types=1);

namespace Vanta\Integration\Temporal\Doctrine\Interceptor;

use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\Exception\EntityManagerClosed;
use Temporal\Interceptor\ActivityInbound\ActivityInput;
use Temporal\Interceptor\ActivityInboundInterceptor;
use Throwable;
use Vanta\Integration\Temporal\Doctrine\Finalizer\Finalizer;

final readonly class DoctrineHandlerThrowsActivityInboundInterceptor implements ActivityInboundInterceptor
{
    public function __construct(
        private Finalizer $finalizer
    ) {
    }


    /**
     * @throws Throwable
     */
    public function handleActivityInbound(ActivityInput $input, callable $next): mixed
    {
        try {
            $result = $next($input);
        } catch (Throwable $e) {
            if ($e instanceof EntityManagerClosed || $e instanceof DriverException) {
                $this->finalizer->finalize();
            }

            throw $e;
        }

        return $result;
    }
}
