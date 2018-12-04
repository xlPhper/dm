<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/11
 * Time: 16:41
 */
class Model_Weixin_Friend extends DM_Model
{
    public static $table_name = "weixin_friends";
    protected $_name = "weixin_friends";
    protected $_primary = "FriendID";

    const CHATRATE_NONE = 0; //无互动
    const CHATRATE_LOW = 1; //低频
    const CHATRATE_MIDDLE = 2; //中频
    const CHATRATE_HIGH = 3; //高频

    // 互动频率数据（带人数）
    static $_chatRateData = [
        Model_Weixin_Friend::CHATRATE_NONE => ['ChatRate' => Model_Weixin_Friend::CHATRATE_NONE, 'FriendNum' => '0', 'Name' => '无互动'],
        Model_Weixin_Friend::CHATRATE_LOW => ['ChatRate' => Model_Weixin_Friend::CHATRATE_LOW, 'FriendNum' => '0',  'Name' => '低频'],
        Model_Weixin_Friend::CHATRATE_MIDDLE => ['ChatRate' => Model_Weixin_Friend::CHATRATE_MIDDLE, 'FriendNum' => '0',  'Name' => '中频'],
        Model_Weixin_Friend::CHATRATE_HIGH => ['ChatRate' => Model_Weixin_Friend::CHATRATE_HIGH, 'FriendNum' => '0',  'Name' => '高频']
    ];

    public function add($data)
    {
        $info = $this->getUser($data['WeixinID'], $data['Account'], $data['Alias']);
        if(!isset($info['FriendID'])){
            $data['AddDate'] = date("Y-m-d H:i:s");
            $this->insert($data);
            if($this->_db->lastInsertId()){
                $weixiModel = new Model_Weixin();
                $weixiModel->addFriendNum($data['WeixinID']);
            }
        }
    }

    public function findAccount($Weixin)
    {
        $select = $this->_db->select();
        $select->from($this->_name)
            ->where("Account = ?", $Weixin);
        return $this->_db->fetchRow($select);
    }

    public function getUser($WeixinID, $Account = '', $Alias = '')
    {
        $select = $this->_db->select();
        $select->from($this->_name)
               ->where("WeixinID = ?", $WeixinID);
        if($Account) {
            $select->where("Account = ?", $Account);
        }
        if($Alias) {
            $select->where("Alias = ?", $Alias);
        }
        return $this->_db->fetchRow($select);
    }

    public function getUserByAccount($Account)
    {
        $select = $this->_db->select();
        $select->from($this->_name)
               ->where("Account in (?)", $Account);
        return $this->_db->fetchAll($select);
    }

    public function getDataByWeixinID($WeixinID)
    {
        $select = $this->select();
        $select->from($this->_name)
            ->where("WeixinID = ?", $WeixinID);
        return $this->_db->fetchAll($select);
    }


    /**
     * 获取好友数量
     * @param array $Weixinids  微信IDs
     * @return int
     */
    public function finWeixinFriends(array $Weixinids, $Day)
    {
        $select = $this->fromSlaveDB()->select();
        $select->from($this->_name,'count(FriendID) as FriendNum')
            ->where("WeixinID in (?)", $Weixinids)
            ->where('AddDate < ?',$Day.' 23:59:59')
            ->where('IsDeleted = 0');
        $data = $this->_db->fetchRow($select);
        return $data == false?0:(int)$data['FriendNum'];
    }

    /**
     * 获取微信好友的微信号
     */
    public function findWeixinFirendWx($WeixinID,$Limit = 1)
    {
        $select = $this->select();
        $select->from($this->_name)
            ->where("WeixinID = ?", $WeixinID)
            ->limit($Limit);
        $data = $this->_db->fetchAll($select);

        $res = array();

        foreach ($data as $v){
            $res[] = $v['Account'];
        }

        return $res;

    }

