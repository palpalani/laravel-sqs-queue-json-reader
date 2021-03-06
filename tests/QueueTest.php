<?php

namespace palPalani\SqsQueueReader\Tests;

use palPalani\SqsQueueReader\Jobs\DispatcherJob;
use palPalani\SqsQueueReader\Sqs\Queue;

/**
 * Class QueueTest
 * @package palPalani\SqsQueueReader\Tests
 */
class QueueTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @test
     */
    public function class_named_is_derived_from_queue_name()
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
    }
}
