<?php
    ini_set('display_errors','On');
    error_reporting(E_ALL);
    setlocale(LC_MONETARY, 'en_GB');

    // Path defines
    define('WEBROOT', $_SERVER['DOCUMENT_ROOT']);
    define('ROOT', dirname($_SERVER['DOCUMENT_ROOT']));

    // Load core library
    require_once(ROOT . '/lib/Core/Core.php');
    $core = new Core();
?>
