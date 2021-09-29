<?php

declare(strict_types=1);

namespace Unitpay\Shamir\Tests;

use Unitpay\Shamir\Tables;

final class TablesTest extends TestCase
{
    public function testTables(): void
    {
        for ($i = 1; $i < 256; $i++) {
            $logV = Tables::logTable[$i];
            $expV = Tables::expTable[$logV];
            $this->assertSame($i, $expV);
        }
    }
}
