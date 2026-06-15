<?php

namespace App\Support;

/**
 * Base class for all queueable jobs.
 *
 * Subclasses declare public properties for job data (serialized to DB).
 * Service dependencies are resolved from the Container when handle() runs.
 *
 * Example:
 *   class SendWelcomeEmailJob extends Job {
 *       public string $email = '';
 *       public function handle(Container $container): void {
 *           $container->get(MailService::class)->send($this->email, ...);
 *       }
 *   }
 */
abstract class Job
{
    public int $maxAttempts  = 3;
    public string $queue        = 'default';
    public int $delaySeconds = 0;

    abstract public function handle(Container $container): void;
}
