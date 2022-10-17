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

if (class_exists(CoreEventDispatcher::class, false)) {
    class EventDispatcher extends CoreEventDispatcher implements EventDispatcherInterface
    {
        public function __construct(Wiki $wiki)
        {
            $this->wiki = $wiki;
        }
    }
} elseif (class_exists(ComschangeEventDispatcher::class, false)) {
    class EventDispatcher extends ComschangeEventDispatcher implements EventDispatcherInterface
    {
        protected $comsChangeEventDispatcher;

        public function __construct(Wiki $wiki, ComschangeEventDispatcher $comsChangeEventDispatcher)
        {
            $this->comsChangeEventDispatcher = $comsChangeEventDispatcher;
            $this->wiki = $wiki;
        }

        public function yesWikiDispatch(string $eventName, array $data = []): array
        {
            return $this->comsChangeEventDispatcher->yesWikiDispatch($eventName, $data);
        }

        public function getListeners(string $eventName = null)
        {
            return $this->comsChangeEventDispatcher->getListeners($eventName);
        }

        public function dispatch(object $event, string $eventName = null): object
        {
            return $this->comsChangeEventDispatcher->dispatch($event, $eventName);
        }

        public function getListenerPriority(string $eventName, $listener)
        {
            return $this->comsChangeEventDispatcher->getListenerPriority($eventName, $listener);
        }

        public function hasListeners(string $eventName = null)
        {
            return $this->comsChangeEventDispatcher->hasListeners($eventName);
        }

        public function addListener(string $eventName, $listener, int $priority = 0)
        {
            return $this->comsChangeEventDispatcher->addListener($eventName, $listener, $priority);
        }
        public function removeListener(string $eventName, $listener)
        {
            return $this->comsChangeEventDispatcher->addListener($eventName, $listener);
        }
    }
} else {
    class EventDispatcher implements EventDispatcherInterface
    {
        public function __construct(Wiki $wiki)
        {
        }

        public function yesWikiDispatch(string $eventName, array $data = []): array
        {
            return [];
        }
    }
}
