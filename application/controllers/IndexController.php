<?php

class IndexController extends DM_Controller
{
    public function indexAction()
    {
        // ./silk-v3-decoder/silk/decoder 1.amr 1.pcm
        // ffmpeg -f s16le -ar 24000 -i 1.pcm -f mp3 1.mp3

//        phpinfo();exit;

//        $cid = $this->_getParam('cid');
//        try {
//            $r = Helper_Gateway::initConfig()->sendToClient($cid, 1111);
////            $r = Helper_Gateway::sendToClient($cid, '111');
//            var_dump($r);
//        } catch (\Exception $e) {
//            echo $e->__toString();
//        }
//        exit;
//        // 队列使用
//        echo Helper_DisQueue::getInstance()->inQueue(Helper_DisQueue::job_name_test, ['time'=>time()]);
//        exit;

        try {

            // 单例模式
            echo gethostname();
            echo '<br>';
            $model = Model_Weixin::getInstance();
            $config = $model->getAdapter()->getConfig();
            echo 'from ', $config['role'],'    result:<br>';
            // slave db
            $model->fromSlaveDB();
            $config = $model->getAdapter()->getConfig();
            echo 'from ', $config['role'],'    result:<br>';

            $wx = $model->getByPrimaryId(19);
            var_dump($wx['WeixinID']);

            // restore master db
            $model->restoreOriginalAdapter();
            $config = $model->getAdapter()->getConfig();
            echo 'from ', $config['role'],'    result:<br>';

            $model->fromSlaveDB();
            $config = $model->getAdapter()->getConfig();
            echo 'from ', $config['role'],'    result:<br>';

            $model->fromMasterDB();
            $config = $model->getAdapter()->getConfig();
            echo 'from ', $config['role'],'    result:<br>';

//            $redis = Helper_Redis::getInstance();
//            $redis->setex('aaa', 60, 'bbb');
//
//            var_dump($redis->get('aaa'));
            exit;
        } catch (\Exception $e) {
            echo $e->__toString();
        }
    }

    public function testAction()
    {
//        TaskRun_Consumer::instance()->daemonRun();
    }

    public function fixAction()
    {
        exit('end');
        $data = Model_Distribution::getInstance()->fromMasterDB()->select()->from('distribution', ['DistributionID', 'Devices'])
                ->query()->fetchAll();
        foreach ($data as $d){
            //根据设备重新获取当前在线的WeixinID
            $onlineInfos = Model_Device::getInstance()->getOnlineWxIDBySerialNums(explode(',', trim($d['Devices'])));
            Model_Distribution::getInstance()->fromMasterDB()->update(['WeixinIDs' => implode(',', $onlineInfos['WeixinIDs'])], ['DistributionID = ?' => $d['DistributionID']]);
        }
        exit('ok');
    }

}

