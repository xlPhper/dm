<?php
/**
 * Created by PhpStorm.
 * User: Ekko
 * Date: 2018/9/18
 * Ekko: 14:20
 */
class Model_Position extends DM_Model
{
    public static $table_name = "position";
    protected $_name = "position";
    protected $_primary = "PositionID";

    public function getTableName()
    {
        return $this->_name;
    }

    /**
     * 查询主键信息
     * @param $ID
     * @return mixed
     */
    public function findByID($ID)
    {

        $select = $this->select()->where('PositionID = ?',$ID);
        return $this->_db->fetchRow($select);

    }

    /**
     * 寻找可执行的随机定位
     * @return array
     */
    public function getRunTask($weixinIds = null)
    {
        $select = $this->select()->from($this->_name.' as p')->setIntegrityCheck(false);
        $select->joinLeft('stop_date as s','s.StopDateID = p.StopDate',['s.Start','s.End']);
//             下次执行时间大于上次执行时间 并且 当前时间大于下次执行时间
        $select->where("Longitude > 0 AND Latitude > 0 ");
        $select->where("CURRENT_TIMESTAMP() >= p.NextRunTime");
        $select->where("p.WeixinID <> 0");
        $select->where("s.Start < DATE_FORMAT(NOW(),'%H:%i') AND s.End > DATE_FORMAT(NOW(),'%H:%i')");
        if ($weixinIds){
            $select->where("p.WeixinID in(?)",$weixinIds);
        }
        $select->order("p.NextRunTime Asc");
        return $this->_db->fetchAll($select);

    }

    /**
     * 寻找空余的位置
     * @param int $tags 标签
     * @param null $num 数量
     * @param $weixinId 排除的微信号
     * @return array
     */
    public function getPosition($tags = 0,$num = null,$weixinId = null)
    {
        $select = $this->select()->from($this->_name,['PositionID','Longitude','Latitude','AddressID']);
        $select->where("Longitude > 0 AND Latitude > 0 ");
        $select->where("CURRENT_TIMESTAMP() >= NextRunTime OR WeixinID = 0");
        // 多标签
        if (!empty($tags)) {
            $where_msg ='';
            $tag_data = explode(',',$tags);
            foreach($tag_data as $w){
                $where_msg .= "FIND_IN_SET(".$w.",Tags) OR ";
            }
            $where_msg = rtrim($where_msg,'OR ');
            $select->where($where_msg);
        }
        // 排除微信号
        if ($weixinId){
            $select->where('WeixinID <> ?',$weixinId);
        }
        // 获取数量
        if ($num){
            $select->limit($num);
        }
        return $this->_db->fetchAll($select);

    }

    /**
     * 获取下一次任务执行时间
     */
    public function getNextRunTime($Hour,$Start,$End)
    {
        $next_run_time_hour = date('H:i',strtotime('+'.$Hour.' hour'));

        if ($next_run_time_hour > $Start || date('H:i') >$Start){

            // 当前时间距离开始暂停时间还有多久
            $poor = floor((strtotime(date('Y-m-d ').$Start.':00')-time())/3600);

            // 算出暂停之后还需要停留多久
            $end_time = explode(':',$End);
            $Hour = $end_time[0]+($Hour - $poor);

            // 判断今天的暂停截止时间是否已经过去
            if (date('H:i')>$End){
                $day = date('Y-m-d',strtotime('+1 day'));
            }else{
                $day = date('Y-m-d');

            }
            $next_run_time = $day.' '.$Hour.':00:00';

        }else{
            $next_run_time = date('Y-m-d H:i:s',strtotime('+'.$Hour.' hour'));
        }

        return $next_run_time;
    }


    /**
     * 获取匹配的微信号
     */
    public function getWeixinIds($weixinIds)
    {
        $select = $this->select()->where('WeixinID in (?)',$weixinIds);
        $data = $this->_db->fetchAll($select);

        $res = array();

        foreach ($data as $v){
            $res[] = $v['WeixinID'];
        }

        return $res;

    }

    /**
     * 创建随机定位任务
     * @param $weixinId 微信ID
     * @param $longitude 经度
     * @param $latitude 纬度
     */
    public function createTask($weixinId,$longitude,$latitude)
    {
        // 直接生产一个任务
        $childTaskConfigs = [
            'Longitude' => $longitude,
            'Latitude' => $latitude
        ];
        (new Model_Task())->insert([
            'WeixinID' => $weixinId,
            'TaskCode' => TASK_CODE_RANDOM_POSITION,
            'TaskConfig' => json_encode($childTaskConfigs),
            'MaxRunNums' => 1,
            'AlreadyNums' => 0,
            'TaskRunTime' => '',
            'NextRunTime' => date('Y-m-d H:i:s'),
            'LastRunTime' => '0000-00-00 00:00:00',
            'Status' => TASK_STATUS_NOTSTART,
            'ParentTaskID' => 0,
            'IsSendClient' => 'Y'
        ]);
    }

    /**
     * 根据标签查找
     *
     * @param $tags
     */
    public function findByTagID($tags)
    {
        $select = $this->select()->from($this->_name);
        $select->where('Tags = ?',$tags);
        return $this->_db->fetchAll($select);
    }
}