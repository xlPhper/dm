<?php
/**
 * Created by PhpStorm.
 * User: Ekko
 * Date: 2018/9/6
 * Ekko: 23:15
 */
class Model_Sendweixin extends DM_Model
{
    public static $table_name = "send_weixins";
    protected $_name = "send_weixins";
    protected $_primary = "SendWeixinID";

    public function findWeixin($categoryId = null,$weixin)
    {
        $select = $this->select();
        if ($categoryId){
            $select->where('CategoryID = ?',$categoryId);
        }
        $select->where('Weixin = ?',$weixin);
        return $this->_db->fetchRow($select);
    }

    /**
     * 统计好友所使用
     *
     * @param array $Weixins
     * @param bool $group
     * @return array
     */
    public function findWeixins($Weixins)
    {
        $select = $this->select()->from($this->_name,['COUNT(DISTINCT Weixin) AS Num']);
        if (!empty($Weixins)){
            $select->where('Weixin in (?) or WxAccount in (?)',$Weixins);
        }
        $select->where('Status = 1');
        return $this->_db->fetchRow($select);
    }

    /**
     * 获取合法的微信号
     */
    public function getValidWeixins(array $weixins)
    {
        $tmpWeixins = [];
        foreach ($weixins as $weixin) {
            $weixin = trim($weixin);
            if ('' !== $tmpWeixins && !in_array($weixin, $tmpWeixins)) {
                $tmpWeixins[] = $weixin;
            }
        }

        $tmpWeixins = implode(',',$tmpWeixins);
        $sql = 'select * from `send_weixins` where Weixin in ('.$tmpWeixins.')';
        $tmpWeixinsInDb = $this->_db->fetchAll($sql);
        $validWeixins = [];
        foreach ($tmpWeixinsInDb as $p)
        {
            $validWeixins[] = $p['Weixin'];
        }
        return $validWeixins;
    }

    /**
     * 获取当前分类信息
     */
    public function getCategory($CategoryID)
    {
        $where = '';
        $bin = array();
        if ($CategoryID !=''){
            $where = ' AND `CategoryID` = ?';
            $bin[] = $CategoryID;
        }
        $total_sql = "select count(SendWeixinID)as `num` from `send_weixins` where 1=1 ".$where;
        $send_sql = "select count(SendWeixinID) as `num` from `send_weixins`  where Status <> 0  ".$where;
        $success_sql = "select count(SendWeixinID) as `num` from `send_weixins`  where Status =1 ".$where;

        $total = $this->_db->fetchRow($total_sql,$bin);
        $send = $this->_db->fetchRow($send_sql,$bin);
        $success = $this->_db->fetchRow($success_sql,$bin);
        $data =[
            'Total'=>$total['num'],
            'SendNum'=>$send['num'],
            'SendSuccessNum'=>$success['num'],
            'SendSuccessRate'=>$send['num'] == 0?'0.00%':(number_format($success['num']/$send['num'],2,'.','')*100).'%',
        ];
        return $data;

    }

    /**
     * 分类标记微信号
     */
    public function getSendWeixins()
    {
        $sql = "select count(s.CategoryID) as Num,c.Name,s.CategoryID from `send_weixins` as s join `categories` as c on c.CategoryID=s.`CategoryID` WHERE c.`Type`='".CATEGORY_TYPE_SENDWEIXIN."' AND s.`Status` = 0 GROUP BY s.CategoryID";
        $res = $this->_db->fetchAll($sql);
        return $res;

    }

    /**
     * 分类标记微信号
     */
    public function getSendWeixinsAll()
    {
        $sql = "select count(s.CategoryID) as Num,c.Name,s.CategoryID from `send_weixins` as s join `categories` as c on c.CategoryID=s.`CategoryID` WHERE c.`Type`='".CATEGORY_TYPE_SENDWEIXIN."' GROUP BY s.CategoryID";
        return $this->_db->fetchAll($sql);

    }



    /**
     * 指定分类的分类名和微信数量
     */
    public function getCategoryWeixins($CategoryID)
    {
        $sql = "select count(s.CategoryID) as Num,c.Name from `send_weixins` as s join `categories` as c on c.CategoryID=s.`CategoryID` WHERE s.CategoryID ={$CategoryID} GROUP BY s.CategoryID";
        return $this->_db->fetchRow($sql);
    }


    /**
     * 获取该微信号指定日期的发送微信号信息 发送成功的
     */
    public function findSendNum($weixins,$day)
    {
        $select = $this->select()->from($this->_name);
        $select->where("SendDate = ?", $day);
        $select->where("Status = 1");
        if ($weixins){
            $select->where("SendWeixin in (?)", $weixins);
        }
        return $this->_db->fetchAll($select);
    }


    /**
     * 任务失败资源修改
     */
    public function saveSendweixinState($weixins)
    {
        $this->update(['Status'=>0,'SendDate'=>'0000-00-00'],['Weixin in (?)'=>$weixins]);
    }
}