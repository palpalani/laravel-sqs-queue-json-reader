<?php

namespace palPalani\SqsQueueReader\Tests;

use Aws\Sqs\SqsClient;
use palPalani\SqsQueueReader\Jobs\DispatcherJob;
use palPalani\SqsQueueReader\Sqs\Queue;

/**
 * Class QueueTest
 * @package palPalani\SqsQueueReader\Tests
 */
class QueueTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function class_named_is_derived_from_queue_name()
    {

        $content = [
            'test' => 'test'
        ];

        $job = new DispatcherJob($content);

        $queue = $this->getMockBuilder(Queue::class)
            ->disableOriginalConstructor()
            ->getMock();

        $method = new \ReflectionMethod(
            'palPalani\SqsQueueReader\Sqs\Queue', 'createPayload'
        );

        $method->setAccessible(true);

        //$response = $method->invokeArgs($queue, [$job]);
    }
}
