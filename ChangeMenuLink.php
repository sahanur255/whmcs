<?php

use WHMCS\View\Menu\Item as MenuItem;

add_hook('ClientAreaPrimaryNavbar', 1, function (MenuItem $primaryNavbar) {
    $navItem = $primaryNavbar->getChild('Home');
    if (is_null($navItem)) {
        return;
    }

    // $navItem = $navItem->getChild('Announcements');
    // if (is_null($navItem)) {
    //     return;
    // }

    $navItem->setUri('https://www.example.com/3rdpartyblogsystem');
});
