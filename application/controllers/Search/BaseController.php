<?php
/**
 * Created by PhpStorm.
 * User: tim
 * Date: 2018/11/13
 * Time: 7:02 PM
 */

require_once(APPLICATION_PATH . "/../library/Aliyun/OpenSearch/Autoloader/Autoloader.php");

use OpenSearch\Client\OpenSearchClient;

class Search_BaseController extends DM_Controller
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
        //替换为应用名
        $appName = $this->_config['aliyun']['search']['appName'];
        //应用表名
        $tableName = $this->_config['aliyun']['search']['tableName'];
        $options = array('debug' => false);
        //创建OpenSearchClient客户端对象
        $this->_client = new OpenSearchClient($accessKeyId, $secret, $endPoint, $options);
    }
}