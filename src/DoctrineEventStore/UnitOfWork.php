<?php
namespace Tuck\DoctrineEventStore;

use Broadway\Domain\AggregateRoot;
use Broadway\Domain\DomainEventStream;
use Broadway\Domain\DomainMessage;
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
     * @var Transaction[]
     */
    private $failedTransactions = [];

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
        /** @var Transaction $transaction */
        while ($transaction = array_shift($this->pendingTransactions)) {
            try {
                $this->eventStore->append(
                    $transaction->getAggregateRootId(),
                    $transaction->getDomainEventStream()
                );
            } catch (\Exception $e) {
                $this->failedTransactions[] = $transaction;
                throw $e;
            }

            $this->committedTransactions[] = $transaction;
        }
    }

    /**
     * @return DomainEventStream
     */
    public function releaseCommittedEvents()
    {
        $committedEvents = $this->peekAtCommittedEvents();
        $this->clear();

        return $committedEvents;
    }

    /**
     * @return DomainEventStream
     */
    public function peekAtCommittedEvents()
    {
        // Collect and merge all Domain Events
        $messages = [];
        foreach ($this->committedTransactions as $transaction) {
            $messages = array_merge($messages, iterator_to_array($transaction->getDomainEventStream()));
        }

        // Sort DomainMessages based on their timestamps, so they always come
        // out in the same order they were generated internally.
        usort(
            $messages,
            function (DomainMessage $message1, DomainMessage $message2) {
                return strcmp($message1->getRecordedOn()->toString(), $message2->getRecordedOn()->toString());
            }
        );

        return new DomainEventStream($messages);
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
    public function getFailedTransactions()
    {
        return $this->failedTransactions;
    }

    /**
     * @return Transaction[]
     */
    public function getCommittedTransactions()
    {
        return $this->committedTransactions;
    }

    public function clear()
    {
        $this->pendingTransactions = [];
        $this->committedTransactions = [];
        $this->failedTransactions = [];
    }
}
