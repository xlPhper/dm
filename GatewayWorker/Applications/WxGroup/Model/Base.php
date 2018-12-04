<?php

use \GatewayWorker\Lib\Db;

abstract class Base
{
    public static function returnArray($flag, $msg = '', $data = [], $extra = [])
    {
        $flag = $flag === true ? 1 : $flag;

        $d = ['f' => $flag, 'm' => $msg, 'd' => $data, 'e' => $extra];

        return $d;
    }

    public static function returnJson($flag, $msg = '', $data = [], $extra = [])
    {
        $flag = $flag === true ? 1 : $flag;

        $d = ['f' => $flag, 'm' => $msg, 'd' => $data, 'e' => $extra];

        return json_encode($d);
    }

    public static function getDb()
    {
        return Db::instance(Events::getDb());
    }

    public static function getSlaveDb()
    {
        return Db::instance(Events::getSlaveDb());
    }
}