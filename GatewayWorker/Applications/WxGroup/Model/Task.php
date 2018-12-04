<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/25
 * Time: 10:50
 */

use \GatewayWorker\Lib\Db;

class Task
{
    public static $table = 'tasks';

    public static function getInfo($TaskID)
    {
        $db = Db::instance(Events::getDb());
        return $db->select(self::$table)->cols('*')->where("TaskID = :TaskID")->bindValues(['TaskID' => $TaskID])->row();

    }

    public static function updateStatus($TaskID, $flag)
    {
        $db = Db::instance(Events::getDb());
        if($flag) {
            $updateData = [
                'Status' => 'SUCCESS',
            ];
        }else{
            $updateData = [
                'Status' => 'FAILURE',
            ];
        }
        $updateData['UpdateDate'] = date("Y-m-d H:i:s");
        $db->update(self::$table)->cols($updateData)->where("TaskID = :TaskID")
            ->bindValues(["TaskID" => $TaskID])->query();
    }

    public static function add($Weixin, $TaskType, $RequestData, $Level = 0)
    {
        $db = Db::instance(Events::getDb());
        $data = [
            'WeixinID' => $Weixin,
            'TaskType' => $TaskType,
            'RequestData' => json_encode($RequestData),
            'Level' => $Level,
            'AddDate' => date("Y-m-d H:i:s"),
            'Status' => 'NOTSTART',
            'Note' => '',
        ];
        $db->insert(self::$table)->cols($data)->query();
    }
}