<?php
/**
 * 日志功能模块
 * 
 * @author Bruce
 *
 */
class DM_Log {
    /**
     * 缓存区最大字节数
     * 大小缓存为10K，通过缓存大小和时间两个保证。
     *
     * @var int
     */
    const MAX_BUFFER=10240;
    
    /**
     * 日志多少时间内得刷新到磁盘，避免在内存中呆太久
     *
     * 单位秒
     *
     * @var int
     */
    const MAX_MEMLIFE=30;
    
    /**
     * 日志保存天数
     *
     * 最早一个星期，有能力保存到一个月
     *
     * @var
     */
    const LOG_KEEP_LIFE=30;
    
    /**
     * 单例实例
     *
     * @var DM_Log
     */
    private static $instance=array();
    
    /**
     * 日志缓存
     */
    private $buffer='';
    
    /**
     * 服务标记
     */
    private $service='common';
    
    /**
     * 储存路径
     */
    private $filePath=NULL;
    
    /**
     * 文件名称
     */
    private $fileName=NULL;
    
    /**
     * 开始时间
     * @var int
     */
    private $startTime=NULL;
    
    /**
     * 文件描述符
     */
    private $fileFd=NULL;
    
    /**
     * 上次写入磁盘时间
     * @var int
     */
    private $flushTime=0;
    
    /**
     * 是否可用，调用接口Api不能输出
     */
    private $available=TRUE;
    
    /**
     * 是否输出到控制台
     */
    private $output=FALSE;
    
    /**
     * 是否新的一天标记 因为startTime非重入
     */
    private $newDayFlag=FALSE;
    
    protected function __construct($service)
    {
        //日志保存目录
        $this->filePath=APPLICATION_PATH.'/data/log/';
        $this->flushTime=time();//初始化
        $this->service=$service;
        
        if (!is_dir($this->filePath)) {
            mkdir($this->filePath, 0777, TRUE);
        }
    
        $this->clean();
    }
    
    private function getFileName()
    {
        if ($this->fileName!==NULL && date('d', $this->startTime)==date('d')){
            return $this->fileName;
        }
    
        if ($this->startTime===NULL || date('d', $this->startTime)!=date('d')){
            $this->startTime=time();
            //设置标记 这里非重入的
            $this->newDayFlag=TRUE;
        }
    
        $this->fileName=$this->filePath.date('Y-m-d', $this->startTime).'.'.$this->getService().'.log';
        return $this->fileName;
    }
    
    private function getService()
    {
        return $this->service;
    }
    
    public function setFilePath($path)
    {
        $this->filePath=$path;
        return $this;
    }
    
    /**
     * 添加一部分日志
     *
     * !!!!!!不能输出含有嵌套对象的对象，PHP挂，垃圾数据也超大。
     * 比如Zend相关对象。
     *
     * @param string $log
     * @return Transaction_Log_Abstract
     */
    public function add($log, $newLine=TRUE)
    {
        if (!$this->available) return FALSE;
        if (!is_string($log)){
            $log=var_export($log, TRUE );
        }

        $timestr=microtime(0);
        $microTime=explode(' ', $timestr);
        $floatMicro=$microTime[0];
        if ($newLine) $log="[".date('H:i:s'). ".".intval($floatMicro*1000000). "] ".$log."\n";
        $this->buffer.=$log;
    
        //超出缓存允许长度、超出内存缓存生命期输出到磁盘
        if ($this->needFulsh()){
            $this->flush();
        }

//        // 增加 logShare 日志
//        $startTime = microtime(true);
//        $shareLog = DM_LogShare::create($this->getService());
//        $shareLog->add($log, $newLine);
//        $endTime = microtime(true);
//        $costTime = $endTime-$startTime;
//        $shareLog->add('execute time:'.$costTime);

        return $this;
    }
    
    public function needFulsh()
    {
        return strlen($this->buffer)>self::MAX_BUFFER || time()-$this->flushTime>self::MAX_MEMLIFE || $this->newDay();
    }
    
