<?php

namespace palPalani\SqsQueueReader;

use Aws\Exception\AwsException;
use Aws\Sqs\SqsClient;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use palPalani\SqsQueueReader\Sqs\Connector;

class SqsQueueReaderServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/sqs-queue-reader.php' => config_path('sqs-queue-reader.php'),
            ], 'config');

            Queue::after(function (JobProcessed $event) {
                if ($event->connectionName === 'sqs-json' || $event->connectionName === 'sqs-mailgun' || $event->connectionName === 'sqs-external-customer-webhook' || $event->connectionName === 'sqs-priority-json') {
                    $queue = $event->job->getQueue();

                    $queueId = explode('/', $queue);
                    $queueId = array_pop($queueId);

                    $count = (array_key_exists($queueId, Config::get('sqs-queue-reader.handlers')))
                        ? Config::get('sqs-queue-reader.handlers')[$queueId]['count']
                        : Config::get('sqs-queue-reader.default-handler')['count'];

                    if ($count === 1) {
                        $event->job->delete();
                    } else {
                        $this->removeMessages($event->job->payload(), $queue);
                    }
                }
            });
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sqs-queue-reader.php', 'sqs-queue-reader');

        $this->app->booted(function () {
            $this->app['queue']->extend('sqs-json', static function () {
                return new Connector();
            });
        });
    }

    private function removeMessages(array $data, $queue): void
    {
        $batchIds = array_column($data['data'], 'batchIds');
        $batchIds = array_chunk($batchIds, 10);

        $client = new SqsClient([
            //'profile' => 'default',
            'region' => Config::get('queue.connections.sqs-json.region'),
            'version' => '2012-11-05',
            'credentials' => Arr::only(Config::get('queue.connections.sqs-json'), ['key', 'secret']),
            'http' => [
                'timeout' => 30,
                'connect_timeout' => 30,
            ],
        ]);

        foreach ($batchIds as $batch) {
            //Deletes up to ten messages from the specified queue.
            try {
                $result = $client->deleteMessageBatch([
                    'Entries' => $batch,
                    'QueueUrl' => $queue,
                ]);

                if (isset($result['Failed'])) {
                    $msg = '';
                    foreach ($result['Failed'] as $failed) {
                        $msg .= sprintf('Deleting message failed, code = %s, id = %s, msg = %s, senderfault = %s', $failed['Code'], $failed['Id'], $failed['Message'], $failed['SenderFault']);
                    }
                    Log::error('Cannot delete some SQS messages: ', [$msg]);

                    throw new \RuntimeException('Cannot delete some messages, consult log for more info!');
                }
                //Log::info('Message remove report:', [$result]);
            } catch (AwsException $e) {
                Log::error('AWS SQS client error:', [$e->getMessage()]);
            }
        }
    }
}
