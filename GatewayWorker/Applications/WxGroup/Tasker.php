<?php

require_once ROOT_PATH . '/../GatewayWorker/vendor/workerman/workerman/Autoloader.php';
use \Workerman\Connection\AsyncTcpConnection;

class Tasker
{
    public static function send($client_id, $data, $type = 'client')
    {
        // 与远程task服务建立异步链接，ip为远程task服务的ip，如果是本机就是127.0.0.1，如果是集群就是lvs的ip
        $task_connection = new AsyncTcpConnection('Text://127.0.0.1:8284');
        // 任务及参数数据
        $task_data = array(
            'client_id' => $client_id,
            'data' => $data
        );
        // 发送数据
        $task_connection->send(json_encode($task_data));
        // 异步获得结果
        $task_connection->onMessage = function($task_connection, $task_result)
        {
            var_dump($task_result);
            // 获得结果后记得关闭链接
            $task_connection->close();
        };
        // 执行异步链接
        $task_connection->connect();
    }
}
