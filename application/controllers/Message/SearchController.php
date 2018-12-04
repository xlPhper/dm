<?php
/**
 * Created by PhpStorm.
 * User: tim
 * Date: 2018/11/13
 * Time: 7:06 PM
 */

require_once(APPLICATION_PATH . "/../library/Aliyun/OpenSearch/Autoloader/Autoloader.php");

use OpenSearch\Client\OpenSearchClient;
use OpenSearch\Client\SearchClient;
use OpenSearch\Util\SearchParamsBuilder;
use OpenSearch\Client\DocumentClient;

class Message_SearchController extends DM_Controller
{
    protected $_client = null;

    public function init()
    {
        parent::init();

        //替换对应的access key id
        $accessKeyId = $this->_config['aliyun']['access_key'];
        //替换对应的access secret
        $secret = $this->_config['aliyun']['access_secert'];
        //替换为对应区域api访问地址，可参考应用控制台,基本信息中api地址
        $endPoint = $this->_config['aliyun']['search']['endPoint'];

        $options = array('debug' => false);
        //创建OpenSearchClient客户端对象
        $this->_client = new OpenSearchClient($accessKeyId, $secret, $endPoint, $options);
    }

    /**
     * 搜索
     */
    public function keywordAction()
    {
        $keyword = $this->_getParam('keyword');
        $receiverwx = $this->_getParam('receiverwx');
        $senderwx = $this->_getParam('senderwx');
        $startdate = $this->_getParam('startdate');
        $enddate = $this->_getParam('enddate');
        $msgtype = $this->_getParam('msgtype');

        //替换为应用名
        $appName = $this->_config['aliyun']['search']['appName'];
        // 实例化一个搜索类
        $searchClient = new SearchClient($this->_client);
        // 实例化一个搜索参数类
        $params = new SearchParamsBuilder();
        //设置config子句的start值
        $params->setStart(0);
        //设置config子句的hit值
        $params->setHits(20);
        // 指定一个应用用于搜索
        $params->setAppName($appName);
        // 指定搜索关键词
        $query_data = [];
        if($keyword){
            $query_data[] = "default:'{$keyword}'";
        }
        if($receiverwx){
            $query_data[] = "receiverwx:'{$receiverwx}'";
        }
        if($senderwx){
            $query_data[] = "senderwx:'{$senderwx}'";
        }
        if($startdate){
            $query_data[] = "adddate>='".strtotime($startdate)."'";
        }
        if($enddate){
            $query_data[] = "adddate<='".strtotime($enddate)."'";
        }
        if($msgtype){
            $query_data[] = "msgtype:'{$msgtype}'";
        }
        echo implode(" AND ", $query_data);
        $params->setQuery(implode(" AND ", $query_data));
        // 指定返回的搜索结果的格式为json
        $params->setFormat("fulljson");
        //添加排序字段
        $params->addSort('message_id', SearchParamsBuilder::SORT_DECREASE);
        // 执行搜索，获取搜索结果
        $ret = $searchClient->execute($params->build());
        // 将json类型字符串解码
        Zend_Debug::dump(json_decode($ret->result,true));
    }

    public function getFormatAction()
    {
        $mMessage = new Model_Message();
        $messageData = $mMessage->getAllData(0, 10);
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
        }

        echo json_encode($data);
    }

    /**
     * 测试推送到阿里云
     * @throws Zend_Exception
     */
    public function pushtestAction()
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
        $options = array('debug' => true);
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
        $ret = $documentClient->push($json, $appName, $tableName);

        echo $ret->traceInfo->tracer;
    }
}