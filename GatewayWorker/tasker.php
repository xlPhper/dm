<?php

ini_set('display_errors', 'on');
use Workerman\Worker;
use \GatewayWorker\Lib\Gateway;

if (strpos(strtolower(PHP_OS), 'win') === 0) {
    exit("start.php not support windows, please use start_for_win.bat\n");
}

// 检查扩展
if (!extension_loaded('pcntl')) {
    exit("Please install pcntl extension. See http://doc3.workerman.net/appendices/install-extension.html\n");
}

if (!extension_loaded('posix')) {
    exit("Please install posix extension. See http://doc3.workerman.net/appendices/install-extension.html\n");
}

// 标记是全局启动
define('GLOBAL_START', 1);

define('ROOT_PATH', dirname(__FILE__));

require_once ROOT_PATH . '/../application/configs/const.php';

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/workerman/workerman/Autoloader.php';

// task worker，使用Text协议
// task进程数可以根据需要多开一些
$task_worker = new Worker('Text://0.0.0.0:8284');
$task_worker->count = 400;
$task_worker->name = 'TaskWorker';
$task_worker->onMessage = function ($connection, $task_data) {
    $task_data = json_decode($task_data, 1);
    Gateway::sendToClient($task_data['client_id'], json_encode($task_data['data']));
    $connection->send('ok');
};

Worker::runAll();