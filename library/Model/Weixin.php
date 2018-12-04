<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/24
 * Time: 14:41
 */
class Model_Weixin extends DM_Model
{
    public static $table_name = "weixins";
    protected $_name = "weixins";
    protected $_primary = "WeixinID";

    public function getInfoByWeixin($weixin)
    {
        $select = $this->select();
        $select->from($this->_name)
            ->where("Weixin = ?", $weixin);
        return $this->_db->fetchRow($select);
    }

    public function getDataByWeixinID($WeixinID)
    {
        $select = $this->select();
        $select->from($this->_name)
            ->where("WeixinID = ?", $WeixinID);
        return $this->_db->fetchRow($select);
    }

    public function getWeixinIDByDevice($DeviceID)
    {
        $select = $this->select();
        $select->from($this->_name, ['WeixinID'])
               ->where("DeviceID = ?", $DeviceID);
        return $this->_db->fetchCol($select);
    }

    public function findByID($wxId)
    {
        $select = $this->select();
        $select->where('WeixinID = ?',$wxId);
        return $this->_db->fetchRow($select);
    }

    public function findChannel($channelId)
    {
        $select = $this->select();
        $select->where('Channel = ?',$channelId);
        $data = $this->_db->fetchAll($select);

        $res = [];
        foreach ($data as $v){
            $res []=$v['WeixinID'];
        }

        return $res;
    }

    public function findByAdminID($adminId)
    {
        $select = $this->select()->from($this->_name);
        $select->where('AdminID = ?',$adminId);
        return $this->_db->fetchAll($select);
    }

    /**
     * 获取微信ids
     */
    public function getWxIdsByAdminId($adminId)
    {
        $select = $this->select()->from($this->_name, ['WeixinID']);
        $select->where('AdminID = ?',$adminId);
        return $this->_db->fetchCol($select);
    }

    /**
     * @param $adminID
     * @return array
     * 获取运营管理员下的微信ids
     */
    public function getWxIdsByYyAdminId($adminID){
        $select = $this->select()->from($this->_name, ['WeixinID']);
        $select->where('FIND_IN_SET(?, YyAdminID)', $adminID);
        return $this->_db->fetchCol($select);
    }

    /**
     * 获取微信s
     */
    public function getWeixinsByAdminId($adminId, $cols = ['*'])
    {
        $select = $this->select()->from($this->_name, $cols);
        $select->where('AdminID = ?',$adminId);
        return $this->_db->fetchAll($select);
    }

    /**
     * 模糊搜索
     */
    public function searchWeixin($search)
    {
        $select = $this->select();
        $select->from($this->_name,['WeixinID','Weixin','Alias']);
        $select->where("Weixin like ? OR Alias like ?","%".$search."%");
        $select->limit(10);
        return $this->_db->fetchAll($select);
    }

    /**
     * 获取微信帐号信息
     */
    public function getWeixinAccount($weixinIds)
    {
        $select = $this->fromSlaveDB()->select();
        $select->from($this->_name,['Weixin','Alias']);
        $select->where("WeixinID in (?)",$weixinIds);
        $data = $this->_db->fetchAll($select);
        $res = [];
        foreach ($data as $w){
            $res[] = $w['Weixin'];
            if (!empty($w['Alias'])){
                $res[] = $w['Alias'];
            }
        }
        return $res;
    }

    /**
     * 切换微信
     *
     * @param $DeviceID
     * @param $WeixinID
     */
    public function changeWeixin($DeviceID, $WeixinID, $Level = TASK_LEVAL_MEDIUM)
    {
        $deviceModel = new Model_Device();
        $deviceInfo = $deviceModel->getInfo($DeviceID);

        if($deviceInfo['OnlineWeixinID'] == $WeixinID){
            return true;
        }

        $weixinInfo = $this->getInfo($WeixinID);

        $taskModel = new Model_Task();

        $data = [
            'DeviceID'  =>  $DeviceID,
            'TaskConfig'    =>  [
                'Weixin'    =>  $weixinInfo['Weixin'],
                'Config'    =>  $weixinInfo['Config']
            ],

        ];
        return $taskModel->add("DeviceChangeWeixinTask", $data);
    }

    /**
     * 分类标记微信
     */
    public function findWeixins()
    {
        $sql = "select count(c.CategoryID) as Num,c.Name,c.CategoryID from `weixins` as w join `categories` as c on find_in_set(c.CategoryID,w.`CategoryIds`) GROUP BY c.CategoryID";

        return $this->_db->fetchAll($sql);
    }

