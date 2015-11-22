<?php
namespace Tuck\DoctrineEventStore\Tests;

use Broadway\Domain\DomainEventStream;
use Broadway\Domain\DomainMessage;
use Mockery as m;
use Tuck\DoctrineEventStore\Tests\Fixtures\MockAggregate;
use Tuck\DoctrineEventStore\Tests\Fixtures\OtherThingHappened;
use Tuck\DoctrineEventStore\Tests\Fixtures\ThingHappened;
use Tuck\DoctrineEventStore\Tests\Fixtures\YetAnotherThingHappened;
use Tuck\DoctrineEventStore\UnitOfWork;
use Broadway\EventStore\EventStoreInterface;
use OutOfBoundsException;

class UnitOfWorkTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var UnitOfWork
     */
    private $uow;

    /**
     * @var EventStoreInterface|m\MockInterface
     */
    private $eventStore;

    protected function setUp()
    {
        $this->uow = new UnitOfWork(
            $this->eventStore = m::mock(EventStoreInterface::class)
        );

        $this->eventStore->shouldReceive('append')->byDefault();
    }

    public function testCanFlushEventsToEventStore()
    {
        $event1 = new ThingHappened();
        $event2 = new OtherThingHappened();

        $this->uow->persistEventsFromAggregateRoot(MockAggregate::withEvents($event1));
        $this->uow->persistEventsFromAggregateRoot(MockAggregate::withEvents($event2));

        $this->eventStore->shouldReceive('append')->with(1, $this->eventStreamMatcher($event1));
        $this->eventStore->shouldReceive('append')->with(2, $this->eventStreamMatcher($event2));

        $this->uow->flush();
    }

    public function testRethrowsFailingException()
    {
        $expectedException = new OutOfBoundsException();
        $this->eventStore->shouldReceive('append')->andThrow($expectedException);

        $this->uow->persistEventsFromAggregateRoot(MockAggregate::withEvents(new ThingHappened()));

        $caughtException = null;
        try {
            $this->uow->flush();
        } catch (\Exception $e) {
            $caughtException = $e;
        }

        $this->assertSame($expectedException, $caughtException);
    }

    public function testManagesTransactionsPerStateInCaseOfFailure()
    {
        $event1 = new ThingHappened();
        $event2 = new OtherThingHappened();
        $event3 = new YetAnotherThingHappened();

        $this->uow->persistEventsFromAggregateRoot(MockAggregate::withEvents($event1)->andId(1));
        $this->uow->persistEventsFromAggregateRoot(MockAggregate::withEvents($event2)->andId(2));
        $this->uow->persistEventsFromAggregateRoot(MockAggregate::withEvents($event3)->andId(3));

        $this->eventStore->shouldReceive('append')->with(1, m::any());
        $this->eventStore->shouldReceive('append')->with(2, m::any())->andThrow(new OutOfBoundsException());
        $this->eventStore->shouldReceive('append')->with(3, m::any())->never();

        try {
            $this->uow->flush();
        } catch (OutOfBoundsException $e) {
        }

        $this->assertCount(1, $this->uow->getCommittedTransactions(), 'expected 1 committed transaction');
        $this->assertCount(1, $this->uow->getFailedTransactions(), 'expected 1 failed transaction');
        $this->assertCount(1, $this->uow->getPendingTransactions(), 'expected 1 pending transaction');

        $this->assertEquals(1, $this->uow->getCommittedTransactions()[0]->getAggregateRootId());
        $this->assertEquals(2, $this->uow->getFailedTransactions()[0]->getAggregateRootId());
        $this->assertEquals(3, $this->uow->getPendingTransactions()[0]->getAggregateRootId());
    }

    public function testCanRetrieveEventsAsSingleArrayEvenFromMultipleTransactions()
    {
        $event1 = new ThingHappened();
        $event2 = new OtherThingHappened();
        $event3 = new YetAnotherThingHappened();

        $this->uow->persistEventsFromAggregateRoot(MockAggregate::withEvents($event1, $event2));
        $this->uow->persistEventsFromAggregateRoot(MockAggregate::withEvents($event3));

        $this->uow->flush();

        $this->assertSameEvents(
            [$event1, $event2, $event3],
            $this->uow->peekAtCommittedEvents()
        );
    }

    public function testAlwaysReturnsEventsInTimeOrder()
    {
        $event1 = new ThingHappened();
        $event2 = new OtherThingHappened();
        $event3 = new YetAnotherThingHappened();

        $aggregate1 = MockAggregate::withEvents($event1);
        $aggregate2 = MockAggregate::withEvents($event2);
        $aggregate1->forceEvent($event3);

        $this->uow->persistEventsFromAggregateRoot($aggregate2);
        $this->uow->persistEventsFromAggregateRoot($aggregate1);
        $this->uow->flush();

        $this->assertSameEvents(
            [$event1, $event2, $event3],
            $this->uow->peekAtCommittedEvents()
        );
    }

    public function testCanReturnNoEventsWithoutError()
    {
        $this->assertSameEvents(
            [],
            $this->uow->peekAtCommittedEvents()
        );
    }

    public function testCanClearAnyPendingTransactions()
    {
        $this->uow->persistEventsFromAggregateRoot(MockAggregate::withEvents(new ThingHappened()));

        $this->assertCount(1, $this->uow->getPendingTransactions());
        $this->uow->clear();
        $this->assertCount(0, $this->uow->getPendingTransactions());
    }

    public function testCanClearPreviouslyCommittedTransactions()
    {
        $this->uow->persistEventsFromAggregateRoot(MockAggregate::withEvents(new ThingHappened()));
        $this->uow->flush();

        $this->assertCount(1, $this->uow->peekAtCommittedEvents());
        $this->uow->clear();
        $this->assertCount(0, $this->uow->peekAtCommittedEvents());
    }

    public function testCanClearFailedTransactions()
    {
        $this->uow->persistEventsFromAggregateRoot(MockAggregate::withEvents(new ThingHappened()));

        $this->eventStore->shouldReceive('append')->andThrow(new OutOfBoundsException());

        try {
            $this->uow->flush();
        } catch (OutOfBoundsException $e) {
        }

        $this->assertCount(1, $this->uow->getFailedTransactions());
        $this->uow->clear();
        $this->assertCount(0, $this->uow->getFailedTransactions());
    }

    public function testPeekingAtCommittedEventsDoesNotClearThem()
    {
        $this->uow->persistEventsFromAggregateRoot(
            MockAggregate::withEvents(new ThingHappened(), new OtherThingHappened())
        );
        $this->uow->flush();

        $this->uow->peekAtCommittedEvents();
        $this->assertCount(2, $this->uow->peekAtCommittedEvents());
    }

    public function testReleasingCommittedEventsDoesClearThem()
    {
        $this->uow->persistEventsFromAggregateRoot(
            MockAggregate::withEvents(new ThingHappened(), new OtherThingHappened())
        );
        $this->uow->flush();

        $this->assertCount(2, $this->uow->releaseCommittedEvents());
        $this->assertCount(0, $this->uow->releaseCommittedEvents());
    }

    private function assertSameEvents($expectedEvents, $receivedMessages)
    {
        $receivedMessages = iterator_to_array($receivedMessages);
        $this->assertCount(count($expectedEvents), $receivedMessages);

        foreach ($expectedEvents as $key => $expectedEvent) {
            $this->assertSame($expectedEvent, $receivedMessages[$key]->getPayload());
        }
    }

    private function eventStreamMatcher($expectedEvents)
    {
        if (!is_array($expectedEvents)) {
            $expectedEvents = [$expectedEvents];
        }

        return m::on(
            function (DomainEventStream $stream) use ($expectedEvents) {
                $receivedEvents = array_map(
                    function (DomainMessage $message) {
                        return $message->getPayload();
                    },
                    iterator_to_array($stream)
                );

                return $receivedEvents === $expectedEvents;
            }
        );
    }

}
