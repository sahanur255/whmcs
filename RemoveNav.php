//need to upload under includes/hooks
<?php
use WHMCS\View\Menu\Item as MenuItem;
add_hook('ClientAreaPrimaryNavbar', 1, function (MenuItem $primaryNavbar)
{
    
    if (!is_null($primaryNavbar->getChild('Support'))) {
        $primaryNavbar->removeChild('Support');
    }
    
    
});