    /**
     * 指定分类的分类名和微信号号数量
     */
    public function getCategoryWeixins($CategoryID)
    {
        $sql = "select count(*) as Num from `weixins` WHERE find_in_set({$CategoryID},`CategoryIds`)";
        return $this->_db->fetchRow($sql);
    }

    /**
     * 查询微信来源分类
     */
    public function findWeixinChannel()
    {
        $sql = "select `Channel` from `weixins` WHERE `Channel`<>'' GROUP BY `Channel`";
        $res = $this->_db->fetchAll($sql);
        return $res;
    }

    public function findWeixinlist($DeviceID,$CategoryID = null,$Serch = null,$Channel = null)
    {
        $where = "where DeviceID = ?";
        $bind[] = $DeviceID;
        if ($CategoryID){
            $where .= " and FIND_IN_SET(?,CategoryIds)";
            $bind[] = $CategoryID;
        }
        if ($Serch){
            $where .= " and Weixin like ?  OR Nickname like ?";
            $bind[] = "%".$Serch."%";
            $bind[] = "%".$Serch."%";
        }
        if ($CategoryID){
            $where .= " and Channel = ?";
            $bind[] = $Channel;
        }
        $sql = "select `WeixinID`,`Nickname`,`FriendNumber`,`Position`,`Address`,`CategoryIds`,`GroupNum`,`AvatarUrl`,`AddDate` from `weixins`".$where;
        $res = $this->_db->fetchAll($sql,$bind);
        return $res;
    }

    /**
     * 判断该分类下是否有微信号
     */
    public function findIsCategory($CategoryID)
    {
        $select = $this->select()->from($this->_name,'COUNT(*) as Num');
        $select->where('FIND_IN_SET(?,CategoryIds)',$CategoryID);
        return $this->_db->fetchRow($select);
    }

    /**
     * 获取微信号
     */
    public function findIsWeixins($CategoryID,$Platform = PLATFORM_GROUP)
    {
        $select = $this->select();
        if ($Platform == PLATFORM_GROUP){
            $select->where('FIND_IN_SET(?,CategoryIds)',$CategoryID);
        }elseif ($Platform == PLATFORM_OPEN){
            $select->where('FIND_IN_SET(?,YyCategoryIds)',$CategoryID);
        }
        return $this->_db->fetchAll($select);
    }

    /**
     * 判断该渠道下是否有微信号
     */
    public function findIsChannel($CategoryID)
    {
        $select = $this->select()->from($this->_name,'COUNT(*) as Num');
        $select->where('Channel = ?',$CategoryID);
        return $this->_db->fetchRow($select);
    }

    /**
     * 获取所有微信号
     */
    public function findWeixinCode()
    {
        $sql = "select `Weixin` from `weixins`";
        $data = $this->_db->fetchAll($sql);
        $res = array();
        foreach ($data as $w){
            $res[]=$w['Weixin'];
        }
        return $res;
    }

    /**
     * 获取所有微信号
     */
    public function findWeixinAllID()
    {
        $sql = "select `WeixinID` from `weixins`";
        $data = $this->_db->fetchAll($sql);
        $res = array();
        foreach ($data as $w){
            $res[]=$w['WeixinID'];
        }
        return $res;
    }

    /**
     * 修改微信管理员
     */
    public function saveAdminID($AdminID,$WeixinIDs)
    {
        $sql = "update `weixins` set AdminID = ? where WeixinID in ({$WeixinIDs})";
        return $this->_db->query($sql,[$AdminID]);
    }

    /**
     * 查询现在管理微信号的管理员ID
     */
    public function findWeixinAdminID()
    {
        $select = $this->select()->group('AdminID')->where('AdminID != 0');
        $data = $this->_db->fetchAll($select);
        $res  = ['all'];
        foreach ($data as $val){
            $res[] = $val['AdminID'];
        }
        return $res;
    }

    /**
     * 统计管理-查询渠道
     */
    public function findChannels()
    {
        $select = $this->select()->group('Channel')->where("Channel <> ''");
        $data = $this->_db->fetchAll($select);
        $res  = ['all'];
        foreach ($data as $val){
            $res[] = $val['Channel'];
        }
        return $res;
    }

