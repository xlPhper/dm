<?php
/**
 * 消息同步到阿里云
 *
 * by Tim
 */

require_once(APPLICATION_PATH . "/../library/Aliyun/OpenSearch/Autoloader/Autoloader.php");

use OpenSearch\Client\OpenSearchClient;
use OpenSearch\Client\DocumentClient;

class TaskRun_MessageSyncAliyun extends DM_Daemon
{
    const CRON_SLEEP = 5000000;
    const SERVICE = 'messageSyncAliyun';

    /**
     * 执行Daemon任务
     *
     */
    protected function run()
    {
        $this->done();
    }

    protected function done()
    {
        $config = Zend_Registry::get("config");
        //替换对应的access key id
        $accessKeyId = $config['aliyun']['access_key'];
        //替换对应的access secret
        $secret = $config['aliyun']['access_secert'];
        //替换为对应区域api访问地址，可参考应用控制台,基本信息中api地址
        $endPoint = $config['aliyun']['search']['endPoint'];
        //替换为应用名
        $appName = $config['aliyun']['search']['appName'];
        //应用表名
        $tableName = $config['aliyun']['search']['tableName'];
        $options = array('debug' => false);
        //创建OpenSearchClient客户端对象
        $client = new OpenSearchClient($accessKeyId, $secret, $endPoint, $options);

        $documentClient = new DocumentClient($client);

        $mMessage = new Model_Message();
        $mConfig = new Model_Config();
        $last_message_id = $mConfig->get("message.last_id");
        $messageData = $mMessage->getAllData($last_message_id);
        $data = [];
        foreach ($messageData as $key => $datum) {
            $item = [];
            $item['cmd'] = 'ADD';
            $item["fields"] = [
                'message_id' => $datum['MessageID'],
                'receiverwx' => $datum['ReceiverWx'],
                'msgtype'      =>  $datum['MsgType'],
                'senderwx' => $datum['SenderWx'],
                'content' => $datum['Content'],
                'adddate' => strtotime($datum['AddDate'])
            ];
            $data[] = $item;
            $last_message_id = $datum['MessageID'];
        }
        $mConfig->set('message.last_id', $last_message_id);
        //将文档编码成json格式
        $json = json_encode($data);
        //提交推送文档
        $documentClient->push($json, $appName, $tableName);

        //exit();
    }

    protected function init()
    {
        parent::init();
        self::getLog()->add("\n\n**********************定时更新**************************");
        self::getLog()->flush();
    }

    /**
     * 发现新版本的事件
     */
    protected function onNewReleaseFind()
    {
        self::getLog()->add('Found new release: ' . $this->getReleaseCheck()->getRelease() . ', will quit for update.');
        die();
    }

    /**
     * 系统运行过程检测到内存不够的事件
     */
    protected function onOutOfMemory()
    {
        self::getLog()->add('System find that daemon will be out of memory, will quit for restart.');
        die();
    }

}
