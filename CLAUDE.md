# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

### Testing
- `composer test` - Run tests using TestBench without coverage
- `composer test-coverage` - Run PHPUnit with HTML coverage report
- `./vendor/bin/testbench package:test --no-coverage` - Direct TestBench command

### Code Quality
- `composer analyse` - Run PHPStan static analysis (level 4)
- `composer format` - Format code using Laravel Pint with custom ruleset
- `vendor/bin/pint` - Direct Pint formatting command
- `vendor/bin/phpstan analyse` - Direct PHPStan command

### Package Development
- `php artisan vendor:publish --provider="palPalani\SqsQueueReader\SqsQueueReaderServiceProvider" --tag="config"` - Publish configuration file

## Architecture Overview

This is a Laravel package that extends SQS queue functionality to handle raw JSON payloads from external sources (webhooks, third-party APIs) without requiring Laravel's specific job format.

### Core Components

**Queue Driver (`sqs-json`)**
- Custom SQS connector (`src/Sqs/Connector.php`) extends Laravel's SqsConnector
- Custom queue implementation (`src/Sqs/Queue.php`) extends Laravel's SqsQueue
- Handles both single and batch message processing
- Automatically formats raw JSON messages into Laravel job format

**Service Provider (`src/SqsQueueReaderServiceProvider.php`)**
- Registers the `sqs-json` queue driver
- Handles automatic message deletion after processing
- Manages batch message cleanup via SQS API

**Dispatcher Job (`src/Jobs/DispatcherJob.php`)**
- Utility for dispatching plain JSON or Laravel-formatted messages
- Supports both structured (`setPlain(false)`) and plain JSON (`setPlain(true)`) modes

### Configuration System

**Queue Handlers (`config/sqs-queue-reader.php`)**
- Maps queue names to handler classes and message counts
- Supports multiple queues with different handlers
- Falls back to default handler for unmapped queues
- Configurable message batch sizes (1-10 messages per poll)

**Queue Connection Setup**
- Add `sqs-json` driver to `config/queue.php`
- Use standard AWS SQS configuration (key, secret, region, prefix, queue)
- Set `QUEUE_DRIVER=sqs-json` in environment

### Message Processing Flow

1. **Incoming Messages**: Raw JSON from external sources (Stripe, Mailgun, etc.)
2. **Queue Processing**: `Queue::pop()` retrieves and formats messages
3. **Handler Mapping**: Uses queue name to determine handler class and batch size
4. **Job Creation**: Wraps raw payload in Laravel job format with UUID
5. **Batch Handling**: Multiple messages processed together when count > 1
6. **Cleanup**: Automatic SQS message deletion after successful processing

### Testing Framework

- Uses Orchestra Testbench for Laravel package testing
- Configured for strict testing (warnings/notices as failures)
- Coverage reports generated in `build/coverage/`
- Test files in `tests/` directory

### Code Standards

- PHP 8.3+ with strict types declared
- Laravel Pint formatting with custom rules (PER-CS, PHP 8.3 migration)
- PHPStan level 4 analysis with Octane compatibility checks
- PSR-4 autoloading: `palPalani\SqsQueueReader\`

### Key Features

- **Multi-message Processing**: Configurable batch sizes for high-throughput scenarios
- **Handler Flexibility**: Different job classes per queue
- **AWS Integration**: Direct SQS API usage for batch operations
- **Laravel Compatibility**: Works with Laravel 11-12, PHP 8.3+
- **Plain JSON Support**: Handles raw webhook payloads without Laravel job wrapper