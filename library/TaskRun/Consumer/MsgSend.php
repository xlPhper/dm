<?php

/**
 * 具体实现类
 */
class TaskRun_Consumer_MsgSend implements  TaskRun_Consumer_Interface
{
    /**
     * 具体实现方法
     */
    public function consumer($data)
    {
        // ['ReceiverWxId' => 1, 'Data' => $data]
        $receiverWxId = $data['ReceiverWxId'];
        $msgData = $data['Data'];
        $device = Model_Device::getInstance()->fromSlaveDB()->fetchRow(['OnlineWeixinID = ?' => $receiverWxId]);
        if ($device && $device['ClientID'] !== '') {
            $client_id = $device['ClientID'];
            $response = json_encode(['TaskCode' => TASK_CODE_SEND_CHAT_MSG, 'Data' => $msgData]);
            DM_Log::create(TaskRun_Consumer::SERVICE)->add('content:'.$response);
            $res = Helper_Gateway::initConfig()->sendToClient($client_id, $response);
            if ($res) {
                DM_Log::create(TaskRun_Consumer::SERVICE)->add('auto reply client ok, msgId:'.$msgData['MessageID'].';content:'.$msgData['Content']);
            } else {
                DM_Log::create(TaskRun_Consumer::SERVICE)->add('auto reply client fail, msgId:'.$msgData['MessageID']);
            }
        } else {
            DM_Log::create(TaskRun_Consumer::SERVICE)->add('auto reply client offline, msgId:'.$msgData['MessageID']);
        }
    }
}