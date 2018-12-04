<?php
/**
 * Created by PhpStorm.
 * User: jakins
 * Date: 2018/10/8
 * Time: 14:40
 * redis服务
 */
class Helper_Redis{
    /**
     * Instance of Redis
     */
    public static $_redis = null;
    protected static $_connected = false;

    protected static $_host = 'localhost';
    protected static $_port = 6379;
    protected static $_auth = null;
    protected static $_timeout = 3;
    protected static $_conn = 'connect';
    protected static $_db = '';

    /**
     * Sets the redis server
     */
    public static function init($options = array())
    {
        if (!empty($options['host'])) {
            self::$_host = $options['host'];
        }

        if (!empty($options['port'])) {
            self::$_port = $options['port'];
        }

        if (!empty($options['auth'])) {
            self::$_auth = $options['auth'];
        }

        if (isset($options['timeout'])) {
            self::$_timeout = floatval($options['timeout']);
        }

        if (isset($options['persistent']) && $options['persistent']) {
            self::$_conn = 'pconnect';
        }

        if (isset($options['db']) && $options['db'] != '') {
            self::$_db = $options['db'];
        }
    }

    /**
     * Return an instance of the Redis.
     *
     * @return Redis
     */
    public static function getInstance($reconnect = false)
    {
        if (is_null(self::$_redis)) {
            self::$_redis = new Redis();
            self::$_connected = false;
        }

        if ($reconnect || !self::$_connected) {
            $conn = self::$_conn;
            // 如果timeout为0，那么取值为PHP设置中的default_socket_timeout值
            $ret = self::$_redis->$conn(self::$_host, self::$_port, self::$_timeout);
            if (!$ret) {
                throw new \Exception('Unable to connect the Redis server - '. self::$_host .':'. self::$_port);
            }

            if (!empty(self::$_auth)) {
                $ret = self::$_redis->auth(self::$_auth);
                if (!$ret) {
                    throw new \Exception('Invalid password for the Redis server - '. self::$_host .':'. self::$_port);
                }
            }
            if(self::$_db != ""){
                $ret = self::$_redis->select(self::$_db);
                if (!$ret) {
                    throw new \Exception('Invalid db for the Redis dbindex - '. self::$_db);
                }
            }

            self::$_connected = true;
        }

        return self::$_redis;
    }

    // ==================== redis key =====================
    /**
     * 聊天最后一个消息列表 hash表
     */
    public static function lastMsgIdKey()
    {
        return 'CHAT:MSG:LASTIDS';
    }

    /**
     * 微信-地区选项列表
     */
    public static function wxAreaKey()
    {
        return 'WEIXIN:AREA:LIST';
    }

    /**
     * @return string
     * 微信个号ID和发过的朋友圈素材ID关系
     */
    public static function weixinIDMateIDRelationKey()
    {
        return 'WEIXINID_MATEID_RELATION';
    }

    /**
     * 搜索索引队列停止
     */
    public static function searchIndexQueueStop()
    {
        return 'SEARCH:INDEX:QUEUE:STOP';
    }

    /**
     * 已索引到的id
     */
    public static function searchIndexId($indexName)
    {
        return 'SEARCH:INDEXID:'.$indexName;
    }

    /**
     * 已经完成的索引
     */
    public static function searchIndexFinish($indexName)
    {
        return 'SEARCH:INDEX:FINISH:'.$indexName;
    }
}


