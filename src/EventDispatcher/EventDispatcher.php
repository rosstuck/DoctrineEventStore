<?php
namespace Tuck\EventDispatcher;

/**
 *
 */
interface EventDispatcher
{
    /**
     * @param object $event
     * @return void
     */
    public function dispatch($event);

    /**
     * @param object[] $events
     * @return void
     */
    public function dispatchAll($events);

    /**
     * @param string $eventClassName
     * @param callable $callableListener
     * @return void
     */
    public function addListener($eventClassName, $callableListener);
}
