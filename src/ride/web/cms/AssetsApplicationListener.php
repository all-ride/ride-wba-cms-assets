<?php

namespace ride\web\cms;

use ride\library\event\Event;

use ride\web\base\menu\MenuItem;

/**
 * Listener for events
 */
class AssetsApplicationListener {

    /**
     * Listeren to add a menu item to the content menu
     * @param \ride\library\event\Event $event
     * @return null
     */
    public function prepareContentMenu(Event $event) {
        $menuItem = new MenuItem();
        $menuItem->setTranslation('title.assets');
        $menuItem->setRoute('assets.overview');

        $menu = $event->getArgument('menu');
        $menu->addMenuItem($menuItem);
    }

}
