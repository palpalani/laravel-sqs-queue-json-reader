# A custom SQS queue reader for Laravel that supports plain JSON payloads.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/palpalani/laravel-sqs-queue-json-reader.svg?style=flat-square)](https://packagist.org/packages/palpalani/laravel-sqs-queue-json-reader)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/palpalani/laravel-sqs-queue-json-reader/run-tests?label=tests)](https://github.com/palpalani/laravel-sqs-queue-json-reader/actions?query=workflow%3ATests+branch%3Amaster)
[![Total Downloads](https://img.shields.io/packagist/dt/palpalani/laravel-sqs-queue-json-reader.svg?style=flat-square)](https://packagist.org/packages/palpalani/laravel-sqs-queue-json-reader)


This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

## Installation

You can install the package via composer:

```bash
composer require palpalani/laravel-sqs-queue-json-reader
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --provider="Palpalani\SqsQueueReader\SqsQueueReaderServiceProvider" --tag="migrations"
php artisan migrate
```

You can publish the config file with:
```bash
php artisan vendor:publish --provider="Palpalani\SqsQueueReader\SqsQueueReaderServiceProvider" --tag="config"
```

This is the contents of the published config file:

```php
return [
];
```

## Usage

```php
$laravel-sqs-queue-json-reader = new Palpalani\SqsQueueReader();
echo $laravel-sqs-queue-json-reader->echoPhrase('Hello, Palpalani!');
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
