<?php
/**
 * Created by PhpStorm.
 * User: Tim
 * Date: 2018/7/14
 * Time: 23:15
 */
class Model_Phones extends DM_Model
{
    public static $table_name = "phones";
    protected $_name = "phones";
    protected $_primary = "PhoneID";

    public function findPhone($Phone,$CategoryID = null)
    {
        $select = $this->select();
        $select->where('Phone = ?',$Phone);
        if ($CategoryID){
            $select->where('CategoryID = ?',$CategoryID);
        }
        return $this->_db->fetchRow($select);
    }

    /**
     * 获取合法的手机号
     */
    public function getValidPhones(array $phones)
    {
        $tmpPhones = [];
        foreach ($phones as $phone) {
            $phone = trim($phone);
            if ('' !== $tmpPhones && !in_array($phone, $tmpPhones)) {
                $tmpPhones[] = $phone;
            }
        }

        $tmpPhones = implode(',',$tmpPhones);
        $sql = 'select * from `phones` where Phone in ('.$tmpPhones.')';
        $tmpPhonesInDb = $this->_db->fetchAll($sql);
        $validPhones = [];
        foreach ($tmpPhonesInDb as $p)
        {
            $validPhones[] = $p['Phone'];
        }
        return $validPhones;
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
        $total_sql = "select count(PhoneID)as `num` from `phones` where 1=1 ".$where;
        $send_sql = "select count(PhoneID) as `num` from `phones`  where FriendsState <>0 ".$where;
        $success_sql = "select count(PhoneID) as `num` from `phones`  where FriendsState = 1 ".$where;
        $consume_sql = "select count(PhoneID) as `num` from `phones`  where (FriendsState <> 0 or WeixinState =2) ".$where;
        $unsent_sql = "select count(PhoneID) as `num` from `phones`  where FriendsState = 0 and `WeixinState` in(0,1,3) ".$where;

        $weixin_sql = "select count(PhoneID) as `num` from `phones`  where `Detection` =1 AND `WeixinState` =1 ".$where;
        $notweixin_sql = "select count(PhoneID) as `num` from `phones`  where `Detection` =1 AND `WeixinState` =2 ".$where;
        $unknown_sql = "select count(PhoneID) as `num` from `phones`  where `Detection` =1 AND `WeixinState` in (0,3) ".$where;
        $not_detection_sql = "select count(PhoneID) as `num` from `phones`  where `Detection` = 0 ".$where;

        $total = $this->_db->fetchRow($total_sql,$bin);
        $send = $this->_db->fetchRow($send_sql,$bin);
        $success = $this->_db->fetchRow($success_sql,$bin);
        $consume = $this->_db->fetchRow($consume_sql,$bin);
        $unsent = $this->_db->fetchRow($unsent_sql,$bin);

        $weixin_num = $this->_db->fetchRow($weixin_sql,$bin);
        $notweixin_num = $this->_db->fetchRow($notweixin_sql,$bin);
        $unknown_num = $this->_db->fetchRow($unknown_sql,$bin);
        $not_detection_num = $this->_db->fetchRow($not_detection_sql,$bin);

        $data =[
            'Total'=>$total['num'],
            'Send'=>$send['num'],
            'Success'=>$success['num'],
            'SuccessRate'=> $send['num'] == 0?'0.00%':(number_format($success['num']/$send['num'],2,'.','')*100).'%',
            'Consume'=>$consume['num'],
            'Unsent'=>$unsent['num'],
            'WeixinNum'=>$weixin_num['num'],
            'NotWeixinNum'=>$notweixin_num['num'],
            'UnknownNum'=>$unknown_num['num'],
            'NotDetection'=>$not_detection_num['num'],
            'WxProportion'=>$total['num'] == 0?'0.00%':(number_format($weixin_num['num']/$total['num'],2,'.','')*100).'%',
            'UnsentProportion'=>$total['num'] == 0?'0.00%':(number_format($unknown_num['num']/$total['num'],2,'.','')*100).'%'
        ];
        return $data;

    }

    /**
     * 分类标记手机号
     */
    public function getPhones($category_ids = null)
    {
        $where = '';
        if ($category_ids){
            $category_ids = implode(',',$category_ids);
            $where = ' and p.CategoryID in ('.$category_ids.')';
        }
        $sql = "select count(p.CategoryID) as Num,c.Name,p.CategoryID from `phones` as p join `categories` as c on c.CategoryID=p.`CategoryID` WHERE c.`Type`='".CATEGORY_TYPE_PHONE."' AND p.`WeixinState` in(3,1) and p.`FriendsState` = 0 and Detection =1".$where." GROUP BY p.CategoryID";
        $res = $this->_db->fetchAll($sql);
        return $res;

    }

    /**
     * 分类标记手机号
     */
    public function getPhonesAll()
    {
        $sql = "select count(p.CategoryID) as Num,c.Name,p.CategoryID from `phones` as p join `categories` as c on c.CategoryID=p.`CategoryID` WHERE c.`Type`='".CATEGORY_TYPE_PHONE."' GROUP BY p.CategoryID";
        $res = $this->_db->fetchAll($sql);
        return $res;

    }

