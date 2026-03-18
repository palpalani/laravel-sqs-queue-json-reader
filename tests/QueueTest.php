<?php

declare(strict_types=1);

namespace palPalani\SqsQueueReader\Tests;

use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessed;
use palPalani\SqsQueueReader\Jobs\DispatcherJob;
use palPalani\SqsQueueReader\Sqs\Queue;

use function PHPUnit\Framework\assertTrue;

/**
 * Class QueueTest
 */
class QueueTest extends TestCase
{
    /**
     * @test
     */
    public function class_named_is_derived_from_queue_name(): void
    {
        $content = [
            'test' => 'test',
        ];

        $job = new DispatcherJob($content);

        $queue = $this->getMockBuilder(Queue::class)
            ->disableOriginalConstructor()
            ->getMock();

        $method = new \ReflectionMethod(
            Queue::class,
            'createPayload'
        );

        $method->setAccessible(true);

        $method->invokeArgs($queue, [$job]);

        assertTrue(true);
    }

    /**
     * @test
     */
    public function queue_after_listener_returns_early_when_job_is_already_deleted(): void
    {
        $job = $this->createMock(Job::class);

        $job->expects($this->once())
            ->method('isDeleted')
            ->willReturn(true);

        // If the early-return guard works, getQueue() and delete() must never be reached.
        $job->expects($this->never())->method('getQueue');
        $job->expects($this->never())->method('delete');

        event(new JobProcessed('sqs-json', $job));
    }

    /**
     * @test
     */
    public function queue_after_listener_proceeds_when_job_is_not_deleted(): void
    {
        $job = $this->createMock(Job::class);

        $job->expects($this->once())
            ->method('isDeleted')
            ->willReturn(false);

        // The listener must inspect the queue URL when the job has not been deleted.
        $job->expects($this->once())
            ->method('getQueue')
            ->willReturn('https://sqs/123456789/test-queue');

        // No matching connection → the listener takes no further action, so delete() is not called.
        $job->expects($this->never())->method('delete');

        event(new JobProcessed('non-existent-connection', $job));
    }
}
