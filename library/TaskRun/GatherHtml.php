<?php
/**
 * Created by PhpStorm.
 * User: jakins
 * Date: 2018/10/30
 * Time: 10:18
 * 采集网页html
 */
class TaskRun_GatherHtml extends DM_Daemon
{
    const CRON_SLEEP = 1000000;
    const SERVICE = 'gatherHtml';

    protected function run()
    {
        $this->done();
    }

    protected function done()
    {
        set_time_limit(0);
        try{
            //一次代理抓取50条 频率
            $model = new Model_Linkurl();
            $data = $model->getQuerySelect(['Status' => Model_Linkurl::STATUS_PENDING], false)->order('LinkurlID Asc')->limit(50)->query()->fetchAll();
            //找到待抓取html的地址数据
            if(!empty($data)){
                try{
                    //获取proxy代理
                    $proxyResponse = DM_Controller::curl(Model_Linkurl::GET_PROXY_URL, null, false);
                    $proxy = json_decode($proxyResponse, true);
                    if(isset($proxy['d']) && isset($proxy['d']['Ip']) && isset($proxy['d']['Port'])){
                        self::getLog()->add('proxy res:'. json_encode($proxy));
                        foreach ($data as $row){
                            try{
                                $response = DM_Controller::curl($row['Url'], null, false, ['proxy' => $proxy['d']['Ip'], 'proxy_port' => $proxy['d']['Port']]);
                                //更新地址状态为已解析
                                $model->update(['Status' => Model_Linkurl::STATUS_GATHERED, 'Html' => $response, 'UpdateDate' => date('Y-m-d H:i:s')], ['LinkurlID = ?' => $row['LinkurlID']]);
                            }catch (Exception $e){
                                self::getLog()->add('gather error:'.$e->getMessage());
                                try{
                                    $proxyResponse = DM_Controller::curl(Model_Linkurl::GET_PROXY_URL, null, false);
                                    $proxy = json_decode($proxyResponse, true);
                                    if(!isset($proxy['d']) || !isset($proxy['d']['Ip']) || !isset($proxy['d']['Port'])){
                                        throw new Exception('没有可用的新代理');
                                    }
                                    self::getLog()->add('new proxy res:'.json_encode($proxy));
                                }catch(Exception $e){
                                    throw new Exception('get new proxy error:'.$e->getMessage());
                                }
                            }
                        }
                    }else{
                        self::getLog()->add('未获取到可用的代理服务器');
                    }
                }catch (Exception $e){
                    self::getLog()->add('get proxy error:'.$e->getMessage());
                }
            }
        } catch (Exception $e){
            self::getLog()->add('error:'.$e->getMessage());
        }
    }

    protected function init()
    {
        parent::init();
        self::getLog()->add("\n\n**********************定时更新**************************");
        self::getLog()->flush();
    }

    /**
     * 发现新版本的事件
     */
    protected function onNewReleaseFind()
    {
        self::getLog()->add('Found new release: '.$this->getReleaseCheck()->getRelease().', will quit for update.');
        die();
    }

    /**
     * 系统运行过程检测到内存不够的事件
     */
    protected function onOutOfMemory()
    {
        self::getLog()->add('System find that daemon will be out of memory, will quit for restart.');
        die();
    }
}