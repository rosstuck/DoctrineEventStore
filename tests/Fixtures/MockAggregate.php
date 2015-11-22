<?php
namespace Tuck\DoctrineEventStore\Tests\Fixtures;

use Broadway\Domain\AggregateRoot;
use Broadway\Domain\DomainEventStream;
use Tuck\DoctrineEventStore\EntityWithEvents;

/**
 * Handy-dandy fake aggregate builder
 */
class MockAggregate implements AggregateRoot
{
    use EntityWithEvents;

    /**
     * @var string
     */
    private $id;

    /**
     * @var int
     */
    static private $idCount = 0;

    /**
     * @return static
     */
    public static function withEvents()
    {
        static::$idCount++;
        return new static(
            static::$idCount,
            func_get_args()
        );
    }

    /**
     * @param string $id
     * @param array $events
     */
    private function __construct($id, $events)
    {
        $this->id = (string)$id;

        array_map([$this, 'raise'], $events);
    }

    /**
     * @return string
     */
    public function getAggregateRootId()
    {
        return $this->id;
    }

    public function andId($newId)
    {
        $this->id = $newId;
        return $this;
    }

    public function forceEvent($event)
    {
        $this->raise($event);
    }
}
