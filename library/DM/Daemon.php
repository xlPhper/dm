<?php
/**
 * Daemon服务
 * 
 * 专门用于不间歇执行脚本，不是那种定期执行的任务。用于解决crontab时间粒度最小一分钟的不足。
 * 
 * @author Bruce
 * @since 2014/10/24
 */
require_once APPLICATION_PATH . '/../GatewayClient/Gateway.php';
use GatewayClient\Gateway;
//Gateway::$registerAddress = '127.0.0.1:1238';
Gateway::$registerAddress = '127.0.0.1:1339';

abstract class DM_Daemon
{
    //任务执行间歇性休息时间
    //子类可以通过继承覆盖
    //20150103 Daemon默认间隔改为1s，发现200ms，很容易把mysql 负载刷高。而且crontab的粒度的一分钟，远超那个了。
    // 使用 php usleep 默认单位微秒
    const CRON_SLEEP=1000000;
    //子类可以通过覆盖，快速指定服务
    const SERVICE=NULL;
    //日志多少秒强制刷新一次
    const LOG_FORCEFLUSH_INTERVAL=10;
    
    /**
     * 是否需要定期检测数据库是否连接
     * @var bool
     */
    const NEED_DB_CHECKER=true;
    
    /**
     * 运行的服务
     * 
     * @var string
     */
    private $service=NULL;
    
    protected $sleepCron=NULL;
    
    /**
     * 单例实例
     *
     * @var DM_Daemon
     */
    private static $instance=NULL;
    
    /**
     * 单进程防并发守护
     * 
     * @var DM_Daemon_SingleProcessGuard
     */
    private $singleGuard=NULL;
    
    /**
     * 新版本检测
     * 
     * 使用方法：在application\data\release创建相应的{service}.txt文件即可。
     * {service}为服务名称。检测到内容变动，机会自动触发新版本事件onNewReleaseFind，可退出或其他操作。
     *
     * @var DM_Daemon_ReleaseCheck
     */
    private $releaseCheck=NULL;
    
    /**
     * 内存限额检测机制
     *
     * @var DM_Daemon_MemoryCheck
     */
    private $memoryCheck=NULL;
    
    /**
     * 上次刷新日志
     * 
     * @var float
     */
    private $lastFlushLog=0;
    
    protected function __construct()
    {

    }
    
    /**
     * Daemon服务初始化
     * 
     * 子类可以调用，但是必须调用parent::init();
     */
    protected function init()
    {
        if (php_sapi_name()!='cli'){
            self::getLog()->add("No permission run in not cli mode.");
            exit('No perm.');
        }
        
        //单进程并发确认
        $this->getSingleGuard()->check();

        self::getLog()->add("\n\nenv:".Zend_Registry::get('application_env'));
    }
    
    /**
     * 执行Daemon任务
     * 
     * 关于异常处理，子任务处理处尽量不要直接抓取顶级\Exception异常，最好建立任务级别的异常以免乱套。特别是设计mysql/redis连接的时候。
     */
    public final function daemonRun()
    {
        try {
            /**
             * 死循环部分
             */
            while (TRUE){
                //脚本进程日常监测
                //新版本检测
                $this->releaseCheck();
                
                //内存检测等
                $this->memoryCheck();
                
                if (static::NEED_DB_CHECKER){
                    $this->dbConnectionCheck();
                }
                //调用方法
                $this->run();
                //日志强刷
                if (microtime(1)-$this->lastFlushLog>static::LOG_FORCEFLUSH_INTERVAL){
                    //第一次不推
                    if ($this->lastFlushLog!=0){
                        DM_Log::daemonFlush();
                    }
                    $this->lastFlushLog=microtime(1);
                }

                usleep(static::CRON_SLEEP);
            }
            
        }catch (Exception $e){
            self::getLog()->add('Find DM_Daemon::daemonRun Exception:'.$e->__toString());
            //退出前刷新日志
            DM_Log::forceFlush();
            //print_r($e);
            die();
        }
    }
    
