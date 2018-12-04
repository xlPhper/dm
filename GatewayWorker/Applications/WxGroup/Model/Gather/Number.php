<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/5
 * Time: 18:51
 */
use \GatewayWorker\Lib\Db;

class Gather_Number
{
    public static $table = "gather_numbers";

    public static function check($data)
    {
        $db = Db::instance(Events::getDb());
        $db->update(self::$table)->cols([
            'IsWeixin' => $data['IsWeixin']
        ]);
        $db->where("NumberID = '{$data['ID']}'");
        $db->query();
    }
}