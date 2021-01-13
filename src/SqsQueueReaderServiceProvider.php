<?php

namespace palPalani\SqsQueueReader;

use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use palPalani\SqsQueueReader\Sqs\Connector;

class SqsQueueReaderServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/sqs-queue-reader.php' => config_path('sqs-queue-reader.php'),
            ], 'config');

            Queue::after(static function (JobProcessed $event) {
                $event->job->delete();
            });
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/sqs-queue-reader.php', 'laravel-sqs-queue-json-reader');

        $this->app->booted(function () {
            $this->app['queue']->extend('sqs-plain', static function () {
                return new Connector();
            });
        });
    }
}