    /**
     * 管理所管理的微信号
     */
    public function findAdminWeixin($AdminID = 'all',$Day = '')
    {
        $select = $this->select()->from($this->_name,['WeixinID','FriendNumber']);
        if ($Day){
            $select->where('AddDate < ?',date('Y-m-d',strtotime($Day.' +1 day')));
        }
        if ($AdminID != 'all'){
            $select->where('AdminID = ?',$AdminID);
        }
        return $this->_db->fetchAll($select);
    }

    /**
     * 查询微信ids下的好友数量
     */
    public function findWeixinFrenidNum($data)
    {
        $select = $this->select()->from($this->_name,'FriendNumber')->where("WeixinID in (?)",$data);
        $res = $this->_db->fetchAll($select);
        $num = 0;
        foreach ($res as $val){
            $num += $val['FriendNumber'];
        }
        return $num;
    }


    /**
     * 根据标签查询微信号
     */
    public function findWeixinCategory($categoryIds = '')
    {
        $select = $this->select();
        if ($categoryIds){
            $where_msg ='';
            $category_data = explode(',',$categoryIds);
            foreach($category_data as $w){
                if ((int)$w>0){
                    $where_msg .= "FIND_IN_SET(".$w.",CategoryIds) OR ";
                }
            }
            $where_msg = rtrim($where_msg,'OR ');
            $select->where($where_msg);
        }

        return $this->_db->fetchAll($select);
    }

    /**
     * 获取多个微信号信息
     */
    public function findWeixsCode($weixinIds)
    {
        $select = $this->select()->where('WeixinID in (?)',$weixinIds);

        $data = $this->_db->fetchAll($select);

        $res = array();

        foreach ($data as $v){
            $res[] = $v['Weixin'];
        }

        return $res;
    }


    /**
     * 通过WeixinID查询设备自定义编号
     */
    public function findSerialNum($WeixinID)
    {
        $select = $this->select()
            ->from($this->_name.' as w','')
            ->setIntegrityCheck(false)
            ->join('devices as d','d.DeviceID = w.DeviceID','SerialNum')
            ->where('WeixinID = ?',$WeixinID);
        return $this->_db->fetchRow($select);
    }

    /**
     * 获取账号对应的昵称 返回 二维数组
     * @param $Weixins
     * @return array [{Weixin:,Nickname:}]
     */
    public function getNickName($Weixins)
    {
        if(count($Weixins)==0){
            return [];
        }
        $sql = "SELECT `Weixin` AS `Weixin`, `Nickname` AS `Nickname` FROM `weixins`
            WHERE ( `Weixin` in ('".implode("','",$Weixins)."') ) UNION
        SELECT `Alias` AS `Weixin`, `Nickname` AS `Nickname` FROM  `weixins`
            WHERE ( `Alias` in ('".implode("','",$Weixins)."') )";
        $res = $this->getAdapter()->query($sql)->fetchAll();
        return $res;
    }

    /**
     * 查询微信详情
     * @param $WeixinID 微信ID
     * @param $Weixin   微信号
     */
    public function WxDetail($WeixinID,$Weixin)
    {
        $select = $this->select()->from($this->_name.' as w')->setIntegrityCheck(false);
        $select->joinLeft('devices as d','w.DeviceID = d.DeviceID','d.SerialNum');
        if ($WeixinID > 0) {
            $select->where('w.WeixinID = ?',$WeixinID);
        } else {
            $select->where('w.Weixin = ?',$Weixin);
        }

        return $this->_db->fetchRow($select);
    }
    /**
     * 根据标签查询微信号数量
     */
    public function findWeixinNum($CategoryIds)
    {
        $where_msg ='';
        if(empty($CategoryIds)){
            return 0;
        }
        $select = $this->select()->from($this->getTableName(),"count(*) as Num");
        $category_data = explode(',',$CategoryIds);
        foreach($category_data as $w){
            $where_msg .= "FIND_IN_SET(".$w.",CategoryIds) OR ";
        }
        $where_msg = rtrim($where_msg,'OR ');
        $select->where($where_msg);
        return $select->query()->fetchColumn();
    }

