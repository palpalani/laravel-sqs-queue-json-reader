<?php

declare(strict_types=1);

namespace palPalani\SqsQueueReader;

use Aws\Exception\AwsException;
use Aws\Sqs\SqsClient;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use palPalani\SqsQueueReader\Sqs\Connector;
use RuntimeException;

class SqsQueueReaderServiceProvider extends ServiceProvider
{
    private const CONFIG_KEY = 'sqs-queue-reader';

    private const CONFIG_PATH = __DIR__ . '/../config/sqs-queue-reader.php';

    private const SQS_VERSION = '2012-11-05';

    private const BATCH_SIZE = 10;

    private const DEFAULT_TIMEOUT = 30;

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishConfiguration();
        }

        $this->registerJobEventListener();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, self::CONFIG_KEY);

        $this->app->booted(fn() => $this->registerQueueDriver());
    }

    private function publishConfiguration(): void
    {
        $this->publishes([
            self::CONFIG_PATH => config_path(self::CONFIG_KEY . '.php'),
        ], 'config');
    }

    private function registerQueueDriver(): void
    {
        /** @var QueueManager $queueManager */
        $queueManager = $this->app['queue'];

        $queueManager->extend('sqs-json', static fn() => new Connector);
    }

    private function registerJobEventListener(): void
    {
        /** @var Dispatcher $eventDispatcher */
        $eventDispatcher = $this->app['events'];

        $eventDispatcher->listen(JobProcessed::class, $this->handleJobProcessed(...));
    }

    private function handleJobProcessed(JobProcessed $event): void
    {
        if (! $this->shouldProcessJob($event)) {
            return;
        }

        $messageCount = $this->getMessageCount($event->job);

        if ($messageCount === 1) {
            $event->job->delete();

            return;
        }

        $this->removeBatchMessages($event->job, $event->connectionName);
    }

    private function shouldProcessJob(JobProcessed $event): bool
    {
        /** @var ConfigRepository $config */
        $config = $this->app['config'];

        $connections = $config->get('queue.connections', []);

        return array_key_exists($event->connectionName, $connections);
    }

    private function getMessageCount(Job $job): int
    {
        $queueId = $this->extractQueueId($job->getQueue());

        /** @var ConfigRepository $config */
        $config = $this->app['config'];

        $handlers = $config->get(self::CONFIG_KEY . '.handlers', []);

        if (array_key_exists($queueId, $handlers)) {
            return (int) $handlers[$queueId]['count'];
        }

        $defaultHandler = $config->get(self::CONFIG_KEY . '.default-handler', []);

        return (int) ($defaultHandler['count'] ?? 1);
    }

    private function extractQueueId(string $queue): string
    {
        $segments = explode('/', $queue);

        return array_pop($segments) ?: throw new InvalidArgumentException("Invalid queue format: {$queue}");
    }

    private function removeBatchMessages(Job $job, string $connectionName): void
    {
        try {
            $payload = $job->payload();
            $batchIds = $this->extractBatchIds($payload);

            if (empty($batchIds)) {
                return;
            }

            $sqsClient = $this->createSqsClient($connectionName);
            $this->deleteBatchMessages($sqsClient, $batchIds, $job->getQueue());

        } catch (AwsException $exception) {
            Log::error('AWS SQS client error during message removal', [
                'error' => $exception->getMessage(),
                'connection' => $connectionName,
                'queue' => $job->getQueue(),
            ]);
        } catch (RuntimeException $exception) {
            Log::error('Failed to remove SQS messages', [
                'error' => $exception->getMessage(),
                'connection' => $connectionName,
                'queue' => $job->getQueue(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function extractBatchIds(array $payload): array
    {
        if (! isset($payload['data']['data'])) {
            return [];
        }

        $batchIds = array_column($payload['data']['data'], 'batchIds');

        return array_chunk($batchIds, self::BATCH_SIZE);
    }

    private function createSqsClient(string $connectionName): SqsClient
    {
        /** @var ConfigRepository $config */
        $config = $this->app['config'];

        $connectionConfig = $config->get("queue.connections.{$connectionName}");

        if (! is_array($connectionConfig)) {
            throw new InvalidArgumentException("Invalid connection configuration for: {$connectionName}");
        }

        $sqsConfig = [
            'region' => $connectionConfig['region'] ?? throw new InvalidArgumentException("Missing region for connection: {$connectionName}"),
            'version' => self::SQS_VERSION,
            'http' => [
                'timeout' => self::DEFAULT_TIMEOUT,
                'connect_timeout' => self::DEFAULT_TIMEOUT,
            ],
        ];

        if (isset($connectionConfig['key'], $connectionConfig['secret'])) {
            $sqsConfig['credentials'] = Arr::only($connectionConfig, ['key', 'secret']);
        }

        return new SqsClient($sqsConfig);
    }

    /**
     * @param  array<int, array<string, mixed>>  $batchIds
     */
    private function deleteBatchMessages(SqsClient $client, array $batchIds, string $queueUrl): void
    {
        foreach ($batchIds as $batch) {
            $result = $client->deleteMessageBatch([
                'Entries' => $batch,
                'QueueUrl' => $queueUrl,
            ]);

            if (isset($result['Failed']) && ! empty($result['Failed'])) {
                $this->handleFailedDeletions($result['Failed']);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $failedDeletions
     */
    private function handleFailedDeletions(array $failedDeletions): void
    {
        $errorMessages = [];

        foreach ($failedDeletions as $failed) {
            $errorMessages[] = sprintf(
                'Code: %s, ID: %s, Message: %s, Sender Fault: %s',
                $failed['Code'] ?? 'unknown',
                $failed['Id'] ?? 'unknown',
                $failed['Message'] ?? 'unknown',
                $failed['SenderFault'] ?? 'unknown'
            );
        }

        $combinedMessage = implode(' | ', $errorMessages);

        Log::error('Failed to delete SQS messages', [
            'failed_deletions' => $failedDeletions,
            'error_summary' => $combinedMessage,
        ]);

        throw new RuntimeException('Failed to delete some SQS messages. Check logs for details.');
    }
}
