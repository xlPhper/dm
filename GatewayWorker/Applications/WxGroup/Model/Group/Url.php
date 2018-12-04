<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/8
 * Time: 19:50
 */
use \GatewayWorker\Lib\Db;

class Group_Url
{
    public static $table = 'group_urls';

    public static function updateQRCode($Message)
    {
        $db = Db::instance(Events::getDb());
        $updateData = [
            'QRCode' => $Message['QRCode']
        ];
        $db->update(self::$table)->cols($updateData)->where("UrlID = :UrlID")
            ->bindValue(["UrlID" => $Message['UrlID']])->query();

        return true;
    }
}