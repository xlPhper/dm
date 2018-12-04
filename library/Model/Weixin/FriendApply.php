<?php
/**
 * Created by PhpStorm.
 * User: jakins
 * Date: 2018/11/6
 * Time: 14:06
 * 微信好友申请列表
 */
class Model_Weixin_FriendApply extends DM_Model
{
    public static $table_name = "weixin_friend_apply";
    protected $_name = "weixin_friend_apply";
    protected $_primary = "FriendApplyID";

    const STATE_UNADD = 0; //未添加
    const STATE_ADD = 1; //已添加

    const APPLY_DEAL_TYPE_AGREE = 'AGREE'; //申请同意
    const APPLY_DEAL_TYPE_DELETE = 'DELETE'; //申请删除

    const IS_DELETED = 1; //删除
    const IS_NOT_DELETED = 0; //未删除
    const IS_AGREE_DELETED = 2; //web端同意好友申请,申请列表不展示

    const IS_NEW = 1; //新申请
    const IS_NOT_NEW = 0; //老申请

    /**
     * @param $weixinID
     * @param $friend
     * @param bool $fromSlaveDB
     * @return null|Zend_Db_Table_Row_Abstract
     * 获取某个个号好友信息
     */
    public function getByWeixinAndFriend($weixinID, $friend, $fromSlaveDB = true){
        if($fromSlaveDB){
            return $this->fromSlaveDB()->fetchRow(['WeixinID = ?' => $weixinID, 'Talker = ?' => $friend]);
        }else{
            return $this->fetchRow(['WeixinID = ?' => $weixinID, 'Talker = ?' => $friend]);
        }
    }

    /**
     * @param array $weixinIDs
     * @param array $dates
     * @return array
     * 返回某些日期当天的申请数 Key=>ApplyDate, Value => Num
     */
    public function getApplyNumByDate($weixinIDs = [], $dates = []){
        if(empty($weixinIDs) || empty($dates)){
            return [];
        }
        $select = $this->fromSlaveDB()->select();
        $select->from($this->_name, ["DATE_FORMAT(ApplyTime,'%Y-%m-%d') as ApplyDate","COUNT(FriendApplyID) as Num"]);
        $select->where("WeixinID in (?)", $weixinIDs);
        $select->where("DATE_FORMAT(ApplyTime,'%Y-%m-%d') in (?)", $dates);
        $select->group("DATE_FORMAT(ApplyTime,'%Y-%m-%d')");
        $select->order('ApplyDate Desc');
        $data = $select->query()->fetchAll();
        $res = array();

        foreach ($data as $d){
            $res[$d['ApplyDate']] = $d['Num'];
        }

        return $res;
    }

    /**
     * @param array $weixinIDs
     * @param array $dates
     * @return array
     * 返回某些日期当天的通过数 Key=>AgreeDate, Value => Num
     */
    public function getAgreeNumByDate($weixinIDs = [], $dates = []){
        if(empty($weixinIDs) || empty($dates)){
            return [];
        }
        $select = $this->fromSlaveDB()->select();
        $select->from($this->_name, ["DATE_FORMAT(UpdateTime,'%Y-%m-%d') as AgreeDate","COUNT(FriendApplyID) as Num"]);
        $select->where("WeixinID in (?)", $weixinIDs);
        $select->where('State = ?', Model_Weixin_FriendApply::STATE_ADD);
        $select->where("DATE_FORMAT(UpdateTime,'%Y-%m-%d') in (?)", $dates);
        $select->group("DATE_FORMAT(UpdateTime,'%Y-%m-%d')");
        $select->order('AgreeDate Desc');
        $data = $select->query()->fetchAll();
        $res = array();

        foreach ($data as $d){
            $res[$d['AgreeDate']] = $d['Num'];
        }

        return $res;
    }

