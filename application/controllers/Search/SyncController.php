<?php
/**
 * Created by PhpStorm.
 * User: tim
 * Date: 2018/11/13
 * Time: 3:47 PM
 */

require_once(APPLICATION_PATH . "/../library/Aliyun/OpenSearch/Autoloader/Autoloader.php");

use OpenSearch\Client\OpenSearchClient;
use OpenSearch\Client\DocumentClient;

class Search_SyncController extends DM_Controller
{
    public function startAction()
    {

//替换对应的access key id
        $accessKeyId = 'b9b26oCc0KBIE4WL';
//替换对应的access secret
        $secret = 'tjv9AF23wecf2dt2IlWVZjV4HPLKZm';
//替换为对应区域api访问地址，可参考应用控制台,基本信息中api地址
        $endPoint = 'http://opensearch-cn-hangzhou.aliyuncs.com';
//替换为应用名
        $appName = 'wx_messages';
        //应用表名
        $tableName = 'messages';
//开启调试模式
        $options = array('debug' => true);
//创建OpenSearchClient客户端对象
        $client = new OpenSearchClient($accessKeyId, $secret, $endPoint, $options);

        $documentClient = new DocumentClient($client);

        $mMessage = new Model_Message();
        $mConfig = new Model_Config();
        $last_message_id = $mConfig->get("message.last_id");
        $messageData = $mMessage->getAllData($last_message_id);
        $data = [];
        foreach($messageData as $key => $datum){
            $item = [];
            $item['cmd'] = 'ADD';
            $item["fields"] = [
                'message_id'    =>  $datum['MessageID'],
                'receiverwx'    =>  $datum['ReceiverWx'],
                'senderwx'      =>  $datum['SenderWx'],
                'content'       =>  $datum['Content'],
                'adddate'       =>  strtotime($datum['AddDate'])
            ];
            $data[] = $item;
            $last_message_id = $datum['MessageID'];
        }
        $mConfig->set('message.last_id', $last_message_id);
//将文档编码成json格式
        $json = json_encode($data);
//提交推送文档
        $ret = $documentClient->push($json, $appName, $tableName);
//打印调试信息
        echo $ret->traceInfo->tracer;
    }
}