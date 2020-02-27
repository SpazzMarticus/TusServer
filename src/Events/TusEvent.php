<?php

namespace SpazzMarticus\Tus\Events;

use Psr\EventDispatcher\StoppableEventInterface;
use Ramsey\Uuid\UuidInterface;
use SplFileInfo;

abstract class TusEvent implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    protected UuidInterface $uuid;
    protected SplFileInfo $file;

    /**
     * @var array
     */
    protected array $metadata;

    public function __construct(UuidInterface $uuid, SplFileInfo $file, array $metadata)
    {
        $this->uuid = $uuid;
        $this->file = $file;
        $this->metadata = $metadata;
    }

    public function getUuid(): UuidInterface
    {
        return $this->uuid;
    }

    public function getFile(): SplFileInfo
    {
        return $this->file;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * {@inheritdoc}
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * Stops the propagation of the event to further event listeners.
     *
     * If multiple event listeners are connected to the same event, no
     * further event listener will be triggered once any trigger calls
     * stopPropagation().
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}
