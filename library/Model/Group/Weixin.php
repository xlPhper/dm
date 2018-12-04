<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/10
 * Time: 10:03
 */
class Model_Group_Weixin extends DM_Model
{
    public static $table_name = "group_weixins";
    protected $_name = "group_weixins";
    protected $_primary = "ID";

    public function join($GroupID, $WeixinID)
    {
        //更新群组与微信号的关系表
        $select = $this->_db->select();
        $select->from($this->_name)
               ->where("WeixinID = ?", $WeixinID)
               ->where("GroupID = ?", $GroupID);
        $info = $this->_db->fetchRow($select);
        if(!isset($info['ID'])){
            $data = [
                'GroupID'   =>  $GroupID,
                'WeixinID'  =>  $WeixinID,
                'Status'    =>  'IN',
                'UpdateDate'  =>  date("Y-m-d H:i:s")
            ];
            $this->insert($data);
            //groups增加weixin数量
            $sql = "update groups set WeixinNum = WeixinNum + 1 where GroupID = '{$GroupID}'";
            $this->_db->query($sql);
        }
    }

    public function quit($GroupID, $WeixinID)
    {
        //更新群组与微信号的关系表
        $select = $this->_db->select();
        $select->from($this->_name)
            ->where("WeixinID = ?", $WeixinID)
            ->where("GroupID = ?", $GroupID);
        $info = $this->_db->fetchRow($select);
        if(isset($info['ID'])){
            $data = [
                'Status'    =>  'OUT',
                'UpdateDate'  =>  date("Y-m-d H:i:s")
            ];
            $where = $this->_db->quoteInto("ID = ?", $info['ID']);
            $this->update($data, $where);
            //groups减少weixin数量
            $sql = "update groups set WeixinNum = WeixinNum - 1 where GroupID = '{$GroupID}'";
            $this->_db->query($sql);
        }
    }
}