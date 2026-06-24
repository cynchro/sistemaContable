<?php

namespace Tests\Unit\Support;

use App\Support\JobDispatcher;
use Tests\Unit\Support\Fixtures\SimpleJob;
use Tests\Unit\UnitTestCase;

class JobDispatcherTest extends UnitTestCase
{
    private function makePdo(?\PDOStatement $stmt = null): \PDO
    {
        $pdo = $this->createMock(\PDO::class);
        if ($stmt !== null) {
            $pdo->method('prepare')->willReturn($stmt);
        }
        return $pdo;
    }

    private function makeStmt(int $rowCount = 1): \PDOStatement
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn($rowCount);
        return $stmt;
    }

    public function test_dispatch_inserts_job_with_serialized_payload(): void
    {
        $captured = null;
        $stmt     = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturnCallback(function (array $params) use (&$captured) {
            $captured = $params;
            return true;
        });

        $dispatcher   = new JobDispatcher($this->makePdo($stmt));
        $job          = new SimpleJob();
        $job->email   = 'test@example.com';
        $job->userId  = 42;
        $dispatcher->dispatch($job);

        $this->assertNotNull($captured);
        $this->assertSame('default', $captured[0]);

        $payload = json_decode($captured[1], true);
        $this->assertSame(SimpleJob::class, $payload['class']);
        $this->assertSame('test@example.com', $payload['data']['email']);
        $this->assertSame(42, $payload['data']['userId']);
    }

    public function test_dispatch_respects_custom_queue_and_delay(): void
    {
        $captured = null;
        $stmt     = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturnCallback(function (array $p) use (&$captured) {
            $captured = $p;
            return true;
        });

        $dispatcher       = new JobDispatcher($this->makePdo($stmt));
        $job              = new SimpleJob();
        $job->queue       = 'emails';
        $job->delaySeconds = 60;
        $dispatcher->dispatch($job);

        $this->assertSame('emails', $captured[0]);
        $this->assertGreaterThan(date('Y-m-d H:i:s'), $captured[3]);
    }

    public function test_dispatch_does_not_serialize_framework_props(): void
    {
        $captured = null;
        $stmt     = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturnCallback(function (array $p) use (&$captured) {
            $captured = $p;
            return true;
        });

        (new JobDispatcher($this->makePdo($stmt)))->dispatch(new SimpleJob());

        $payload = json_decode($captured[1], true);
        $this->assertArrayNotHasKey('maxAttempts', $payload['data']);
        $this->assertArrayNotHasKey('queue', $payload['data']);
        $this->assertArrayNotHasKey('delaySeconds', $payload['data']);
    }

    public function test_claim_returns_null_when_no_jobs_available(): void
    {
        $stmt = $this->makeStmt(rowCount: 0);
        $this->assertNull((new JobDispatcher($this->makePdo($stmt)))->claim());
    }

    public function test_complete_deletes_job_row(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with([99]);
        (new JobDispatcher($this->makePdo($stmt)))->complete(99);
    }

    public function test_fail_marks_job_as_failed_when_max_attempts_reached(): void
    {
        $captured = null;
        $stmt     = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturnCallback(function (array $p) use (&$captured) {
            $captured = $p;
            return true;
        });

        $jobRow = ['id' => 1, 'attempts' => 3, 'max_attempts' => 3];
        (new JobDispatcher($this->makePdo($stmt)))->fail($jobRow, new \RuntimeException('boom'));

        $this->assertSame(1, end($captured));
    }

    public function test_retry_returns_true_when_job_reset(): void
    {
        $this->assertTrue((new JobDispatcher($this->makePdo($this->makeStmt(1))))->retry(7));
    }

    public function test_retry_returns_false_when_job_not_found(): void
    {
        $this->assertFalse((new JobDispatcher($this->makePdo($this->makeStmt(0))))->retry(99));
    }
}