    /**
     * 微信号 当前日期下的新好友增加量
     * @param $WeixinID 微信ID
     * @param $Date   日期
     */
    public function getAddFriend($WeixinID = null,$Date = array())
    {
        $select = $this->fromSlaveDB()->select();
        $select->from($this->_name,["DATE_FORMAT(AddDate,'%Y-%m-%d') as AddDate","COUNT(FriendID) as FriendNum"]);
        $select->where("DATE_FORMAT(AddDate,'%Y-%m-%d') in (?)", $Date);
        if ($WeixinID){
            $select->where("WeixinID in (?)", $WeixinID);
        }
        $select->group("DATE_FORMAT(AddDate,'%Y-%m-%d')");
        $select->order('AddDate Desc');
        $data = $this->_db->fetchAll($select);
        $res = array();

        foreach ($data as $d){
            $res[$d['AddDate']] = $d;
        }

        return $res;
    }

    /**
     * 微信号 当前日期下的好友删除量
     * @param $WeixinID 微信ID
     * @param $Date   日期
     */
    public function getDelFriend($WeixinID = null,$Date = array())
    {
        $select = $this->fromSlaveDB()->select();
        $select->from($this->_name,["DATE_FORMAT(DeletedTime,'%Y-%m-%d') as DelDate","COUNT(FriendID) as FriendNum"]);
        $select->where("DATE_FORMAT(DeletedTime,'%Y-%m-%d') in (?)", $Date);
        if ($WeixinID){
            $select->where("WeixinID in (?)", $WeixinID);
        }
        $select->group("DATE_FORMAT(DeletedTime,'%Y-%m-%d')");
        $select->order('DeletedTime Desc');
        $data = $this->_db->fetchAll($select);
        $res = array();

        foreach ($data as $d){
            $res[$d['AddDate']] = $d;
        }

        return $res;

    }

    /**
     * 判断该分类下好友数量
     */
    public function getFriendNumsByCategoryID($TagID)
    {
        $select = $this->select()->from($this->_name,'COUNT(*) as Num');
        $select->where('FIND_IN_SET(?, CategoryIDs)',$TagID);
        return $this->_db->fetchRow($select);
    }

    /**
     * 根据群发信息返回个数信息
     * @param $weixinIDs 微信号数组
     * @param $categoryIDs 好友标签数组
     * @param $excludeWeixinIDs 排除的微信号数组
     * @return mixed 返回微信号个数 以及总好友数
     */
    public function getNumsByGroupSendInfo($weixinIDs, $categoryIDs, $excludeWeixinIDs){
        $select = $this->fromSlaveDB()->select()->where('1=1');
        if(!empty($weixinIDs)){
            $select->where('WeixinID IN (?)', $weixinIDs);
        }
        if(!empty($excludeWeixinIDs)){
            $select->where('WeixinID not in (?)', $excludeWeixinIDs);
        }
        if(!empty($categoryIDs)){
            $tagIds = array_unique($categoryIDs);
            $conditions = [];
            foreach ($tagIds as $tagId) {
                if ($tagId > 0) {
                    $conditions[] = 'find_in_set(' . $tagId . ', CategoryIDs)';
                }
            }
            if ($conditions) {
                $select->where(implode(' or ', $conditions));
            }
        }
        $data['FriendNum'] = $select->query()->rowCount();
        $data['WeixinNum'] = $select->group('WeixinID')->query()->rowCount();
        return $data;
    }

    /**
     * 根据条件返回select对象
     * @param $weixinIDs
     * @param $categoryIDs
     * @param $excludeWeixinIDs
     * @return Zend_Db_Select
     */
    public function getSelectByQuery($weixinIDs, $categoryIDs, $excludeWeixinIDs){
        $select = $this->fromSlaveDB()->select()->where('1=1');
        if(!empty($weixinIDs)){
            $select->where('WeixinID IN (?)', $weixinIDs);
        }
        if(!empty($excludeWeixinIDs)){
            $select->where('WeixinID not in (?)', $excludeWeixinIDs);
        }
        if(!empty($categoryIDs)){
            $tagIds = array_unique($categoryIDs);
            $conditions = [];
            foreach ($tagIds as $tagId) {
                if ($tagId > 0) {
                    $conditions[] = 'find_in_set(' . $tagId . ', CategoryIDs)';
                }
            }
            if ($conditions) {
                $select->where(implode(' or ', $conditions));
            }
        }
        return $select;
    }

