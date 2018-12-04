<?php

use \GatewayWorker\Lib\Gateway;

function onMessage($client_id, $message) {
    $message_data = json_decode($message,true);
    if(!$message_data){
        return ;
    }
    $type = $message_data['TaskType'] ?? false;
    if(!$type){
        return ;
    }

    //判断任务是否正常完成
    if(isset($message_data['Status']) && $message_data['Status'] == 0){
    }

    //任务数据
    $data = $message_data['Data'];
    $flag = true;
    $message = [];

    // 设备与微信
    deviceWeixin($type, $client_id, $data);

    // web 后台聊天
    webChat($type, $client_id, $data);

    // 运营平台聊天
    openChat($type, $client_id, $data);

    // 监控词
    monitorWord($type, $client_id, $data);
}

/**
 * 设备与微信
 */
function deviceWeixin($type, $client_id, $data)
{
    switch ($type) {
        case 'Ping':
            $r = Device::ping($client_id, $data);
            if ($r === 'Pong') {
                Gateway::sendToClient($client_id, 'Pong');
            } else {
                $msg = 'current cid:'.$client_id . ';table cid:'.$r;
                Gateway::sendToClient($client_id, $msg);
                Gateway::closeClient($client_id);
            }
//            echo 'ping:'.time().';clientid:'.$client_id."\n";
//            Gateway::sendToClient($client_id, $client_id);
            break;
        case 'DeviceOnline':
            $r = Device::online($client_id, $data);
            if ($r === false) {
                Gateway::sendToClient($client_id, 'online device fail');
            } else {
                Gateway::sendToClient($client_id, 'device online success');
            }
            break;
        case 'DeviceOffline':
            Device::offline($client_id);
            break;
        case 'DeviceOnlineWeixin':
            $r = Device::onlineWeixin($client_id, $data);
            if (false !== $r) {
//                Gateway::sendToClient($client_id, json_encode(['TaskCode' => TASK_CODE_WEIXIN_ACCEPT_MSG, 'Data' => $r]));
            }
            break;
        case 'DeviceChangeWorkStatus':
            Device::changeWorkStatus($client_id, $data);
            break;
    }
}

/**
 * web后台聊天
 */
function webChat($type, $client_id, $data)
{
    //-------------微信号-------------
    switch ($type){
        case 'WebWxLeftFriend': // web 左侧微信朋友列表
            // {"TaskType":"WebWxLeftFriend","Data":{"Weixin":"1", "Nickname":"xxx", "Start":"0", "Num":"20"}}
            $results = Message::leftWxFriendMessages($client_id, $data);
//            Tasker::send($client_id, ['TaskType' => $type, 'Result' => $results]);
            Gateway::sendToClient($client_id, json_encode(['TaskType' => $type, 'Result' => $results]));
            break;
        case 'WebWxLeftGroup':
            // {"TaskType":"WebWxLeftGroup","Data":{"Weixin":"1", "Nickname":"xxx", "Start":"0", "Num":"20"}}
            $results = Message::leftWxGroupMessages($client_id, $data);
            Gateway::sendToClient($client_id, json_encode(['TaskType' => $type, 'Result' => $results]));
            break;
        case 'WebWxRightGet': // web 右侧聊天区域获取内容
            // {"TaskType":"WebWxLeftGroup","Data":{"Weixin":"1", "GetFrom":"xxx", "IsGroup":"N", "Start":"0", "Num":"20"}}
            $results = Message::rightChatGetMessages($client_id, $data);
            Gateway::sendToClient($client_id, json_encode(['TaskType' => $type, 'Result' => $results]));
            break;
        case 'WebWxRightSend': // web 右侧发送内容
            // {"TaskType":"WebWxLeftGroup","Data":{"Weixin":"1", "SendTo":"xxx", "IsGroup":"N", "Content":"xxx", "MsgType":"1"}}
            $results = Message::rightChatSendMessage($client_id, $data);
            Gateway::sendToClient($client_id, json_encode(['TaskType' => $type, 'Result' => $results]));
            break;
    }
}

/**
 * 运营平台聊天
 */
