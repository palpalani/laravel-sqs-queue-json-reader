<?php

namespace palPalani\SqsQueueReader\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatcherJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var mixed
     */
    protected $data;

    /**
     * @var bool
     */
    protected $plain = false;

    /**
     * DispatchedJob constructor.
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * @return mixed
     */
    public function getPayload()
    {
        if (! $this->isPlain()) {
            return [
                'job' => app('config')->get('sqs-queue-reader.default-handler'),
                'data' => $this->data,
            ];
        }

        return $this->data;
    }

    /**
     * @param bool $plain
     * @return $this
     */
    public function setPlain($plain = true)
    {
        $this->plain = $plain;

        return $this;
    }

    /**
     * @return bool
     */
    public function isPlain()
    {
        return $this->plain;
    }

    public function __invoke()
    {
        $this->getPayload();
    }
}
