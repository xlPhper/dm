<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/9
 * Time: 17:22
 */
use \GatewayWorker\Lib\Db;
class Group_Weixin
{
    public static $table = 'group_weixins';

    public static function join($GroupID, $WeixinID)
    {
        $db = Db::instance(Events::getDb());
        //更新群组与微信号的关系表
        $info = $db->select('*')->from(self::$table)->where("WeixinID = :WeixinID")->where("GroupID = :GroupID")
            ->bindValues(['WeixinID' => $WeixinID,'GroupID' => $GroupID])->row();
        if(!isset($info['ID'])){
            $data = [
                'GroupID'   =>  $GroupID,
                'WeixinID'  =>  $WeixinID,
                'Status'    =>  'IN',
                'UpdateDate'  =>  date("Y-m-d H:i:s")
            ];
            $db->insert(self::$table)->cols($data)->query();
            //groups增加weixin数量
            $sql = "update groups set WeixinNum = WeixinNum + 1 where GroupID = '{$GroupID}'";
            $db->query($sql);
        }
    }

    public static function quit($GroupID, $WeixinID)
    {
        $db = Db::instance(Events::getDb());
        //更新群组与微信号的关系表
        $info = $db->select('*')->from(self::$table)->where("WeixinID = :WeixinID")->where("GroupID = :GroupID")
            ->bindValues(['WeixinID' => $WeixinID,'GroupID' => $GroupID])->row();
        if(isset($info['ID'])){
            $data = [
                'Status'    =>  'OUT',
                'UpdateDate'  =>  date("Y-m-d H:i:s")
            ];
            $db->insert(self::$table)->cols($data)->query();
            $db->update(self::$table)->cols($data)->where("ID = :ID")
                ->bindValue(["ID" => $info['ID']])->query();
        }
    }

}