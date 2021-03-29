<?php

namespace palPalani\SqsQueueReader\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use palPalani\SqsQueueReader\SqsQueueReaderServiceProvider;

class TestCase extends Orchestra
{
    public function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            SqsQueueReaderServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        /*
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        include_once __DIR__.'/../database/migrations/create_laravel_sqs_queue_json_reader_table.php.stub';
        (new \CreatePackageTable())->up();
        */
    }
}
