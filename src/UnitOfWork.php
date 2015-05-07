<?php
namespace Tuck\DoctrineEventStore;

use Broadway\Domain\AggregateRoot;
use Broadway\EventStore\EventStoreInterface;

/**
 * Holds pending events until we're ready to commit them to the database
 */
class UnitOfWork
{
    /**
     * @var EventStoreInterface
     */
    private $eventStore;

    /**
     * @var Transaction[]
     */
    private $pendingTransactions = [];

    /**
     * @var Transaction[]
     */
    private $committedTransactions = [];

    /**
     * @param EventStoreInterface $eventStore
     */
    public function __construct(EventStoreInterface $eventStore)
    {
        $this->eventStore = $eventStore;
    }

    /**
     * @param AggregateRoot $aggregateRoot
     */
    public function persistEventsFromAggregateRoot(AggregateRoot $aggregateRoot)
    {
        $this->pendingTransactions[] = new Transaction(
            $aggregateRoot->getAggregateRootId(),
            $aggregateRoot->getUncommittedEvents()
        );
    }

    /**
     * @throws \Exception
     */
    public function flush()
    {
        while ($transaction = array_pop($this->pendingTransactions)) {

            try {
                $this->eventStore->append(
                    $transaction->getAggregateRootId(),
                    $transaction->getDomainEventStream()
                );
            } catch (\Exception $e) {
                array_unshift($this->pendingTransactions, $transaction);
                throw $e;
            }

            $this->committedTransactions[] = $transaction;
        }
    }

    public function clear()
    {
        $this->pendingTransactions = [];
        $this->committedTransactions = [];
    }

    /**
     * @return Transaction[]
     */
    public function getPendingTransactions()
    {
        return $this->pendingTransactions;
    }

    /**
     * @return Transaction[]
     */
    public function getCommittedTransactions()
    {
        return $this->committedTransactions;
    }
}
