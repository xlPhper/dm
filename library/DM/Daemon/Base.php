<?php
/**
 * Daemon库基类
 * 
 * @author Bruce
 * @since 2014/10/24
 */
class DM_Daemon_Base
{
    const LOG_SERVICE=NULL;
    
    /**
     * 运行的服务
     *
     * @var string
     */
    protected $service=NULL;
    
    public function __construct($service)
    {
        if (!$service || preg_match('/[^\w]/is', $service)) {
            throw new Exception("\$service 参数无效。");
        }
        
        $this->service=$service;
    }
    
    /**
     * 获取日志对象
     */
    protected function getLog()
    {
        if (!static::LOG_SERVICE || preg_match('/[^\w]/is', static::LOG_SERVICE)) {
            throw new Exception("static::LOG_SERVICE 无效。");
        }
        
        return DM_Log::create(static::LOG_SERVICE);
    }
}