    /**
     * 执行Daemon任务要调用的方法
     */
    protected abstract function run();
    
    /**
     * 发现新版本的事件
     */
    protected function onNewReleaseFind()
    {
        
    }
    
    /**
     * 系统运行过程检测到内存不够的事件
     */
    protected function onOutOfMemory()
    {
        
    }
    
    /**
     * 系统运行过程检测到内存不够的事件
     */
    protected function onShutDown()
    {
        
    }
    
    /**
     * 数据库连接确认
     */
    protected function dbConnectionCheck()
    {
        $db = $this->getDb();
        try {
            $db->query('select 1+1;');
        } catch (Exception $e) {
            if($e->getMessage() == 'SQLSTATE[HY000]: General error: 2006 MySQL server has gone away') {
                $db->closeConnection();
                $db->getConnection();
            }else{
                throw new Exception($e->getMessage());
            }
        }
    }
    
    /**
     * 内存检查 激发内存不够事件
     * 
     * @return boolean
     */
    private function memoryCheck()
    {
        if ($this->getMemoryCheck()->check()){//内存够
            return true;
        }else{
            $this->onOutOfMemory();//内存不够事件
            return false;
        }
    }
    
    private function releaseCheck()
    {
        if ($this->getReleaseCheck()->check()){//无新版本
            return true;
        }else{
            $this->onNewReleaseFind();//发现新版本
            return false;
        }
    }
    
    /**
     * 获取数据库适配器
     * 
     * daemon db 需要重连
     *
     * @param string $db The adapter to retrieve. Null to retrieve the default connection
     * @return Zend_Db_Adapter_Abstract
     */
    public static function getDb($db=NULL)
    {
        $db = DM_Model::getDefaultAdapter();
        if (!$db->isConnected()){
            $db->getConnection();
            //DB 默认就是不连的，不需要这句提示了。
            //self::getLog()->add('Db not connected, try to reconnect.');
        }
        
        if (!$db->isConnected()){
            throw new Exception('Failed to fetch connected db adapter, give up. ');
        }
        return $db;
    }
    
    /**
     * 获取日志对象
     */
    protected function getLog()
    {
        return DM_Log::create($this->service);
    }
    
    protected function getSingleGuard()
    {
        if ($this->singleGuard===NULL){
            $this->singleGuard=new DM_Daemon_SingleProcessGuard($this->service);
        }
        
        return $this->singleGuard;
    }
    
    protected function getReleaseCheck()
    {
        if ($this->releaseCheck===NULL){
            $this->releaseCheck=new DM_Daemon_ReleaseCheck($this->service);
        }
        
        return $this->releaseCheck;
    }
    
    protected function getMemoryCheck()
    {
        if ($this->memoryCheck===NULL){
            $this->memoryCheck=new DM_Daemon_MemoryCheck($this->service);
        }
        
        return $this->memoryCheck;
    }
    
    /**
     * 获取服务执行间歇
     * @return NULL
     */
    public function getCronInterval()
    {
        if ($this->sleepCron===NULL){
            $this->sleepCron=self::CRON_SLEEP;
        }else{
            return $this->sleepCron;
        }
    }
    
    /**
     * 获取实例
     * 
     * @param string $service
     * @return DM_Daemon
     */
    public static function instance($service=NULL)
    {
        if (empty($service) || preg_match('/[^\w]/is', $service)) {
            if (static::SERVICE || preg_match('/[^\w]/is', static::SERVICE)){
                $service=static::SERVICE;
            }else{
                throw new Exception('$service 参数无效，仅字母、数字、下划线。');
            }
        }
        
        if (!isset(self::$instance[$service]) || self::$instance[$service]===NULL){
            self::$instance[$service]=new static($service);
            self::$instance[$service]->service=$service;
            
            try {
                //必须移到这里，因为构造函数service还没初始化
                self::$instance[$service]->init();
            }catch (Exception $e){
                self::getLog()->add('Find DM_Daemon::instance Exception:'.$e->getMessage());
                //print_r($e);
                die();
            }
        }

        return self::$instance[$service];
    }
}