    /**
     * 微信号添加好友数据统计
     * @param $CategoryIds  微信标签
     * @param $AdminID      微信管理员ID
     * @param $Search       搜索
     * @param $SerialNum    设备编号
     * @param $Page
     * @param $Pagesize
     * @return array
     */
    public function weixinJoinFirendStat($CategoryIds,$AdminID,$Search,$SerialNum,$Page,$Pagesize)
    {
        $select = $this->select()->from($this->_name.' as w',['w.WeixinID','w.Nickname'])->setIntegrityCheck(false);
        $select->joinLeft('devices as d','w.DeviceID = d.DeviceID','d.SerialNum');
        // 微信标签
        if ($CategoryIds){
            $select->where('FIND_IN_SET(?,CategoryIds)',$CategoryIds);
        }
        // 管理员
        if ($AdminID){
            $select->where('w.AdminID = ?',$AdminID);
        }
        // 管理员
        if ($Search){
            $select->where('w.Weixin LIKE ? OR w.Nickname LIKE ?','%'.$Search.'%');
        }
        // 设备编号
        if ($SerialNum){
            $select->where('d.SerialNum LIKE ?','%'.$SerialNum.'%');
        }

        return $this->getResult($select,$Page,$Pagesize);

    }

    public function getWeixins($weixinIds,$name = '')
    {
        $select = $this->select()->from($this->_name.' as w',['w.WeixinID','w.Weixin','w.Nickname','w.WxNotes'])->setIntegrityCheck(false);
        $select->joinLeft('devices as d','w.DeviceID = d.DeviceID','d.SerialNum');

        if ($weixinIds){
            $select->where('w.WeixinID in (?)',$weixinIds);
        }
        if (!empty($name)) {
            $select->where("w.Alias like ?  OR w.Weixin like ?  OR w.Nickname like ? OR d.SerialNum like ?", ["%".$name."%"]);
        }
        return $this->_db->fetchAll($select);
    }

    /**
     * 获取微信 支持账号和别名
     * @param string $Account Weixin or Alias
     * @return mixed
     */
    public function getWx($Account)
    {
        $select = $this->select();
        $select->from($this->_name)
            ->where("Weixin = ? or Alias = ?", $Account);
        return $this->_db->fetchRow($select);
    }
    /**
     * @param $weixinID
     * @return array
     * @throws Exception
     * 获取某个号微信发过朋友圈的所有素材ID
     */
    public static function getAlbumMateIDs($weixinID){
        $res = [];
        try{
            $mateIDs = Helper_Redis::getInstance()->hGet(Helper_Redis::weixinIDMateIDRelationKey(), $weixinID);
            if($mateIDs !== ''){
                $res = array_unique(array_filter(explode(',', $mateIDs)));
            }
        }catch(Exception $e){
        }
        return $res;
    }

    /**
     * @param $weixinID
     * @param $mateID
     * @throws Exception
     * 将发过朋友圈的素材ID存入此微信的关系表
     */
    public static function setAlbumMateID($weixinID, $addMateIDs){
        $mateIDs = self::getAlbumMateIDs($weixinID);
        $diffMateIDs = array_diff($addMateIDs, $mateIDs);
        if(!empty($diffMateIDs)){
            try{
                Helper_Redis::getInstance()->hSet(Helper_Redis::weixinIDMateIDRelationKey(), $weixinID, implode(',', array_merge($mateIDs, $diffMateIDs)));
            }catch(Exception $e){
            }
        }
    }

    /**
     * 查询个号的标签列表
     */
    public function getWeixinCategoryList($CategoryIds)
    {
        $select = $this->fromSlaveDB()->select()->from($this->_name.' as w',[])->setIntegrityCheck(false);
        $select->joinLeft('categories as c','find_in_set(c.CategoryID,w.`YyCategoryIds`)',['COUNT(c.CategoryID) as Num','c.Name','c.CategoryID']);
        $select->group('c.CategoryID');
        $select->where('c.CategoryID in (?)',$CategoryIds);
        return $this->_db->fetchAll($select);

    }

    public function getWeixinBySerialnum($SerialNum)
    {
        $select = $this->fromMasterDB()->select();
        $select->setIntegrityCheck(false)
               ->from("weixins as w")
               ->joinLeft("devices as d",'w.DeviceID = d.DeviceID','d.SerialNum')
               ->where("d.SerialNum like ?", "%{$SerialNum}");
        $data = $this->fromSlaveDB()->fetchAll($select);
        if(count($data) == 1){
            return array_shift($data->toArray());
        }else{
            foreach($data as $datum){
                $sarr = explode("-", $datum['SerialNum']);
                $end = array_pop($sarr);
                if($end == $SerialNum){
                    return $datum;
                }
            }
        }

        return false;
    }

}