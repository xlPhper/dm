<?php
/**
 * Created by PhpStorm.
 * User: Ekko
 * Date: 2018/9/29
 * Ekko: 13:12
 */
class Model_StatHours extends DM_Model
{
    public static $table_name = "stat_hours";
    protected $_name = "stat_hours";
    protected $_primary = "HourID";

    public function getTabName()
    {
        return $this->_name;
    }

    /**
     * 根据时间查询
     * @param $date
     * @param $hour
     */
    public function findFriendNum($weixinId,$date,$hour)
    {
        $select = $this->select()
            ->where('WeixinID = ?',$weixinId)
            ->where('Date = ?',$date)
            ->where('Hour = ?',$hour);
        return $this->_db->fetchRow($select);
    }

    /**
     * 查询最后一条记录
     * @param $weixinId 指定微信号
     * @param $hourId 排除Id
     * @return mixed
     */
    public function findLast($weixinId,$hourId = false)
    {
        $select = $this->select();
        $select->where('WeixinID = ?',$weixinId);
        if ($hourId){
            $select->where('HourID <> ?',$hourId);
        }
        $select->order('Date Desc');
        $select->order('Hour Desc');
        return $this->_db->fetchRow($select);
    }

    /**
     * 获取所有数据
     */
    public function findAll()
    {
        $select = $this->select();
        return $this->_db->fetchAll($select);
    }

    /**
     * 更新每小时数据
     */
    public function updateHourDate($friendNum,$newFriednNum,$lossFriendNum,$weixinID)
    {
        $date = date('Y-m-d');
        $time = time();
        $hour = date('G');

        $select = $this->select();
        $select->from($this->_name)
            ->where("WeixinID = ?", $weixinID)
            ->where("Date = ?", $date)
            ->where("Hour = ?", $hour);
        $row = $this->_db->fetchRow($select);

        $data['WeixinID'] = $weixinID;
        $data['Date'] = $date;
        $data['Time'] = $time;
        $data['Hour'] = $hour;
        $data['FriendNum'] = $friendNum;

        if (isset($row['HourID'])){
            $data['NewFriendNum'] = $row['NewFriendNum'] + $newFriednNum;
            $data['LossFriendNum'] = $row['LossFriendNum'] + $lossFriendNum;

            $where = "HourID = '{$row['HourID']}'";
            $this->_db->update($this->_name, $data, $where);
        }else{
            $data['NewFriendNum'] = $newFriednNum;
            $data['LossFriendNum'] = $lossFriendNum;

            $this->_db->insert($this->_name, $data);
        }


    }
}