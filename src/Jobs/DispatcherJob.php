<?php

declare(strict_types=1);

namespace palPalani\SqsQueueReader\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatcherJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected bool $plain = false;

    public function __construct(protected $data) {}

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
     * @return $this
     */
    public function setPlain(bool $plain = true): self
    {
        $this->plain = $plain;

        return $this;
    }

    public function isPlain(): bool
    {
        return $this->plain;
    }

    public function __invoke()
    {
        $this->getPayload();
    }
}
