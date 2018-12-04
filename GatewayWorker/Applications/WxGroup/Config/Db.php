<?php
namespace Config;
/**
 * mysql配置
 * @author walkor
 */
class Db
{
    /**
     * 数据库的一个实例配置，则使用时像下面这样使用
     * $user_array = Db::instance('db1')->select('name,age')->from('users')->where('age>12')->query();
     * 等价于
     * $user_array = Db::instance('db1')->query('SELECT `name`,`age` FROM `users` WHERE `age`>12');
     * @var array
     */
    public static $db = array(
        'host'    => 'pi-bp16fjubb1uv0tymp.mysql.polardb.rds.aliyuncs.com',
//        'host' => 'qk.rwlb.rds.aliyuncs.com',
        'port'    => 3306,
        'user'    => 'wx_group',
        'password' => 'Duomai123',
        'dbname'  => 'wx_group',
        'charset'    => 'utf8',
    );

    public static $testDb = array(
        'host'    => '115.238.100.75',
        'port'    => 3306,
        'user'    => 'wx_group',
        'password' => 'Duomai@123',
        'dbname'  => 'wx_group',
        'charset'    => 'utf8',
    );

    public static $slaveDb = [
        'host'    => 'pi-bp12ephi0rrte7t6e.mysql.polardb.rds.aliyuncs.com',
//        'host' => 'qk.rwlb.rds.aliyuncs.com',
        'port'    => 3306,
        'user'    => 'wx_group',
        'password' => 'Duomai123',
        'dbname'  => 'wx_group',
        'charset'    => 'utf8',
    ];

    public static $testSlaveDb = array(
        'host'    => '115.238.100.75',
        'port'    => 3306,
        'user'    => 'wx_group',
        'password' => 'Duomai@123',
        'dbname'  => 'wx_group',
        'charset'    => 'utf8',
    );
}