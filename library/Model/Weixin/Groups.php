<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/23
 * Time: 18:15
 */
class Model_Weixin_Groups extends DM_Model
{
    public static $table_name = "weixin_in_groups";
    protected $_name = "weixin_in_groups";
    protected $_primary = "ID";


    /**
     * 获取群的成员(管理员优先)
     * @param $GroupID 群ID
     */
    public function findGroupUser($GroupID)
    {

        $select = $this->select()
            ->from($this->_name.' as g','WeixinID')->setIntegrityCheck(false)
            ->join('weixins as w','w.WeixinID = g.WeixinID','Nickname')
            ->where('GroupID = ?',$GroupID)
            ->order('IsAdmin DESC');

        return $this->_db->fetchRow($select);
    }


    /**
     * 根据微信查询群信息
     * @param $WeixinIds  微信号ID
     */
    public function findWeixinGroup($WeixinIds)
    {
        $select = $this->select()->from($this->_name,'GroupID')->where('WeixinID in (?)',$WeixinIds)->group('GroupID');
        $data = $this->_db->fetchAll($select);
        $res = array();
        foreach ($data as $v){
            $res[] = $v['GroupID'];
        }
        return $res;
    }

    /**
     * 查询指定微信ID是否是该群的人
     * @param $WeixinID 微信ID
     * @param $GroupID  群ID
     * @return array
     */
    public function findWeixinIsGroup($WeixinID,$GroupID)
    {
        $select = $this->select()->where('WeixinID =?',$WeixinID)->where('GroupID = ?',$GroupID);
        return $this->_db->fetchRow($select);
    }

    /**
     * 查询指定微信ID是否是该群的人
     * @param $WeixinID 微信ID
     * @param ChatroomID  微信群标识
     * @return array
     */
    public function findWeixinInGroup($WeixinID,$GroupID)
    {
        $select = $this->select()->where('WeixinID =?',$WeixinID)->where('GroupID = ?',$GroupID);
        return $this->_db->fetchRow($select);
    }

}