    /**
     * 指定分类的分类名和手机号数量
     */
    public function getCategoryPhones($CategoryID)
    {
        $sql = "select count(p.CategoryID) as Num,c.Name from `phones` as p join `categories` as c on c.CategoryID=p.`CategoryID` WHERE p.CategoryID ={$CategoryID} GROUP BY p.CategoryID";
        return $this->_db->fetchRow($sql);
    }

    /**
     * 查询指定类型的手机号信息
     */
    public function findCategory($CategoryID,$limit)
    {
        $where = '';
        $bin = [];
        if ($CategoryID != 'all'){
            $where = "  AND `CategoryID` = ?";
            $bin=[$CategoryID];
        }
        $sql = "select `Phone` from `phones` WHERE `WeixinState` = 0 AND `FriendsState` = 0 ".$where.' limit '.$limit;
        $phones = $this->_db->fetchAll($sql,$bin);
        $res =array();
        foreach ($phones as $val){
            $res[] = $val['Phone'];
        }
        return $res;
    }

    /**
     * 判断该分类下是否有手机号
     */
    public function findIsCategory($CategoryID)
    {
        $select = $this->select()->from($this->_name,'COUNT(*) as Num')->where('CategoryID = ?',$CategoryID);
        return $this->_db->fetchRow($select);
    }


    /**
     * 总数
     */
    public function getTotal()
    {
        $total_sql = "select count(PhoneID) as `total` from `phones`";
        return $this->_db->fetchRow($total_sql);
    }

    /**
     * 随机查询手机信息
     */
    public function getPhonesLimit($num,$category_id)
    {
        $where = '';
        if ($category_id){
            $where = 'AND CategoryID = '.$category_id;
        }
        $sql = "select `Phone` from `phones` where `Phone` REGEXP '^[1][35678][0-9]{9}$' AND WeixinState in (3,1) AND FriendsState = 0 {$where} limit {$num} for update";
        return $this->_db->fetchAll($sql);
    }


    /**
     * 选择未检测的手机号数据
     */
    public function findDetectionPhone($limit = 100)
    {
        $select = $this->select();
        $select->where('Detection = 0');
        $select->order('PhoneID ASC');
        $select->limit($limit);
        $data = $this->_db->fetchAll($select);

        $res = array();
        foreach ($data as $v){
            $res[] = $v['Phone'];
        }

        return $res;
    }


    /**
     * 微信号查找
     */
    public function findWeixin($Weixin)
    {
        $select = $this->select();
        $select->from($this->_name)
            ->where("Weixin = ?", $Weixin);
        return $this->_db->fetchRow($select);

    }

    /**
     * 搜索当前日期的手机发送数
     */
    public function findSendNum($weixins = null,$day)
    {
        $select = $this->select()->from($this->_name);
        $select->where("FriendsState = 1 or FriendsState = 2");
        $select->where("SendDate = ?", $day);
        if ($weixins){
            $select->where("SendWeixin in (?)", $weixins);
        }
        return $this->_db->fetchAll($select);
    }

    /**
     * 检测不是微信号的手机号
     * @param $phones
     * @return array
     */
    public function isNotWeixin($phones)
    {
        $select = $this->select();
        $select->from($this->_name)
            ->where("WeixinState = 2")
            ->where("Phone in (?)", $phones);
        return $this->_db->fetchAll($select);
    }


    /**
     * 根据手机号查询手机信息
     *
     * @param $phones
     */
    public function getSendWeixin($phones,$page,$pagesize,$errorCode,$sendState,$friendsState)
    {

        $select = $this->fromSlaveDB()->select()->from($this->_name.' as p',['p.PhoneID','p.Phone','p.SendError','p.FriendsState','p.Weixin as PhoneWeixin','p.Avatar as PhoneAvatar','p.Nickname as PhoneNickname'])->setIntegrityCheck(false);
        $select->joinLeft('weixins as w','w.Weixin = p.SendWeixin',['w.Nickname as SendNickname','w.Weixin as SendWeixin','w.Alias as SendAlias','w.AvatarUrl as SendAvatar']);
        $select->where("p.Phone in (?)", $phones);
        if ($friendsState == 1){
            $select->where("p.FriendsState = 4");
        }elseif ($friendsState == 2){
            $select->where("p.FriendsState <> 4");
        }
        if ($errorCode != null){
            if ($errorCode == -24){
                $select->where("p.SendError like ?",'%被搜帐号状态异常，无法显示%')->orWhere('p.SendError like ?','%搜尋的帳號狀態異常，無法顯示%');
            }elseif ($errorCode == -25){
                $select->where("p.SendError like ?",'%操作过于频繁，请稍后再试%')->orWhere('p.SendError like ?','%操作過於頻繁，請稍後再試%');
            }else{
                $select->where("p.SendError like ?", '%err_code='.$errorCode.'%');
            }
        }
        if ($sendState != null){
            $select->where("p.FriendsState = ?", $sendState);
        }
        return $this->getResult($select,$page,$pagesize);
    }


    /**
     * 任务失败对应的手机号回复可用
     */
    public function savePhoneState($Phones)
    {
        $this->update(['FriendsState'=>0,'SendDate'=>'0000-00-00'],['Phone in (?)'=>$Phones]);
    }
}