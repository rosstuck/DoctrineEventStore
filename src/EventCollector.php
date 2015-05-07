<?php
namespace Tuck\DoctrineEventStore;

use Broadway\Domain\AggregateRoot;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

class EventCollector implements EventSubscriber
{
    /**
     * @var UnitOfWork
     */
    private $eventUnitOfWork;

    /**
     * @param UnitOfWork $eventUnitOfWork
     */
    public function __construct(UnitOfWork $eventUnitOfWork)
    {
        $this->eventUnitOfWork = $eventUnitOfWork;
    }

    /**
     * @param OnFlushEventArgs $event
     */
    public function onFlush(OnFlushEventArgs $event)
    {
        $doctrineUnitOfWork = $event
            ->getEntityManager()
            ->getUnitOfWork();

        foreach ($doctrineUnitOfWork->getIdentityMap() as $className => $entities) {
            foreach ($entities as $entity) {
                $this->collectEventsFromEntity($entity);
            }
        }

        foreach ($doctrineUnitOfWork->getScheduledEntityDeletions() as $entity) {
            $this->collectEventsFromEntity($entity);
        }
    }

    /**
     * @param AggregateRoot $entity
     */
    protected function collectEventsFromEntity(AggregateRoot $entity)
    {
        if (!$entity instanceof AggregateRoot) {
            return;
        }

        $this->eventUnitOfWork->persistEventsFromAggregateRoot($entity);
    }

    /**
     * Returns an array of events this subscriber wants to listen to.
     *
     * @return array
     */
    public function getSubscribedEvents()
    {
        return [
            Events::onFlush
        ];
    }
}
