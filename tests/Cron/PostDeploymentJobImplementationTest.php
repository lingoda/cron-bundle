<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\Tests\Cron;

use Carbon\Carbon;
use Lingoda\CronBundle\Cron\PostDeploymentJob;
use PHPUnit\Framework\TestCase;

class PostDeploymentJobImplementationTest extends TestCase
{
    public function testItExecutesOnlyIfNoLastStartTimeIsProvided(): void
    {
        $job = new class() extends PostDeploymentJob {
            private int $counter = 0;

            public function execute(): void
            {
                ++$this->counter;
            }

            public function getCounter(): int
            {
                return $this->counter;
            }
        };

        $job->run(null);
        $this->assertSame(1, $job->getCounter());
        $job->run(Carbon::now());
        $this->assertSame(1, $job->getCounter());
    }
}
