<?php
namespace Tuck\DoctrineEventStore;

use Broadway\Domain\DomainEventStreamInterface;
use Broadway\Domain\DomainMessage;

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

    /**
     * @return object[]
     */
    public function getDomainEvents()
    {
        return array_map(
            function (DomainMessage $message) {
                return $message->getPayload();
            },
            iterator_to_array($this->domainEventStream)
        );
    }
}