    /**
     * 获取账号对应的昵称 返回 二维数组
     * @param $Accounts
     * @return array [{Account:,Nickname:}]
     */
    public function getNickName($Accounts)
    {
        if(count($Accounts)==0){
            return [];
        }
        $sql = "SELECT `Account` AS `Account`, `NickName` AS `Nickname` FROM `weixin_friends`
            WHERE ( `Account` in ('".implode("','",$Accounts)."') ) UNION
        SELECT `Alias` AS `Account`, `NickName` AS `Nickname` FROM  `weixin_friends`
            WHERE ( `Alias` in ('".implode("','",$Accounts)."') )";
        $res = $this->getAdapter()->query($sql)->fetchAll();
        return $res;
    }


    /**
     * 运营后台获取客户详情信息
     */
    public function getCustomerInfo($friendID)
    {
        $select = $this->fromSlaveDB()->select()->from($this->_name.' as wf',['wf.FriendID','wf.NickName as FriendNickname','wf.Alias','wf.Account','wf.AddDate','wf.CategoryIds','wf.Customer'])->setIntegrityCheck(false);
        $select->joinLeft('weixins as w','w.WeixinID = wf.WeixinID',['w.WeixinID','w.Weixin','w.Nickname']);
        $select->joinLeft('devices as d','d.DeviceID = w.DeviceID',['d.SerialNum']);
        $select->where('wf.FriendID = ?',$friendID);
        return $this->_db->fetchRow($select);

    }

    /**
     * 查询好友的标签列表
     */
    public function getFriendCategoryList($categoryIds)
    {
        $select = $this->fromSlaveDB()->select()->from($this->_name.' as wf',[])->setIntegrityCheck(false);
        $select->joinLeft('categories as c','find_in_set(c.CategoryID,wf.`CategoryIds`)',['COUNT(c.CategoryID) as Num','c.Name','c.CategoryID']);
        $select->where('c.CategoryID in (?)',$categoryIds);
        $select->group('c.CategoryID');
        return $this->_db->fetchAll($select);
    }

    /**
     * 根据标签查询
     */
    public function findByCategoryID($categoryID)
    {
        $select = $this->fetchRow()->select()->from($this->_name,['FriendID','CategoryIds']);
        $select->where('find_in_set(?,CategoryIds)',$categoryID);
        return $this->_db->fetchAll($select);

    }

    /**
     * 该微信号是否已经是个号好友
     */
    public function findWxIsFriend($weixinID,$account)
    {
        $select = $this->fetchRow()->select()->from($this->_name,['FriendID']);
        $select->where('WeixinID = ?',$weixinID);
        $select->where('Account = ? OR Alias =?',$account);
        return $this->_db->fetchAll($select);
    }

    /**
     * @param $weixinID
     * @return array ['WeixinID' => 'Weixin']
     * @throws Zend_Db_Select_Exception
     * 返回当前微信号拥有后台微信个号的好友数据
     */
    public function getAdminFriendWx($weixinID){
        $res = [];
        $friendAccount = []; //所有好友Weixin
        $friends = $this->fromSlaveDB()->select()->from($this->getTableName(), ['Account'])->where('WeixinID = ?', $weixinID)->where('IsDeleted = 0')->query()->fetchAll();
        foreach ($friends as $friend){
            if($friend['Account']){
                $friendAccount[] = $friend['Account'];
            }
        }
        if(!empty($friendAccount)){
            $ownFriends = Model_Weixin::getInstance()->fromSlaveDB()->select()->from(Model_Weixin::getInstance()->getTableName(), ['WeixinID','Weixin'])->where('Weixin IN (?)', $friendAccount)->query()->fetchAll();
            foreach ($ownFriends as $ownFriend){
                $res[$ownFriend['WeixinID']] = $ownFriend['Weixin'];
            }
        }
        return $res;
    }

    public function statFriendNum($WeixinID, $startTime, $stopTime)
    {
        $select = $this->fromSlaveDB()->select();
        $select->from($this->_name, ["count(*) as num"])
            ->where("WeixinID = ?", $WeixinID)
            ->where("AddDate >= ?", $startTime)
            ->where("AddDate <= ?", $stopTime);
        $info = $this->fromSlaveDB()->fetchRow($select)->toArray();
        return $info['num'];
    }

