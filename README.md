# Custom SQS queue reader for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/palpalani/laravel-sqs-queue-json-reader.svg?style=flat-square)](https://packagist.org/packages/palpalani/laravel-sqs-queue-json-reader)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/palpalani/laravel-sqs-queue-json-reader/run-tests?label=tests)](https://github.com/palpalani/laravel-sqs-queue-json-reader/actions?query=workflow%3ATests+branch%3Amaster)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/palpalani/laravel-sqs-queue-json-reader/Check%20&%20fix%20styling?label=code%20style)](https://github.com/palpalani/laravel-sqs-queue-json-reader/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amaster)
[![Total Downloads](https://img.shields.io/packagist/dt/palpalani/laravel-sqs-queue-json-reader.svg?style=flat-square)](https://packagist.org/packages/palpalani/laravel-sqs-queue-json-reader)


Custom SQS queue reader for Laravel projects that supports raw JSON payloads. 
Laravel expects SQS messages to be generated in a 
specific format that includes job handler class and a serialized job.

Note: Implemented tm read multiple messages from queue.

But in certain cases you may want to parse messages from 3rd party 
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
/**
 * List of plain SQS queues and their corresponding handling classes
 */
return [

    // Separate queue handler with corresponding queue name as key.
    'handlers' => [
        'stripe-webhooks' => [
            'class' => App\Jobs\StripeHandler::class,
            'count' => 10,
        ],
        'mailgun-webhooks' => [
            'class' => App\Jobs\MailgunHandler::class,
            'count' => 100,
        ]
    ],

    // If no handlers specified then default handler will be executed.
    'default-handler' => [

        // Name of the handler class
        'class' => App\Jobs\SqsHandler::class,

        // Number of messages need to read from SQS.
        'count' => 1,
    ]
];
```

If queue is not found in 'handlers' array, SQS payload is passed to default handler.

Add `sqs-json` connection to your config/queue.php, Ex:

```php
    [
        // Add new SQS connection
        'sqs-json' => [
            'driver' => 'sqs-json',
            'key'    => env('AWS_ACCESS_KEY_ID', ''),
            'secret' => env('AWS_SECRET_ACCESS_KEY', ''),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-west-2.amazonaws.com/1234567890'),
            'queue'  => env('SQS_QUEUE', 'stripe-webhooks'),
            'region' => env('AWS_DEFAULT_REGION', 'us-west-2'),
        ],
    ]
```

In your .env file, choose sqs-json as your new default queue driver:

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
        // Dispatch job with some data.
        $job = new DispatcherJob([
            'music' => 'Sample SQS message',
            'singer' => 'AR. Rahman',
            'time' => time()
        ]);

        // Dispatch the job as you normally would
        // By default, your data will be encapsulated in 'data' and 'job' field will be added
        $this->dispatch($job);

        // If you wish to submit a true plain JSON, add setPlain()
        $this->dispatch($job->setPlain());
    }
}
```
Above code will push the following JSON object to SQS queue:

```json
{"job":"App\\Jobs\\SqsHandler@handle","data":{"music":"Sample SQS message","singer":"AR. Rahman","time":1464511672}}
```

'job' field is not used, actually. It's just kept for compatibility with Laravel
Framework.

### Receiving from SQS

If a 3rd-party application or API Gateway to SQS implementation is creating 
custom-format JSON messages, just add a 
handler in the config file and implement a handler class as follows:

```php
use Illuminate\Contracts\Queue\Job as LaravelJob;

class SqsHandlerJob extends Job
{
    /**
     * @var null|array $data
     */
    protected $data;

    /**
     * @param LaravelJob $job
     * @param null|array $data
     */
    public function handle(LaravelJob $job, ?array $data): void
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
