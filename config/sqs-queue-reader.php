<?php

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
