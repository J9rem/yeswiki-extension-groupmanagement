<?php

/*
 * This file is part of the YesWiki Extension groupmanagement.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YesWiki\Groupmanagement\Service;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use YesWiki\Comschange\Service\EventDispatcher as ComschangeEventDispatcher;
use YesWiki\Core\Service\EventDispatcher as CoreEventDispatcher;
use YesWiki\Groupmanagement\Service\EventDispatcherInterface;
use YesWiki\Wiki;

if (file_exists('tools/groupmanagement/services/EventDispatcherInterface.php')) {
    include_once 'tools/groupmanagement/services/EventDispatcherInterface.php';
}
if (file_exists('includes/services/EventDispatcher.php')) {
    include_once 'includes/services/EventDispatcher.php';
} elseif (file_exists('tools/comschange/services/EventDispatcher.php')) {
    include_once 'tools/comschange/services/EventDispatcher.php';
}

trait EventDispatcherCommon
{
    protected $parentEventDispatcher;
    protected $wiki;

    public function yesWikiDispatch(string $eventName, array $data = []): array
    {
        if (is_null($this->parentEventDispatcher)) {
            return [];
        }
        return $this->parentEventDispatcher->yesWikiDispatch($eventName, $data);
    }

    public function getListeners(string $eventName = null)
    {
        if (is_null($this->parentEventDispatcher)) {
            return [];
        }
        return $this->parentEventDispatcher->getListeners($eventName);
    }

    public function dispatch(object $event, string $eventName = null): object
    {
        if (is_null($this->parentEventDispatcher)) {
            return $event;
        }
        return $this->parentEventDispatcher->dispatch($event, $eventName);
    }

    public function getListenerPriority(string $eventName, $listener)
    {
        if (is_null($this->parentEventDispatcher)) {
            return null;
        }
        return $this->parentEventDispatcher->getListenerPriority($eventName, $listener);
    }

    public function hasListeners(string $eventName = null)
    {
        if (is_null($this->parentEventDispatcher)) {
            return false;
        }
        return $this->parentEventDispatcher->hasListeners($eventName);
    }

    public function addListener(string $eventName, $listener, int $priority = 0)
    {
        if (is_null($this->parentEventDispatcher)) {
            return null;
        }
        return $this->parentEventDispatcher->addListener($eventName, $listener, $priority);
    }
    public function removeListener(string $eventName, $listener)
    {
        if (is_null($this->parentEventDispatcher)) {
            return null;
        }
        return $this->parentEventDispatcher->addListener($eventName, $listener);
    }

    public function addSubscriber(EventSubscriberInterface $subscriber)
    {
        if (is_null($this->parentEventDispatcher)) {
            return null;
        }
        return $this->parentEventDispatcher->addSubscriber($subscriber);
    }

    public function removeSubscriber(EventSubscriberInterface $subscriber)
    {
        if (is_null($this->parentEventDispatcher)) {
            return null;
        }
        return $this->parentEventDispatcher->removeSubscriber($subscriber);
    }
}
if (class_exists(CoreEventDispatcher::class, false)) {
    class EventDispatcher extends CoreEventDispatcher implements EventDispatcherInterface
    {
        use EventDispatcherCommon;

        public function __construct(Wiki $wiki, CoreEventDispatcher $coreEventDispatcher)
        {
            $this->parentEventDispatcher = $coreEventDispatcher;
            $this->wiki = $wiki;
        }
    }
} elseif (class_exists(ComschangeEventDispatcher::class, false)) {
    class EventDispatcher extends ComschangeEventDispatcher implements EventDispatcherInterface
    {
        use EventDispatcherCommon;

        public function __construct(Wiki $wiki, ComschangeEventDispatcher $comsChangeEventDispatcher, $coreEventDispatcher)
        {
            $this->parentEventDispatcher = $comsChangeEventDispatcher;
            $this->wiki = $wiki;
        }
    }
} else {
    class EventDispatcher implements EventDispatcherInterface
    {
        use EventDispatcherCommon;

        public function __construct(Wiki $wiki, $coreEventDispatcher)
        {
            $this->parentEventDispatcher = null;
            $this->wiki = $wiki;
        }

        public function yesWikiDispatch(string $eventName, array $data = []): array
        {
            return [];
        }
    }
}
