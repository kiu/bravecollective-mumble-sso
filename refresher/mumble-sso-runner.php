<?php
    define('GUEST', 23);

    if (PHP_SAPI != 'cli') {
	die("nope!");
    }

    include_once('../webroot/config.php');
    include_once('../webroot/helper.php');

    while(true) {
	echo "Refreshing characters....\n";
	character_refresh();
	echo "done. sleeping.\n";
	sleep(600);
    }
?>
