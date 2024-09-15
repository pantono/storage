<?php

namespace Pantono\Storage\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Pantono\Storage\Model\StoredFile;

abstract class AbstractStoredFileEvent extends Event
{
    private StoredFile $current;
    private ?StoredFile $previous = null;

    public function getCurrent(): StoredFile
    {
        return $this->current;
    }

    public function setCurrent(StoredFile $current): void
    {
        $this->current = $current;
    }

    public function getPrevious(): ?StoredFile
    {
        return $this->previous;
    }

    public function setPrevious(?StoredFile $previous): void
    {
        $this->previous = $previous;
    }
}