    /**
     * 获取流失的好友数量
     */
    public function getNotFriendNum($account,$weixinID)
    {
        $select = $this->select()->from($this->_name,'COUNT(FriendID) as Num');
        $select->where('WeixinID = ?',$weixinID);
        $select->where('Account not in (?)',$account);
        $select->where('IsDeleted = 0');
        return $this->_db->fetchRow($select);
    }
    /**
     * @param array $weixinIDs
     * @param $startTime
     * @param $endTime
     * @param $unit 单位粒度1:30分钟,2:1小时,3:1天
     * @param int $type 1 新增数 2 删除数
     * @return array
     * 统计好友相关报表数据
     */
    public function getStatData($weixinIDs = [], $startTime, $endTime, $unit, $type = 1){
        if(empty($weixinIDs)){
            return [];
        }
        switch ($unit){
            case '1': //30分钟
                if($type == 2){
                    $sql="SELECT TimeString, COUNT(FriendID) AS Num FROM (
	SELECT FriendID,
		DATE_FORMAT(
			concat( date( DeletedTime ), ' ', HOUR ( DeletedTime ), ':', floor( MINUTE ( DeletedTime ) / 30 ) * 30 ),
			'%Y-%m-%d %H:%i' 
		) AS TimeString 
	FROM weixin_friends
	WHERE WeixinID IN ('".implode("','", $weixinIDs)."')  AND DeletedTime >= '{$startTime}' AND DeletedTime <= '{$endTime}'
	) a 
GROUP BY TimeString
ORDER BY TimeString Asc";
                }else{
                    $sql="SELECT TimeString, COUNT(FriendID) AS Num FROM (
	SELECT FriendID,
		DATE_FORMAT(
			concat( date( AddDate ), ' ', HOUR ( AddDate ), ':', floor( MINUTE ( AddDate ) / 30 ) * 30 ),
			'%Y-%m-%d %H:%i' 
		) AS TimeString 
	FROM weixin_friends
	WHERE WeixinID IN ('".implode("','", $weixinIDs)."') AND IsDeleted = 0  AND AddDate >= '{$startTime}' AND AddDate <= '{$endTime}'
	) a 
GROUP BY TimeString
ORDER BY TimeString Asc";
                }

