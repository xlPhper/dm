<?php
/**
 * Created by PhpStorm.
 * User: jakins
 * Date: 2018/10/17
 * Time: 16:42
 * 解析抓取到的页面html
 */
class TaskRun_QrGatherAnalyse extends DM_Daemon
{
    const CRON_SLEEP = 1000000;
    const SERVICE='qrGatherAnalyse';

    /**
     * 执行Daemon任务
     *
     */
    protected function run()
    {
        $this->done();
    }

    protected function done()
    {
        set_time_limit(0);
        try{
            //找到已抓取到html的页面
            $model = new Model_Linkurl();
            $data = $model->getQuerySelect(['Status' => Model_Linkurl::STATUS_GATHERED], false)->order('LinkurlID Asc')->limit(100)->query()->fetchAll();
            //找到待解析的地址数据
            if(!empty($data)){
                $doubanModel = new Model_Gather_Douban();
                foreach ($data as $row){
                    if(empty($row['Html'])){
                        self::getLog()->add('未获取到html, LinkurlID：'.$row['LinkurlID']);
                        continue;
                    }
                    try{
                        //豆瓣地址处理
                        if($row['Channel'] == Model_Linkurl::GATHER_CHANNEL_DOUBAN){
                            if($row['Type'] == Model_Linkurl::GATHER_URL_TYPE_SEARCH){
                                try{
                                    //获取proxy代理
                                    $proxyResponse = DM_Controller::curl(Model_Linkurl::GET_PROXY_URL, null, false);
                                    $proxy = json_decode($proxyResponse, true);
                                    if(isset($proxy['d']) && isset($proxy['d']['Ip']) && isset($proxy['d']['Port'])){
                                        $doubanModel->searchHtmlDeal($row['Html'], ['proxy' => $proxy['d']['Ip'], 'proxy_port' => $proxy['d']['Port']]);
                                    }else{
                                        throw new Exception('未获取到可用的代理服务器');
                                    }
                                }catch (Exception $e){
                                    throw new Exception($e->getMessage());
                                }
                            }else if($row['Type'] == Model_Linkurl::GATHER_URL_TYPE_LIST){
                                $doubanModel->discussionListHtmlDeal($row['Html']);
                            }else if($row['Type'] == Model_Linkurl::GATHER_URL_TYPE_DETAIL){
                                $doubanModel->detailHtmlDeal($row['Html']);
                            }
                        }
                        //更新地址状态为已解析
                        $model->update(['Status' => Model_Linkurl::STATUS_ANALYSED], ['LinkurlID = ?' => $row['LinkurlID']]);
                    }catch (Exception $e){
                        self::getLog()->add('analyse error:'.$e->getMessage());
                    }
                }
            }
        } catch (Exception $e){
            self::getLog()->add('error:'.$e->getMessage());
        }
    }

    protected function init()
    {
        try{
            $redisconfig = Zend_Registry::get("config")['qrredis'];
            Helper_Redis::init($redisconfig);
            parent::init();
            self::getLog()->add("\n\n**********************定时更新**************************");
        } catch (Exception $e){
            self::getLog()->add("init error:".$e->getMessage());
        }
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