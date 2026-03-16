# Custom SQS Queue Reader for Laravel

<p align="center">
    <img src="https://repository-images.githubusercontent.com/329289269/bf372113-a004-40f2-815b-300155f7a220" alt="Custom SQS queue reader for Laravel" style="width: 100%; max-width: 800px;" />
</p>

The Laravel SQS Queue Reader is an extension designed to seamlessly integrate external webhooks into your Laravel application. By leveraging the reliability and scalability of Amazon Simple Queue Service (SQS), this package ensures your application efficiently processes incoming webhooks, minimizing downtime and enhancing overall performance.

### Key Features

#### Effortless Webhook Integration

Easily integrate external webhooks into your Laravel application without compromising performance.

#### Queue-Based Processing

Harness Amazon SQS to queue incoming webhooks for asynchronous and parallel processing, ensuring optimal response times.

#### Reliability and Scalability

SQS provides a robust and scalable infrastructure so your application can handle varying webhook loads without compromising stability.

#### Seamless Laravel Integration

Designed as a Laravel extension, the Queue Reader integrates cleanly into your project, following Laravel's coding standards and conventions.

#### Configurable Settings

Customize settings to align with your application's requirements, including queue names, visibility timeout, and other SQS-specific configurations.

#### Detailed Logging

Gain insights into the webhook processing flow with detailed logging to help troubleshoot and monitor effectively.

### How It Works

#### Webhook Registration

Register external webhooks with your Laravel application by providing the webhook URL.

#### SQS Queue Integration

Incoming webhooks are processed through the SQS queue, ensuring optimal handling of webhook payloads.

#### Asynchronous Processing

Leverage asynchronous processing to handle webhooks in the background, preventing impact on your application's response times.

#### Automatic Retries