                $res = $this->fromSlaveDB()->getAdapter()->fetchAll($sql);
                break;
            case '3': //1天
                $select  = $this->fromSlaveDB()->select();
                if($type == 2){
                    $select->from($this->_name, ["DATE_FORMAT(DeletedTime,'%Y-%m-%d') as TimeString","COUNT(FriendID) as Num"])->where("WeixinID in (?)", $weixinIDs);
                    $select->where('DeletedTime >= ?', $startTime)->where('DeletedTime <= ?', $endTime)->group("DATE_FORMAT(DeletedTime,'%Y-%m-%d')");
                }else{
                    $select->from($this->_name, ["DATE_FORMAT(AddDate,'%Y-%m-%d') as TimeString","COUNT(FriendID) as Num"])->where("WeixinID in (?)", $weixinIDs)->where('IsDeleted = 0');
                    $select->where('AddDate >= ?', $startTime)->where('AddDate <= ?', $endTime)->group("DATE_FORMAT(AddDate,'%Y-%m-%d')");
                }
                $res = $select->order('TimeString Asc')->query()->fetchAll();
                break;
            case '2': //1小时
            default:
                $select = $this->fromSlaveDB()->select();
                if($type == 2){
                    $select->from($this->_name, ["DATE_FORMAT(DeletedTime,'%Y-%m-%d %H:00') as TimeString","COUNT(FriendID) as Num"])->where("WeixinID in (?)", $weixinIDs);
                    $select->where('DeletedTime >= ?', $startTime)->where('DeletedTime <= ?', $endTime)->group("DATE_FORMAT(DeletedTime,'%Y-%m-%d %H:00')");
                }else{
                    $select->from($this->_name, ["DATE_FORMAT(AddDate,'%Y-%m-%d %H:00') as TimeString","COUNT(FriendID) as Num"])->where("WeixinID in (?)", $weixinIDs)->where('IsDeleted = 0');
                    $select->where('AddDate >= ?', $startTime)->where('AddDate <= ?', $endTime)->group("DATE_FORMAT(AddDate,'%Y-%m-%d %H:00')");
                }
                $res = $select->order('TimeString Asc')->query()->fetchAll();
                break;
        }

        $data = array();

        foreach ($res as $d){
            $data[$d['TimeString']] = $d['Num'];
        }

        return $data;
    }

    /**
     * @param array $weixinIDs
     * @param $startTime
     * @param $endTime
     * @param $unit
     * @param $type 1好友总数,2新增好友数
     * @return array ['2018-01-11']
     * 统计好友总数报表
     */
    public function getAllFriendNumStat($weixinIDs = [], $startTime, $endTime, $unit, $type = 1){
        if(empty($weixinIDs)){
            return [];
        }
        if($type == 2){
            $sumField = 'NewFriendNum';
        }else{
            $sumField = 'FriendNum';
        }
        $select = Model_StatHours::getInstance()->fromSlaveDB()->select();
        switch ($unit){
            case '1': //30分钟
                $select->from(Model_StatHours::getInstance()->getTableName(), ["DATE_FORMAT(DateTime,'%Y-%m-%d %H:00') as TimeString","Sum({$sumField}) as Num"])->where("WeixinID in (?)", $weixinIDs)
                    ->where('DateTime >= ?', $startTime)->where('DateTime <= ?', $endTime)->group("DATE_FORMAT(DateTime,'%Y-%m-%d %H:00')");
                break;
            case '3': //1天
                $select->from(Model_StatHours::getInstance()->getTableName(), ["DATE_FORMAT(DateTime,'%Y-%m-%d') as TimeString","Sum({$sumField}) as Num"])->where("WeixinID in (?)", $weixinIDs)
                    ->where('DateTime >= ?', $startTime)->where('DateTime <= ?', $endTime)->group("Date");
                break;
            case '2': //1小时
            default:
            $select->from(Model_StatHours::getInstance()->getTableName(), ["DATE_FORMAT(DateTime,'%Y-%m-%d %H:00') as TimeString","Sum({$sumField}) as Num"])->where("WeixinID in (?)", $weixinIDs)
                ->where('DateTime >= ?', $startTime)->where('DateTime <= ?', $endTime)->group("DATE_FORMAT(DateTime,'%Y-%m-%d %H:00')");
                break;
        }
        $res = $select->order('TimeString Asc')->query()->fetchAll();
        $data = array();

        foreach ($res as $d){
            $data[$d['TimeString']] = $d['Num'];
        }

        return $data;
    }

    /**
     * 获取多个微信号的好友ID
     */
    public function getWeixnFriendIDs($weixinIDs)
    {
        $select = $this->select()->from($this->_name,'FriendID');
        $select->where('WeixinID in (?)',$weixinIDs);
        $data = $this->_db->fetchAll($select);

        $res = [];
        foreach ($data as $v){
            $res[] = $v['FriendID'];
        }
        return $res;

    }

    /**
     * 统计列表使用
     * 新增好友
     */
    public function getAddFriendData($Weixns,$StartDate,$EndDate)
    {

        $select = $this->fromSlaveDB()->select()->setIntegrityCheck(false);
        $select->from($this->_name.' as wf',["DATE_FORMAT(wf.AddDate,'%Y-%m-%d') as AddDate","wf.Account as wfAccount",'wf.Alias as wfAlias','wf.FriendID']);
        $select->joinLeft('weixins as w','wf.WeixinID = w.WeixinID',['w.Weixin as wAccount','w.Alias as wAlias']);
        $select->where("wf.AddDate >=", $StartDate.' 00:00:00');
        $select->where("wf.AddDate <=", $EndDate.' 23:59:59');
        if ($Weixns){
            $select->where("wf.Account in (?) or wf.Alias in (?)", $Weixns);
        }
        $data = $this->_db->fetchAll($select);
        $res = array();

        foreach ($data as $d){

            if (!empty($d['wAccount']) && !empty($d['wfAccount'])){
                $wAccount = empty($d['wAlias'])?$d['wAccount']:$d['wAlias'];
                $wfAccount = empty($d['wfAlias'])?$d['wfAccount']:$d['wfAlias'];

                $res[$wAccount.'-'.$wfAccount] = $d['AddDate'];
            }
        }

        return $res;
    }
}