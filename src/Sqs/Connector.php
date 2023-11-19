<?php

declare(strict_types=1);

namespace palPalani\SqsQueueReader\Sqs;

use Aws\Sqs\SqsClient;
use Illuminate\Queue\Connectors\SqsConnector;
use Illuminate\Support\Arr;

class Connector extends SqsConnector
{
    /**
     * Establish a queue connection.
     *
     * @return \Illuminate\Contracts\Queue\Queue
     */
    public function connect(array $config)
    {
        $config = $this->getDefaultConfiguration($config);

        if (isset($config['key'], $config['secret'])) {
            $config['credentials'] = Arr::only($config, ['key', 'secret']);
        }

        return new Queue(
            new SqsClient($config),
            $config['queue'],
            Arr::get($config, 'prefix', '')
        );
    }
}
