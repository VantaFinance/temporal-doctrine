<?php

declare(strict_types=1);

namespace Vanta\Integration\Temporal\Doctrine\Test\Stub;

use Temporal\Activity\ActivityContextInterface;
use Temporal\Activity\ActivityInfo;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Internal\Activity\ActivityContext;

final readonly class DummyActivityContext implements ActivityContextInterface
{
    public function __construct(
        private ActivityContext $context,
        private ActivityInfo $info,
    ) {
    }


    public function getInfo(): ActivityInfo
    {
        return $this->info;
    }

    public function getInput(): ValuesInterface
    {
        return $this->context->getInput();
    }

    public function hasHeartbeatDetails(): bool
    {
        return $this->context->hasHeartbeatDetails();
    }

    public function getHeartbeatDetails($type = null)
    {
        return $this->context->getHeartbeatDetails($type);
    }

    public function doNotCompleteOnReturn(): void
    {
        $this->context->doNotCompleteOnReturn();
    }

    public function heartbeat($details): void
    {
        $this->context->heartbeat($details);
    }

    public function getInstance(): object
    {
        return $this;
    }
}
