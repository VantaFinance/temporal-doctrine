<?php

declare(strict_types=1);

namespace Vanta\Integration\Temporal\Doctrine\Finalizer;

interface Finalizer
{
    public function finalize(): void;
}
