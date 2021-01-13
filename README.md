# Custom SQS queue reader for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/palpalani/laravel-sqs-queue-json-reader.svg?style=flat-square)](https://packagist.org/packages/palpalani/laravel-sqs-queue-json-reader)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/palpalani/laravel-sqs-queue-json-reader/run-tests?label=tests)](https://github.com/palpalani/laravel-sqs-queue-json-reader/actions?query=workflow%3ATests+branch%3Amaster)
[![Total Downloads](https://img.shields.io/packagist/dt/palpalani/laravel-sqs-queue-json-reader.svg?style=flat-square)](https://packagist.org/packages/palpalani/laravel-sqs-queue-json-reader)


Custom SQS queue reader for Laravel that supports JSON payloads. 
Out of the box, Laravel expects SQS messages to be generated in a 
specific format - format that includes job handler class and a serialized job.

But in certain cases you may want to parse messages from third party 
applications, custom JSON messages and so on.

## Installation

You can install the package via composer:

```bash
composer require palpalani/laravel-sqs-queue-json-reader
```

You can publish the config file with:
```bash
php artisan vendor:publish --provider="palPalani\SqsQueueReader\SqsQueueReaderServiceProvider" --tag="config"
```

This is the contents of the published config file:

```php
return [

    /**
     * Separate queue handle with corresponding queue name as key.
     */
    'handlers' => [
        //'stripe-webhooks' => App\Jobs\SqsHandler::class,
    ],

    'default-handler' => App\Jobs\SqsHandler::class
];
```

If queue is not found in 'handlers' array, SQS payload is passed to default handler.

Add sqs-json connection to your config/queue.php, eg:

```php
    [
        // Add connection
        'sqs-json' => [
            'driver' => 'sqs-json',
            'key'    => env('AWS_KEY', ''),
            'secret' => env('AWS_SECRET', ''),
            'prefix' => 'https://sqs.us-west-2.amazonaws.com/3242342351/',
            'queue'  => 'stripe-webhooks',
            'region' => 'ap-southeast-2',
        ],
    ]
```

In your .env file, choose sqs-plain as your new default queue driver:

```
QUEUE_DRIVER=sqs-json
```

Dispatching to SQS

If you plan to push plain messages from Laravel, you can rely on DispatcherJob:

```php
use palPalani\SqsQueueReader\Jobs\DispatcherJob;

class ExampleController extends Controller
{
    public function index()
    {
        // Create a PHP object
        $object = [
            'music' => 'Sample message',
            'time' => time()
        ];

        // Pass it to dispatcher job
        $job = new DispatcherJob($object);

        // Dispatch the job as you normally would
        // By default, your data will be encapsulated in 'data' and 'job' field will be added
        $this->dispatch($job);

        // If you wish to submit a true plain JSON, add setPlain()
        $this->dispatch($job->setPlain());
    }
}
```
This will push the following JSON object to SQS:

```json
{"job":"App\\Jobs\\SqsHandler@handle","data":{"music":"Sample message","time":1464411642}}
```

'job' field is not used, actually. It's just kept for compatibility.

Receiving from SQS
If a third-party application is creating custom-format JSON messages, just add a 
handler in the config file and implement a handler class as follows:

```php
use Illuminate\Contracts\Queue\Job as LaravelJob;

class HandlerJob extends Job
{
    protected $data;

    /**
     * @param LaravelJob $job
     * @param array $data
     */
    public function handle(LaravelJob $job, array $data)
    {
        // This is incoming JSON payload, already decoded to an array
        var_dump($data);

        // Raw JSON payload from SQS, if necessary
        var_dump($job->getRawBody());
    }
}
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [palPalani](https://github.com/palPalani)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