Benefit from SQS automatic retries, ensuring failed webhook processing attempts are retried without manual intervention.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/palpalani/laravel-sqs-queue-json-reader.svg?style=for-the-badge)](https://packagist.org/packages/palpalani/laravel-sqs-queue-json-reader)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/palpalani/laravel-sqs-queue-json-reader/run-tests.yml?branch=main&label=tests&style=for-the-badge)](https://github.com/palpalani/laravel-sqs-queue-json-reader/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/palpalani/laravel-sqs-queue-json-reader/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=for-the-badge)](https://github.com/palpalani/laravel-sqs-queue-json-reader/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/palpalani/laravel-sqs-queue-json-reader.svg?style=for-the-badge)](https://packagist.org/packages/palpalani/laravel-sqs-queue-json-reader)
<a href="https://laravel.com"><img alt="Laravel v10.x" src="https://img.shields.io/badge/Laravel-v10.x-FF2D20?style=for-the-badge&logo=laravel"></a>
<!--
<a href="https://livewire.laravel.com"><img alt="Livewire v3.x" src="https://img.shields.io/badge/Livewire-v3.x-FB70A9?style=for-the-badge"></a>
-->
<a href="https://php.net"><img alt="PHP 8.1" src="https://img.shields.io/badge/PHP-8.1-777BB4?style=for-the-badge&logo=php"></a>

Custom SQS queue reader for Laravel projects that supports raw JSON payloads and reads multiple messages. Laravel expects SQS messages to be generated in a specific format that includes a job handler class and a serialized job.

Note: Implemented to read multiple messages from the queue.

This library is useful when you want to parse messages from 3rd-party applications such as Stripe webhooks, Shopify webhooks, Mailgun webhooks, custom JSON messages, and more.

## Getting Started

Install via Composer:

```bash
composer require palpalani/laravel-sqs-queue-json-reader
```

Publish the config file and configure your SQS settings in the Laravel configuration file:

```bash
php artisan vendor:publish --provider="palPalani\SqsQueueReader\SqsQueueReaderServiceProvider" --tag="config"
```

This is the content of the published config file:

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
            'count' => 10,
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

If the queue is not found in the `handlers` array, the SQS payload is passed to the default handler.

Register your webhooks with your Laravel application.

Add an `sqs-json` connection to your `config/queue.php`. Example:

```php
    [
        // Add new SQS connection
        'sqs-json' => [
            'driver' => 'sqs-json',
            'key'    => env('AWS_ACCESS_KEY_ID', ''),
            'secret' => env('AWS_SECRET_ACCESS_KEY', ''),
            'prefix' => env('AWS_SQS_PREFIX', 'https://sqs.us-west-2.amazonaws.com/1234567890'),
            'queue'  => env('AWS_SQS_QUEUE', 'external-webhooks'),
            'region' => env('AWS_DEFAULT_REGION', 'us-west-2'),
        ],
    ]
```

In your `.env` file, choose `sqs-json` as your default queue connection:

```
QUEUE_CONNECTION=sqs-json
```

Enjoy seamless, reliable, and scalable webhook processing!

## Dispatching to SQS

If you plan to push plain messages from Laravel, you can rely on `DispatcherJob`:

```php
use palPalani\SqsQueueReader\Jobs\DispatcherJob;

class ExampleController extends Controller
{
    public function index()
    {
        // Dispatch job with some data.
        $job = new DispatcherJob([
            'music' => 'Ponni nathi from PS-1',
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
The above code will push the following JSON object to the SQS queue:

```json
{"job":"App\\Jobs\\SqsHandler@handle","data":{"music":"Ponni nathi from PS-1","singer":"AR. Rahman","time":1464511672}}
```

The `job` field is not used; it is kept for compatibility with the Laravel framework.

### Processing Job

Run the following command to test the dispatched job.

`php artisan queue:work sqs-json`

For production, use Supervisor with the following configuration.

```
[program:sqs-json-reader]
process_name=%(program_name)s_%(process_num)02d
command=php /var/html/app/artisan queue:work sqs-json --sleep=60 --timeout=10 --tries=2 --memory=128 --daemon
directory=/var/html/app
autostart=true
autorestart=true
startretries=10
user=root
numprocs=1
redirect_stderr=true
stdout_logfile=/var/html/app/horizon.log
stderr_logfile=/tmp/horizon-error.log
stopwaitsecs=3600
priority=1000
```

If you are using multiple connections, duplicate the above Supervisor configuration and change the connection name.

### Receiving from SQS

If a 3rd-party application or an API Gateway-to-SQS implementation is creating custom-format JSON messages, add a handler in the config file and implement a handler class as follows:

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

Note:

Ensure that your Laravel application is configured with the necessary AWS credentials and permissions to interact with SQS.

Enhance your Laravel application's webhook processing capabilities with the Laravel SQS Queue Reader. Efficient, reliable, and designed for optimal performance!

For more information about AWS SQS, check the [official docs](https://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/sqs-configure-queue-parameters.html).

## Testing

We already configured the scripts; just run the command:

```bash
composer test
```

For test coverage, run the command:
```bash
composer test-coverage
```
For code analysis, run the command:

```bash
composer analyse
```
For code formatting, run the command:

```bash
composer format
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

If you want to contribute, you may want to test it in a real Laravel project:

- Fork this repository to your GitHub account.
- Create a Laravel app locally.
- Clone your fork in your Laravel app's root directory.
- In the `/laravel-sqs-queue-json-reader` directory, create a branch for your fix, e.g. `feature/awesome-feature`.

Install the package in your app's `composer.json`:

```jsonc
{
    // ...
    "require": {
        "palpalani/laravel-sqs-queue-json-reader": "*",
    },
    "minimum-stability": "dev",
    "repositories": [
        {
            "type": "path",
            "url": "path/to/location"
        }
    ],
    // ...
}
```

Now, run `composer update`.

## Other Laravel Packages

[GrumPHP Rector task](https://github.com/palpalani/grumphp-rector-task) GrumPHP with a task that runs [RectorPHP](https://github.com/rectorphp/rector-src) for your Laravel projects.

[Email Deny List (blacklist) Check - IP Deny List (blacklist) Check](https://github.com/palpalani/laravel-dns-deny-list-check) Deny list (blacklist) checker that tests a mail server IP address against 50+ DNS-based email blacklists (commonly called Realtime blacklist, DNSBL, or RBL).

[SpamAssassin spam score of emails](https://github.com/palpalani/laravel-spamassassin-score) Checks the spam score of email contents using the SpamAssassin database.

[Laravel Login Notifications](https://github.com/palpalani/laravel-login-notifications) A login event notification for Laravel projects. By default, it sends notifications only in the production environment.

[Laravel Toastr](https://github.com/palpalani/laravel-toastr) Implements toastr.js for Laravel. Toastr.js is a JavaScript library for non-blocking notifications.

[Beast](https://github.com/palpalani/beast) Beast is Screenshot as a Service using Node.js, Chrome, and AWS Lambda. Convert a webpage to an image using headless Chrome; takes a screenshot of any given URL/HTML content and returns a base64-encoded buffer.

[eCommerce Product Recommendations](https://github.com/palpalani/eCommerce-Product-Recommendations) Analyze order history of customers and recommend products for new customers to enable higher sales volume.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [palPalani](https://github.com/palPalani)
- [All Contributors](../../contributors)

## Need Help?

If you spot a bug or have a question or feature request, please [submit a detailed issue](https://github.com/palpalani/laravel-sqs-queue-json-reader/issues) and wait for assistance.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
