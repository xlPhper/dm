<?php
date_default_timezone_set("Asia/Shanghai");
// Define path to application directory
defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../application'));

// Define application environment
if(isset($_REQUEST['debug'])) {
    define('APPLICATION_ENV','development');
}else {
    defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));
}
// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
    realpath(APPLICATION_PATH . '/../library'),
    get_include_path(),
)));


require_once APPLICATION_PATH . '/configs/const.php';

require_once APPLICATION_PATH . '/../vendor/autoload.php';

/** Zend_Application */
require_once 'Zend/Application.php';

// Create application, bootstrap, and run
$application = new Zend_Application(
    APPLICATION_ENV,
    APPLICATION_PATH . '/configs/application.ini'
);

Zend_Registry::set("config", $application->getOptions());

$application->bootstrap()
            ->run();