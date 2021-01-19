<?php

namespace palPalani\SqsQueueReader;

use Illuminate\Queue\Events\JobProcessed;
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
                $command = $event->data['data']['command'] ?? 'no command data';
                Log::debug('Job command==', [$command]);
                $data = $event->data ?? 'no data...';
                Log::debug('Job data==', [$data]);
                Log::debug('Job $event==', [$event]);
                $event->job->delete();
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
