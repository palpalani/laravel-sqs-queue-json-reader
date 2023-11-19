<?php

declare(strict_types=1);

namespace palPalani\SqsQueueReader\Sqs;

use Aws\Exception\AwsException;
use Illuminate\Queue\Jobs\SqsJob;
use Illuminate\Queue\SqsQueue;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use JsonException;
use palPalani\SqsQueueReader\Jobs\DispatcherJob;

/**
 * Class CustomSqsQueue
 */
class Queue extends SqsQueue
{
    /**
     * Create a payload string from the given job and data.
     *
     * @param  object|string  $job
     * @param  string  $queue
     * @param  mixed  $data
     * @return string
     *
     * @throws JsonException
     */
    protected function createPayload($job, $queue = null, $data = ''): string
    {
        if (! $job instanceof DispatcherJob) {
            return parent::createPayload($job, $queue, $data);
        }

        $handlerJob = $this->getClass($queue).'@handle';

        return $job->isPlain() ? \json_encode($job->getPayload(), JSON_THROW_ON_ERROR) : \json_encode([
            'job' => $handlerJob,
            'data' => $job->getPayload(),
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @param $queue
     * @return string
     */
    private function getClass($queue = null): string
    {
        if (! $queue) {
            return Config::get('sqs-queue-reader.default-handler')['class'];
        }

        $queueId = explode('/', $queue);
        $queueId = array_pop($queueId);

        return (\array_key_exists($queueId, Config::get('sqs-queue-reader.handlers')))
            ? Config::get('sqs-queue-reader.handlers')[$queueId]['class']
            : Config::get('sqs-queue-reader.default-handler')['class'];
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param  string  $queue
     * @return \Illuminate\Contracts\Queue\Job|null
     *
     * @throws JsonException
     */
    public function pop($queue = null)
    {
        $queue = $this->getQueue($queue);

        $queueId = explode('/', $queue);
        $queueId = array_pop($queueId);

        $count = (\array_key_exists($queueId, Config::get('sqs-queue-reader.handlers')))
            ? Config::get('sqs-queue-reader.handlers')[$queueId]['count']
            : Config::get('sqs-queue-reader.default-handler')['count'];

        try {
            $response = $this->sqs->receiveMessage([
                'QueueUrl' => $queue,
                'AttributeNames' => ['ApproximateReceiveCount'],
                'MaxNumberOfMessages' => $count,
                'MessageAttributeNames' => ['All'],
            ]);

            if (isset($response['Messages']) && count($response['Messages']) > 0) {
                $class = (\array_key_exists($queueId, $this->container['config']->get('sqs-queue-reader.handlers')))
                    ? $this->container['config']->get('sqs-queue-reader.handlers')[$queueId]['class']
                    : $this->container['config']->get('sqs-queue-reader.default-handler')['class'];

                if ($count === 1) {
                    $response = $this->modifySinglePayload($response['Messages'][0], $class);
                } else {
                    $response = $this->modifyMultiplePayload($response['Messages'], $class);
                }

                return new SqsJob($this->container, $this->sqs, $response, $this->connectionName, $queue);
            }
        } catch (AwsException $e) {
            $msg = 'Line: '.$e->getLine().', '.$e->getFile().', '.$e->getMessage();

            throw new \RuntimeException('Aws SQS error: '.$msg);
        }
    }

    /**
     * @param  array|string  $payload
     * @param  string  $class
     * @return array|string
     *
     * @throws JsonException
     */
    private function modifySinglePayload(array | string $payload, string $class): array | string
    {
        if (! is_array($payload)) {
            $payload = \json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        }

        $body = \json_decode($payload['Body'], true, 512, JSON_THROW_ON_ERROR);

        $payload['Body'] = \json_encode([
            'uuid' => (string) Str::uuid(),
            'job' => $class.'@handle',
            'data' => $body['data'] ?? $body,
        ], JSON_THROW_ON_ERROR);

        return $payload;
    }

    /**
     * @param  array|string  $payload
     * @param  string  $class
     * @return array
     *
     * @throws JsonException
     */
    private function modifyMultiplePayload(array | string $payload, string $class): array
    {
        if (! is_array($payload)) {
            $payload = \json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        }

        $body = [];
        $attributes = [];
        $messageId = null;
        $receiptHandle = null;

        foreach ($payload as $k => $item) {
            try {
                $message = \json_decode($item['Body'], true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                $message = [];
            }

            $body[$k] = [
                'messages' => $message,
                'attributes' => $item['Attributes'],
                'batchIds' => [
                    'Id' => $item['MessageId'],
                    'ReceiptHandle' => $item['ReceiptHandle'],
                ],
            ];
            $attributes = $item['Attributes'];
            $messageId = $item['MessageId'];
            $receiptHandle = $item['ReceiptHandle'];
        }

        return [
            'MessageId' => $messageId,
            'ReceiptHandle' => $receiptHandle,
            'Body' => \json_encode([
                'uuid' => (string) Str::uuid(),
                'job' => $class.'@handle',
                'data' => $body,
            ], JSON_THROW_ON_ERROR),
            'Attributes' => $attributes,
        ];
    }

    /**
     * @param  string  $payload
     * @param  string|null  $queue
     * @param  array  $options
     * @return mixed
     *
     * @throws JsonException
     */
    public function pushRaw($payload, $queue = null, array $options = []): mixed
    {
        $payload = \json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        if (isset($payload['data'], $payload['job'])) {
            $payload = $payload['data'];
        }

        return parent::pushRaw(\json_encode($payload, JSON_THROW_ON_ERROR), $queue, $options);
    }
}
