<?php

/**
 * List of plain SQS queues and their corresponding handling classes
 */
return [

    /**
     * Separate queue handle with corresponding queue name as key.
     */
    'handlers' => [
        //'stripe-webhooks' => App\Jobs\StripeHandler::class,
        //'mailgun-webhooks' => App\Jobs\MailgunHandler::class,
        //'shopify-webhooks' => App\Jobs\ShopifyHandler::class,
    ],

    'default-handler' => App\Jobs\SqsHandler::class
];
