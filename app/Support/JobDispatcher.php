<?php

namespace App\Support;

use App\Exceptions\DatabaseException;

class JobDispatcher
{
    /** Properties on Job base class — never serialized as data */
    private const FRAMEWORK_PROPS = ['maxAttempts', 'queue', 'delaySeconds'];

    public function __construct(private \PDO $pdo)
    {
    }

    /**
     * Serialize a Job instance and insert it into the jobs table.
     * Public properties (excluding framework props) are stored as the job's data payload.
     */
    public function dispatch(Job $job): void
    {
        $payload = json_encode([
            'class' => get_class($job),
            'data'  => $this->extractData($job),
        ]);

        if ($payload === false) {
            throw new DatabaseException('Failed to serialize job payload.');
        }

        $availableAt = date('Y-m-d H:i:s', time() + max(0, $job->delaySeconds));

        $stmt = $this->pdo->prepare(
            'INSERT INTO jobs (queue, payload, max_attempts, available_at) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$job->queue, $payload, $job->maxAttempts, $availableAt]);
    }

    /**
     * Claim the next available job from the given queue.
     * Uses a UUID-based atomic claim to prevent double-processing.
     *
     * @return array<string, mixed>|null
     */
    public function claim(string $queue = 'default'): ?array
    {
        $claimId = UUIDGenerator::v4();

        $stmt = $this->pdo->prepare(
            'UPDATE jobs
             SET status = "running", reserved_at = NOW(), reserved_by = ?, attempts = attempts + 1
             WHERE status = "pending"
               AND queue = ?
               AND available_at <= NOW()
             ORDER BY available_at ASC
             LIMIT 1'
        );
        $stmt->execute([$claimId, $queue]);

        if ($stmt->rowCount() === 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM jobs WHERE reserved_by = ? AND status = "running" LIMIT 1'
        );
        $stmt->execute([$claimId]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /** Mark a job as completed and remove it from the table. */
    public function complete(int $jobId): void
    {
        $this->pdo->prepare('DELETE FROM jobs WHERE id = ?')->execute([$jobId]);
    }

    /**
     * Handle a job failure. Re-queues if attempts remain, otherwise marks as failed.
     *
     * @param array<string, mixed> $jobRow
     */
    public function fail(array $jobRow, \Throwable $error): void
    {
        if ((int) $jobRow['attempts'] >= (int) $jobRow['max_attempts']) {
            $stmt = $this->pdo->prepare(
                'UPDATE jobs SET status = "failed", failed_at = NOW(), error = ?, reserved_by = NULL WHERE id = ?'
            );
            $stmt->execute([
                substr($error->getMessage() . "\n" . $error->getTraceAsString(), 0, 65535),
                $jobRow['id'],
            ]);
            return;
        }

        // Exponential back-off: 2^attempts seconds
        $backOff     = (int) pow(2, (int) $jobRow['attempts']);
        $availableAt = date('Y-m-d H:i:s', time() + $backOff);

        $stmt = $this->pdo->prepare(
            'UPDATE jobs
             SET status = "pending", reserved_by = NULL, reserved_at = NULL, available_at = ?
             WHERE id = ?'
        );
        $stmt->execute([$availableAt, $jobRow['id']]);
    }

    /**
     * Reset jobs stuck in "running" status for longer than $timeoutMinutes.
     * Call at worker startup to recover from hard crashes.
     */
    public function releaseStuck(int $timeoutMinutes = 10): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE jobs
             SET status = "pending", reserved_by = NULL, reserved_at = NULL
             WHERE status = "running"
               AND reserved_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)'
        );
        $stmt->execute([$timeoutMinutes]);
        return $stmt->rowCount();
    }

    /** @return array<string, mixed>|null */
    public function restoreJob(int $jobId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM jobs WHERE id = ? LIMIT 1');
        $stmt->execute([$jobId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /** @return array<string, mixed> */
    public function failedJobs(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, queue, payload, attempts, failed_at, error FROM jobs
             WHERE status = "failed"
             ORDER BY failed_at DESC
             LIMIT ?'
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Retry a failed job by resetting it to pending. */
    public function retry(int $jobId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE jobs
             SET status = "pending", attempts = 0, failed_at = NULL, error = NULL,
                 reserved_by = NULL, reserved_at = NULL, available_at = NOW()
             WHERE id = ? AND status = "failed"'
        );
        $stmt->execute([$jobId]);
        return $stmt->rowCount() > 0;
    }

    /** @return array<string, mixed> */
    private function extractData(Job $job): array
    {
        $data = [];
        $ref  = new \ReflectionClass($job);

        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            if (!in_array($prop->getName(), self::FRAMEWORK_PROPS, true)) {
                $data[$prop->getName()] = $prop->getValue($job);
            }
        }

        return $data;
    }
}
