<?php
namespace Tuck\DoctrineEventStore;

use Broadway\Domain\DomainEventStream;
use Broadway\Domain\DomainMessage;
use Broadway\Domain\Metadata;
use Doctrine\ORM\Mapping as ORM;

/**
 * Helper trait for raising events on entities
 */
trait EntityWithEvents
{
    /**
     * @var array
     */
    private $uncommittedEvents = array();

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    private $playhead = 0;

    /**
     * Records an event
     *
     * @param object $event
     */
    protected function raise($event)
    {
        $this->playhead++;

        $this->uncommittedEvents[] = DomainMessage::recordNow(
            $this->getAggregateRootId(),
            $this->playhead,
            new Metadata([]),
            $event
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getUncommittedEvents()
    {
        $stream = new DomainEventStream($this->uncommittedEvents);

        $this->uncommittedEvents = [];

        return $stream;
    }
}
