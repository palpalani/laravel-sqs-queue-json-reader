<?php

namespace palPalani\SqsQueueReader;

use Illuminate\Queue\Events\JobProcessed;
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
                __DIR__ . '/../config/sqs-queue-reader.php' => config_path('sqs-queue-reader.php'),
            ], 'config');

            Queue::after(static function (JobProcessed $event) {
                $queue = $event->job->getQueue();
                $count = (array_key_exists($queue, Config::get('sqs-queue-reader.handlers')))
                    ? Config::get('sqs-queue-reader.handlers')[$queue]['count']
                    : Config::get('sqs-queue-reader.default-handler')['count'];

                if ($count === 1) {
                    $event->job->delete();
                } else {
                    $data = $event->job->payload();
                    Log::debug('Job payload==', [$data]);

                    $batchIds = array_column($data, 'batchIds');

                    $batchIds = array_chunk($batchIds, 10);

                    foreach ($batchIds as $batch) {
                        //Deletes up to ten messages from the specified queue.
                        $result = $event->job->deleteMessageBatch([
                            'Entries' => $batch,
                            'QueueUrl' => $queue,
                        ]);

                        if ($result['Failed']) {
                            $msg = '';
                            foreach ($result['Failed'] as $failed) {
                                $msg .= sprintf("Deleting message failed, code = %s, id = %s, msg = %s, senderfault = %s", $failed['Code'], $failed['Id'], $failed['Message'], $failed['SenderFault']);
                            }
                            Log::error('Cannot delete some SQS messages: ', [$msg]);

                            throw new \RuntimeException("Cannot delete some messages, consult log for more info!");
                        }
                    }
                }
            });
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/sqs-queue-reader.php', 'sqs-queue-reader');

        $this->app->booted(function () {
            $this->app['queue']->extend('sqs-json', static function () {
                return new Connector();
            });
        });
    }
}
