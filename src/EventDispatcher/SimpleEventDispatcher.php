<?php
namespace Tuck\EventDispatcher;

/**
 * Ripped off version from the Broadway\EventDispatcher with some tweaking. :)
 */
class SimpleEventDispatcher implements EventDispatcher
{
    private $listeners = array();

    /**
     * {@inheritDoc}
     */
    public function dispatch($eventObject)
    {
        $eventName = get_class($eventObject);

        $this->dispatchEventToListenersOn($eventObject, $eventName);
        $this->dispatchEventToListenersOn($eventObject, '*');
    }

    public function dispatchAll($events)
    {
        foreach ($events as $event) {
            $this->dispatch($event);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function addListener($eventName, /* callable */ $callable) {
        if (!isset($this->listeners[$eventName])) {
            $this->listeners[$eventName] = array();
        }

        $this->listeners[$eventName][] = $callable;
    }

    /**
     * @param $eventObject
     * @param $eventName
     */
    protected function dispatchEventToListenersOn($eventObject, $eventName)
    {
        if (!isset($this->listeners[$eventName])) {
            return;
        }

        foreach ($this->listeners[$eventName] as $listener) {
            call_user_func_array($listener, [$eventObject]);
        }
    }
}
