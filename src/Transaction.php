<?php
namespace Tuck\DoctrineEventStore;

use Broadway\Domain\DomainEventStreamInterface;

class Transaction
{
    /**
     * @var string
     */
    private $aggregateRootId;

    /**
     * @var DomainEventStreamInterface
     */
    private $domainEventStream;

    public function __construct($aggregateRootId, DomainEventStreamInterface $domainEventStream)
    {
        $this->aggregateRootId = $aggregateRootId;
        $this->domainEventStream = $domainEventStream;
    }

    /**
     * @return string
     */
    public function getAggregateRootId()
    {
        return $this->aggregateRootId;
    }

    /**
     * @return DomainEventStreamInterface
     */
    public function getDomainEventStream()
    {
        return $this->domainEventStream;
    }
}
