<?php

namespace Events;

use Pionia\Events\Event;
use Pionia\Events\PioniaEventDispatcher;
use Pionia\TestSuite\PioniaTestCase;

class PioniaEventDispatcherTest extends PioniaTestCase
{
    public function testDispatchesListenersInPriorityOrder(): void
    {
        $dispatcher = new PioniaEventDispatcher();
        $order = [];

        $dispatcher->addListener('demo', static function () use (&$order): void {
            $order[] = 'low';
        }, 0);
        $dispatcher->addListener('demo', static function () use (&$order): void {
            $order[] = 'high';
        }, 10);

        $dispatcher->dispatch(new \stdClass(), 'demo');

        $this->assertSame(['high', 'low'], $order);
    }

    public function testResolvesEventNameFromEventSubclass(): void
    {
        $dispatcher = new PioniaEventDispatcher();
        $called = false;

        $dispatcher->addListener(DemoEvent::name(), static function () use (&$called): void {
            $called = true;
        });

        $dispatcher->dispatch(new DemoEvent());

        $this->assertTrue($called);
    }

    public function testStoppableEventHaltsRemainingListeners(): void
    {
        $dispatcher = new PioniaEventDispatcher();
        $order = [];

        $dispatcher->addListener('stop', static function (StoppableDemoEvent $event) use (&$order): void {
            $order[] = 'first';
            $event->stopPropagation();
        }, 10);
        $dispatcher->addListener('stop', static function () use (&$order): void {
            $order[] = 'second';
        }, 0);

        $dispatcher->dispatch(new StoppableDemoEvent(), 'stop');

        $this->assertSame(['first'], $order);
    }

    public function testPsr14DispatchSignature(): void
    {
        $dispatcher = new PioniaEventDispatcher();
        $event = new DemoEvent();

        $this->assertSame($event, $dispatcher->dispatch($event));
    }
}

class DemoEvent extends Event
{
}

class StoppableDemoEvent extends Event
{
}