    /**
     * @param array $weixinIDs
     * @param $startTime
     * @param $endTime
     * @param $unit 单位粒度1:30分钟,2:1小时,3:1天
     * @param int $type 1 申请数 2 通过数
     * @return array
     * 统计申请相关报表数据
     */
    public function getStatData($weixinIDs = [], $startTime, $endTime, $unit, $type = 1){
        if(empty($weixinIDs)){
            return [];
        }
        switch ($unit){
            case '1': //30分钟
                if($type == 2){
                    $sql="SELECT TimeString, COUNT(FriendApplyID) AS Num FROM (
	SELECT FriendApplyID,
		DATE_FORMAT(
			concat( date( UpdateTime ), ' ', HOUR ( UpdateTime ), ':', floor( MINUTE ( UpdateTime ) / 30 ) * 30 ),
			'%Y-%m-%d %H:%i' 
		) AS TimeString 
	FROM weixin_friend_apply
	WHERE WeixinID IN ('".implode("','", $weixinIDs)."') AND State = 1  AND UpdateTime >= '{$startTime}' AND UpdateTime <= '{$endTime}'
	) a 
GROUP BY TimeString
ORDER BY TimeString Asc";
                }else{
                    $sql="SELECT TimeString, COUNT(FriendApplyID) AS Num FROM (
	SELECT FriendApplyID,
		DATE_FORMAT(
			concat( date( ApplyTime ), ' ', HOUR ( ApplyTime ), ':', floor( MINUTE ( ApplyTime ) / 30 ) * 30 ),
			'%Y-%m-%d %H:%i' 
		) AS TimeString 
	FROM weixin_friend_apply
	WHERE WeixinID IN ('".implode("','", $weixinIDs)."')  AND ApplyTime >= '{$startTime}' AND ApplyTime <= '{$endTime}'
	) a 
GROUP BY TimeString
ORDER BY TimeString Asc";
                }

                $res = $this->fromSlaveDB()->getAdapter()->fetchAll($sql);
                break;
            case '3': //1天
                $select  = $this->fromSlaveDB()->select();
                if($type == 2){
                    $select->from($this->_name, ["DATE_FORMAT(UpdateTime,'%Y-%m-%d') as TimeString","COUNT(FriendApplyID) as Num"])->where("WeixinID in (?)", $weixinIDs)->where('State = 1');
                    $select->where('UpdateTime >= ?', $startTime)->where('UpdateTime <= ?', $endTime)->group("DATE_FORMAT(UpdateTime,'%Y-%m-%d')");
                }else{
                    $select->from($this->_name, ["DATE_FORMAT(ApplyTime,'%Y-%m-%d') as TimeString","COUNT(FriendApplyID) as Num"])->where("WeixinID in (?)", $weixinIDs);
                    $select->where('ApplyTime >= ?', $startTime)->where('ApplyTime <= ?', $endTime)->group("DATE_FORMAT(ApplyTime,'%Y-%m-%d')");
                }
                $res = $select->order('TimeString Asc')->query()->fetchAll();
                break;
            case '2': //1小时
            default:
            $select = $this->fromSlaveDB()->select();
                if($type == 2){
                    $select->from($this->_name, ["DATE_FORMAT(UpdateTime,'%Y-%m-%d %H:00') as TimeString","COUNT(FriendApplyID) as Num"])->where("WeixinID in (?)", $weixinIDs)->where('State = 1');
                    $select->where('UpdateTime >= ?', $startTime)->where('UpdateTime <= ?', $endTime)->group("DATE_FORMAT(UpdateTime,'%Y-%m-%d %H:00')");
                }else{
                    $select->from($this->_name, ["DATE_FORMAT(ApplyTime,'%Y-%m-%d %H:00') as TimeString","COUNT(FriendApplyID) as Num"])->where("WeixinID in (?)", $weixinIDs);
                    $select->where('ApplyTime >= ?', $startTime)->where('ApplyTime <= ?', $endTime)->group("DATE_FORMAT(ApplyTime,'%Y-%m-%d %H:00')");
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
     * @param $weixinIDs
     * @param $startTime
     * @param $endTime
     * @return array
     * 获取一定时间内个号的好友申请数
     */
    public function getNumGroupByWeixin($weixinIDs, $startTime, $endTime){
        if(empty($weixinIDs)){
            return [];
        }
        $res = $this->fromSlaveDB()->select()->from($this->_name, ["WeixinID","COUNT(FriendApplyID) as Num"])->where("WeixinID in (?)", $weixinIDs)
            ->where('ApplyTime >= ?', $startTime)->where('ApplyTime <= ?', $endTime)->group("WeixinID")->query()->fetchAll();

        $data = array();

        foreach ($res as $d){
            $data[$d['WeixinID']] = $d['Num'];
        }

        return $data;
    }
}
