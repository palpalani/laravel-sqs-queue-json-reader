<?php

/**
 * List of plain SQS queues and their corresponding handling classes
 */
return [

    /**
     * Separate queue handle with corresponding queue name as key.
     */
    'handlers' => [
        'st-webhooks' => App\Jobs\SqsHandler::class,
    ],

    'default-handler' => App\Jobs\SqsHandler::class
];
