<?php

declare(strict_types=1);

use palPalani\SqsQueueReader\Jobs\DispatcherJob;
use palPalani\SqsQueueReader\Sqs\Queue;

it('can create payload for dispatcher job with default handler', function () {
    $content = [
        'test' => 'test',
    ];

    $job = new DispatcherJob($content);

    $queue = Mockery::mock(Queue::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    $payload = $queue->createPayload($job);
    $decodedPayload = json_decode($payload, true);

    expect($payload)->toBeString()
        ->and($decodedPayload)->toBeArray()
        ->and($decodedPayload['job'])->toBe('App\Jobs\SqsHandler@handle')
        ->and($decodedPayload['data'])->toHaveKey('job')
        ->and($decodedPayload['data'])->toHaveKey('data')
        ->and($decodedPayload['data']['data'])->toBe($content);
});

it('can create plain payload for dispatcher job', function () {
    $content = [
        'test' => 'test',
    ];

    $job = new DispatcherJob($content);
    $job->setPlain(true);

    $queue = Mockery::mock(Queue::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    $payload = $queue->createPayload($job);

    expect($payload)->toBeString()
        ->and(json_decode($payload, true))->toBe($content);
});
