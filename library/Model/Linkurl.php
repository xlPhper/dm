<?php
/**
 * Created by PhpStorm.
 * User: Ekko
 * Date: 2018/10/11
 * Ekko: 15:26
 */
class Model_Linkurl extends DM_Model
{
    public static $table_name = "linkurl";
    protected $_name = "linkurl";
    protected $_primary = "LinkurlID";

    const GATHER_URL_TYPE_SEARCH = 1; //搜索地址
    const GATHER_URL_TYPE_LIST = 2; //列表地址
    const GATHER_URL_TYPE_DETAIL = 3; //列表地址

    const GATHER_CHANNEL_DOUBAN = 1; //采集渠道:豆瓣

    const STATUS_PENDING = 0; //待执行
    const STATUS_GATHERED = 1; //已抓取html
    const STATUS_ANALYSED = 2; //已解析html

    const GET_PROXY_URL = 'https://aphp.duomai.com/proxy_index/get-by-from/?from=0'; //获取代理服务器地址

    public function getTableName()
    {
        return $this->_name;
    }

    public function getByID($ID)
    {
        $select = $this->select()
            ->where("LinkurlID = ?", $ID);
        return $this->_db->fetchRow($select);
    }

    /**
     * 获取所有需要采集的url地址
     */
    public function findUrl()
    {
        $select = $this->select()
            ->from($this->_name,['LinkurlID','Url'])
            ->where("Status = 0");
        return $this->_db->fetchAll($select);
    }




    public function getQuerySelect($where = [], $fromSlaveDB = true){
        if($fromSlaveDB){
            $select = $this->fromSlaveDB()->select();
        }else{
            $select = $this->select();
        }
        foreach ($where as $k=>$v){
            switch ($k){
                case 'Channel':{
                    if(is_array($v) && !empty($v)){
                        $select->where("$k IN (?)", $v);
                    }elseif($v !== '') {
                        $select->where("$k = ?", $v);
                    }
                    break;
                }

                case 'Url':{
                    if($v !== ""){
                        $select->where("$k = ?", $v);
                    }
                    break;
                }

                case 'Status':{
                    if($v !== ''){
                        $select->where("$k = ?", $v);
                    }
                    break;
                }

                case 'Type':{
                    if(in_array($v, [self::GATHER_URL_TYPE_SEARCH, self::GATHER_URL_TYPE_LIST, self::GATHER_URL_TYPE_DETAIL])){
                        $select->where("$k = ?", $v);
                    }
                    break;
                }

                default:break;
            }
        }
        return $select;
    }
}