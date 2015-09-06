<?php
namespace Tuck\EventDispatcher;

class BufferedEventDispatcher implements EventDispatcher
{
    /**
     * @var EventDispatcher
     */
    private $actualDispatcher;

    /**
     * @var object[]
     */
    private $pendingEvents;

    public function __construct(EventDispatcher $actualDispatcher)
    {
        $this->actualDispatcher = $actualDispatcher;
    }

    public function dispatch($event)
    {
        $this->pendingEvents[] = $event;
    }

    public function dispatchAll($events)
    {
        foreach ($events as $event) {
            $this->pendingEvents[] = $event;
        }
    }

    public function dispatchPendingEvents()
    {
        foreach ($this->pendingEvents as $event) {
            $this->actualDispatcher->dispatch($event);
        }
    }

    public function addListener($eventName, $callableListener)
    {
        return $this->actualDispatcher->addListener($eventName, $callableListener);
    }
}
