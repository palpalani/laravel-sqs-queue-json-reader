<?php

declare(strict_types=1);

namespace palPalani\SqsQueueReader\Sqs;

use Aws\Exception\AwsException;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Jobs\SqsJob;
use Illuminate\Queue\SqsQueue;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use JsonException;
use palPalani\SqsQueueReader\Jobs\DispatcherJob;
use RuntimeException;

/**
 * Custom SQS Queue implementation for handling raw JSON payloads from external sources.
 *
 * This queue extends Laravel's SqsQueue to support:
 * - Raw JSON message processing
 * - Single and batch message handling
 * - Custom handler class routing based on queue configuration
 */
class Queue extends SqsQueue
{
    /**
     * Create a payload string from the given job and data.
     *
     * @param  object|string  $job  The job instance or class name
     * @param  ?string  $queue  The queue name
     * @param  mixed  $data  Additional job data
     *
     * @throws JsonException When JSON encoding fails
     */
    protected function createPayload($job, $queue = null, $data = '', $delay = null): string
    {
        if (! $job instanceof DispatcherJob) {
            return parent::createPayload($job, $queue, $data);
        }

        if ($job->isPlain()) {
            return json_encode($job->getPayload(), JSON_THROW_ON_ERROR);
        }

        $handlerClass = $this->getHandlerClass($queue);

        return json_encode([
            'job' => "{$handlerClass}@handle",
            'data' => $job->getPayload(),
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Get the handler class for the specified queue.
     *
     * @param  ?string  $queue  The queue URL or name
     * @return string The fully qualified handler class name
     */
    private function getHandlerClass(?string $queue = null): string
    {
        $queueId = $this->extractQueueId($queue);
        $handlers = Config::get('sqs-queue-reader.handlers', []);
        $defaultHandler = Config::get('sqs-queue-reader.default-handler');

        if ($queueId && array_key_exists($queueId, $handlers)) {
            return $handlers[$queueId]['class'];
        }

        return $defaultHandler['class'];
    }

    /**
     * Extract queue ID from queue URL or return null for default queue.
     *
     * @param  ?string  $queue  The queue URL or name
     * @return ?string The extracted queue ID
     */
    private function extractQueueId(?string $queue): ?string
    {
        if (! $queue) {
            return null;
        }

        $parts = explode('/', $queue);

        return array_pop($parts);
    }

    /**
     * Get queue configuration for the specified queue.
     *
     * @param  ?string  $queue  The queue URL or name
     * @return array{class: string, count: int} Queue configuration
     */
    private function getQueueConfig(?string $queue): array
    {
        $queueId = $this->extractQueueId($queue);
        $handlers = Config::get('sqs-queue-reader.handlers', []);
        $defaultHandler = Config::get('sqs-queue-reader.default-handler');

        if ($queueId && array_key_exists($queueId, $handlers)) {
            return $handlers[$queueId];
        }

        return $defaultHandler;
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param  ?string  $queue  The queue name
     * @return ?Job The next job or null if no jobs available
     *
     * @throws JsonException When JSON processing fails
     * @throws RuntimeException When SQS operation fails
     */
    public function pop($queue = null)
    {
        $queueUrl = $this->getQueue($queue);
        $queueConfig = $this->getQueueConfig($queueUrl);

        try {
            $response = $this->receiveMessages($queueUrl, $queueConfig['count']);

            if (empty($response['Messages'])) {
                return;
            }

            $messages = $response['Messages'];
            $handlerClass = $queueConfig['class'];

            $processedResponse = $this->processMessages($messages, $handlerClass);

            return new SqsJob(
                $this->container,
                $this->sqs,
                $processedResponse,
                $this->connectionName,
                $queueUrl
            );
        } catch (AwsException $e) {
            throw new RuntimeException(
                sprintf(
                    'AWS SQS error: %s (File: %s, Line: %d)',
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                ),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Receive messages from SQS queue.
     *
     * @param  string  $queueUrl  The SQS queue URL
     * @param  int  $maxMessages  Maximum number of messages to receive
     * @return array SQS response containing messages
     *
     * @throws AwsException When SQS operation fails
     */
    private function receiveMessages(string $queueUrl, int $maxMessages): array
    {
        $result = $this->sqs->receiveMessage([
            'QueueUrl' => $queueUrl,
            'AttributeNames' => ['ApproximateReceiveCount'],
            'MaxNumberOfMessages' => $maxMessages,
            'MessageAttributeNames' => ['All'],
        ]);

        return $result->toArray();
    }

    /**
     * Process received messages into Laravel job format.
     *
     * @param  array  $messages  Array of SQS messages
     * @param  string  $handlerClass  The handler class name
     * @return array Processed message data
     *
     * @throws JsonException When JSON processing fails
     */
    private function processMessages(array $messages, string $handlerClass): array
    {
        return count($messages) === 1
            ? $this->processSingleMessage($messages[0], $handlerClass)
            : $this->processMultipleMessages($messages, $handlerClass);
    }

    /**
     * Process a single SQS message into Laravel job format.
     *
     * @param  array  $message  The SQS message data
     * @param  string  $handlerClass  The handler class name
     * @return array Processed message data
     *
     * @throws JsonException When JSON processing fails
     */
    private function processSingleMessage(array $message, string $handlerClass): array
    {
        $messageBody = $this->decodeMessageBody($message['Body']);

        $message['Body'] = json_encode([
            'uuid' => (string) Str::uuid(),
            'job' => "{$handlerClass}@handle",
            'data' => $messageBody['data'] ?? $messageBody,
        ], JSON_THROW_ON_ERROR);

        return $message;
    }

    /**
     * Process multiple SQS messages into Laravel batch job format.
     *
     * @param  array  $messages  Array of SQS message data
     * @param  string  $handlerClass  The handler class name
     * @return array Processed batch message data
     *
     * @throws JsonException When JSON processing fails
     */
    private function processMultipleMessages(array $messages, string $handlerClass): array
    {
        $batchData = [];
        $lastMessage = end($messages);

        foreach ($messages as $index => $message) {
            $messageBody = $this->safeDecodeMessageBody($message['Body']);

            $batchData[$index] = [
                'messages' => $messageBody,
                'attributes' => $message['Attributes'] ?? [],
                'batchIds' => [
                    'Id' => $message['MessageId'],
                    'ReceiptHandle' => $message['ReceiptHandle'],
                ],
            ];
        }

        return [
            'MessageId' => $lastMessage['MessageId'],
            'ReceiptHandle' => $lastMessage['ReceiptHandle'],
            'Body' => json_encode([
                'uuid' => (string) Str::uuid(),
                'job' => "{$handlerClass}@handle",
                'data' => $batchData,
            ], JSON_THROW_ON_ERROR),
            'Attributes' => $lastMessage['Attributes'] ?? [],
        ];
    }

    /**
     * Decode message body JSON with error handling.
     *
     * @param  string  $messageBody  The raw message body
     * @return array The decoded message data
     *
     * @throws JsonException When JSON decoding fails
     */
    private function decodeMessageBody(string $messageBody): array
    {
        return json_decode($messageBody, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Safely decode message body JSON, returning empty array on failure.
     *
     * @param  string  $messageBody  The raw message body
     * @return array The decoded message data or empty array
     */
    private function safeDecodeMessageBody(string $messageBody): array
    {
        try {
            return $this->decodeMessageBody($messageBody);
        } catch (JsonException) {
            return [];
        }
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param  string  $payload  The raw JSON payload
     * @param  ?string  $queue  The queue name
     * @param  array  $options  Additional options
     * @return mixed The result of the push operation
     *
     * @throws JsonException When JSON processing fails
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        $decodedPayload = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        // Extract data from Laravel job format if present
        if (isset($decodedPayload['data'], $decodedPayload['job'])) {
            $decodedPayload = $decodedPayload['data'];
        }

        $processedPayload = json_encode($decodedPayload, JSON_THROW_ON_ERROR);

        return parent::pushRaw($processedPayload, $queue, $options);
    }
}
