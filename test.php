<?php

require 'keys.php';
require 'skyfunctions.php';

print_r(getBrowsePrice(prepareSearch('Gatwick', 'Madrid', '2017-11-15', $skyKey), $skyKey));


?>