    /**
     * 判断是否新的一天
     * 
     * 容易造成错误，因为getFileName是不可重入的；加入newDayFlag可重入
     * 
     * @return boolean
     */
    private function newDay()
    {
        return $this->newDayFlag || $this->getFileName()!=$this->fileName;
    }
    
    /**
     * 将日志刷新到磁盘
     */
    public function flush()
    {
        if (!$this->buffer) return FALSE;
    
        @fwrite($this->getFileFd(), $this->buffer);
        $this->out();
    
        $this->buffer='';
        $this->flushTime=time();
    
        return TRUE;
    }
    
    public function isEmpty()
    {
        return empty($this->buffer);
    }
    
    /**
     * 设为无用
     */
    public function disable()
    {
        $this->available=FALSE;
        return TRUE;
    }
    
    /**
     * 启用无用
     */
    public function enable()
    {
        $this->available=TRUE;
        return TRUE;
    }
    
    /**
     * 设为无用
     */
    public function disableOutput()
    {
        $this->output=FALSE;
        return TRUE;
    }
    
    /**启用无用
     */
    public function enableOutput()
    {
        $this->output=TRUE;
        return TRUE;
    }
    
    /**
     * 创建一个日志实例
     * @param string $service
     * @return DM_Log
     */
    public static function create($service)
    {
        if (!isset(self::$instance[$service]) || !self::$instance[$service] instanceof self){
            self::$instance[$service]=new static($service);
        }

        return self::$instance[$service];
    }

    /**
     * 释放一个实例
     * @param string $service
     * @return boolean
     */
    public function release()
    {
        if (isset(self::$instance[$this->service])&& self::$instance[$this->service] instanceof self){
            unset(self::$instance[$this->service]);
            return true;
        }
    
        return false;
    }
    
    /**
     * 将日志环境打印出来
     */
    public function out()
    {
        if (!$this->available || !$this->output || !$this->buffer) return false;
        if ((php_sapi_name() == 'cli')) {
            echo $this->buffer;
        }else{
            echo "<pre>".$this->buffer.'</pre><br>';
        }
    }
    
    /**
     * 清楚老的日志
     */
    protected function clean()
    {
        $files=glob($this->filePath.'*.log');
        if (!$files) return false;
    
        //print_r($files);
        foreach ($files as $file){
            $mtime=filemtime($file);
            if (time()-$mtime>self::LOG_KEEP_LIFE*86400){
                @unlink($file);
            }
        }
        return true;
    }
    
    /**
     * 获取文件打开符号
     */
    public function getFileFd()
    {
        if ($this->newDay() && $this->fileFd) {
            $this->buffer.="A new DAY begin, will transfer to new File:".$this->getFileName().PHP_EOL;
            @fwrite($this->fileFd, $this->buffer);
            //关闭原来的
            fclose($this->fileFd);
            
            //开启新一天
            $this->buffer="A new DAY begin, transfered from pid:".getmypid().PHP_EOL;
        }
        if ($this->fileFd===NULL || $this->newDay()){
            if (!file_exists($this->getFileName())){
                touch($this->getFileName());
                chmod($this->getFileName(), 0666);
            }
            $this->fileFd=fopen($this->getFileName(), 'ab+');
            //复原
            $this->newDayFlag=FALSE;
        }
    
        return $this->fileFd;
    }

    public static function getAllInstances()
    {
        return self::$instance;
    }
    
    /**
     * 后台Daemon服务情况下需要调用
     * 
     * 触发保存日志，不然可能造成日志丢失
     */
    public static function daemonFlush()
    {
         foreach (self::$instance as $log){
             if ($log instanceof self){
                 
                 if ($log->needFulsh()){
                     if ($log->isEmpty()) $log->add('.');
                     $log->flush();
                 }
             }
         }
         return true;
    }
    
    /**
     * 强制刷新，用于退出等
     */
    public static function forceFlush()
    {
        foreach (self::$instance as $log){
            if ($log instanceof self){
                $log->add('force flush.');
                $log->flush();
            }
        }
        return true;
    }
    
    public function __destruct()
    {
        //退出前输出缓存
        $this->flush();
    
        if ($this->fileFd){
            fclose($this->fileFd);
        }
    }
}