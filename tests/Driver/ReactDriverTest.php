<?php

declare(strict_types=1);

namespace Flow\Test\Driver;

use Flow\Driver\ReactDriver;
use Flow\DriverInterface;

class ReactDriverTest extends DriverTestCase
{
    protected function createDriver(): DriverInterface
    {
        return new ReactDriver();
    }
}
