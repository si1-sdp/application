<?php
/*
 * This file is part of dgfip-si1/application
 */
namespace DgfipSI1\ApplicationTests\TestClasses\Commands;

use DgfipSI1\Application\Contracts\ConfigAwareInterface;
use DgfipSI1\Application\Contracts\ConfigAwareTrait;
use DgfipSI1\Application\Contracts\LoggerAwareInterface;
use DgfipSI1\Application\Contracts\LoggerAwareTrait;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 *
 */
class EventTestClass implements ConfigAwareInterface, EventSubscriberInterface
{
    use ConfigAwareTrait;
    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        $events = [];
        $events[ConsoleEvents::COMMAND] = ["beforeCommandExecution", -1000];

        return $events;
    }
    /**
     * test event dispatcher :  set something in config to check we have been called
     *
     * @param ConsoleCommandEvent $e
     *
     * @return void
     */
    public function beforeCommandExecution(ConsoleCommandEvent $e) : void
    {
        $this->getConfig()->set("options.test_event", "set via event");
    }
}