function openChat($type, $client_id, $data)
{
    switch ($type){
        case 'OpenWxLeftList': // web 左侧微信列表
            // {"TaskType":"OpenWxLeftList","Data":{"AdminID":"1", "DepartmentID":"1","Start":"0", "Num":"20"}}
            $results = Message::openLeftWxList($client_id, $data);
            Gateway::sendToClient($client_id, json_encode(['TaskType' => $type, 'Result' => $results]));
            break;
        case 'OpenWxLeftFriend': // web 左侧微信朋友列表
            // {"TaskType":"OpenWxLeftFriend","Data":{"AdminID":"1", "Weixin":"wx_xx", "Nickname":"xxx", "Start":"0", "Num":"20"}}
            $results = Message::openLeftWxFriendMessages($client_id, $data);
//            Tasker::send($client_id, ['TaskType' => $type, 'Result' => $results]);
            Gateway::sendToClient($client_id, json_encode(['TaskType' => $type, 'Result' => $results]));
            break;
        case 'OpenWxLeftGroup':
            // {"TaskType":"OpenWxLeftGroup","Data":{"AdminID":"1", "Weixin":"wx_xx", "Nickname":"xxx", "Start":"0", "Num":"20"}}
            // todo:
            $results = Message::leftWxGroupMessages($client_id, $data);
            Gateway::sendToClient($client_id, json_encode(['TaskType' => $type, 'Result' => $results]));
            break;
        case 'OpenWxRightGet': // web 右侧聊天区域获取内容
            // {"TaskType":"OpenWxRightGet","Data":{"AdminID":"1", "Weixin":"wx_xx", "GetFrom":"xxx","Word":"xxx","IsGroup":"N", "Start":"0", "Num":"20"}}
            $results = Message::openRightChatGetMessages($client_id, $data);
            Gateway::sendToClient($client_id, json_encode(['TaskType' => $type, 'Result' => $results]));
            break;
        case 'OpenWxRightSend': // web 右侧发送内容
            // {"TaskType":"OpenWxRightSend","Data":{"AdminID":"1", "Weixin":"wx_xx", "SendTo":"xxx", "IsGroup":"N", "Content":"xxx", "MsgType":"1","QueueID":"1"}}
            $results = Message::openRightChatSendMessage($client_id, $data);
            Gateway::sendToClient($client_id, json_encode(['TaskType' => $type, 'Result' => $results]));
            break;
        case 'OpenSetRead':
            // {"TaskType":"OpenSetRead","Data":{"AdminID":"1", "Weixin":"wx_xx", "FriendWx":"xxx", "IsGroup":"N"}}
            $results = Message::openSetRead($client_id, $data);
            Gateway::sendToClient($client_id, json_encode(['TaskType' => $type, 'Result' => $results]));
            break;
            break;
        case 'OpenWxLeftCategoryList': //web 左侧好友列表上方 好友已拥有的标签列表
            $results = Message::openFriendCategoryList($client_id, $data);
            Gateway::sendToClient($client_id, json_encode(['TaskType' => $type, 'Result' => $results]));
            break;

    }
}

/**
 * 监控词
 */
function monitorWord($type, $client_id, $data)
{
    switch ($type) {
        case 'OpenMonitorWordList':
            // {"TaskType":"OpenMonitorWordList","Data":{"AdminID":"R7H1b3iRdKKyYgm1eJKPL31yhN14F8Ce"}}
            $results = MonitorWord::openMonitorWordList($client_id, $data);
            Gateway::sendToClient($client_id, json_encode(['TaskType' => $type, 'Result' => $results]));
            break;
        case 'OpenMonitorWordFriends':
            // {"TaskType":"OpenMonitorWordFriends","Data":{"AdminID":"R7H1b3iRdKKyYgm1eJKPL31yhN14F8Ce","Weixin":"wx_xx","Word":"你", "IsGroup":"N", "Start":"0", "Num":"20", "CategoryID":"2"}}
            $results = MonitorWord::openMonitorWordFriends($client_id, $data);
            Gateway::sendToClient($client_id, json_encode(['TaskType' => $type, 'Result' => $results]));
            break;
        case 'OpenMonitorWordAdd':
            // {"TaskType":"OpenMonitorWordAdd","Data":{"AdminID":"R7H1b3iRdKKyYgm1eJKPL31yhN14F8Ce","Word":"你好"}}
            $results = MonitorWord::openMonitorWordAdd($client_id, $data);
            Gateway::sendToClient($client_id, json_encode(['TaskType' => $type, 'Result' => $results]));
            break;
        case 'OpenMonitorWordDel':
            // {"TaskType":"OpenMonitorWordDel","Data":{"AdminID":"R7H1b3iRdKKyYgm1eJKPL31yhN14F8Ce","WordID":"xx"}}
            $results = MonitorWord::openMonitorWordDel($client_id, $data);
            Gateway::sendToClient($client_id, json_encode(['TaskType' => $type, 'Result' => $results]));
            break;
    }
}