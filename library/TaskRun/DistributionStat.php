<?php
/**
 * Created by PhpStorm.
 * User: jakins
 * Date: 2018/10/10
 * Time: 14:43
 * 每小时更新派单统计数据
 */
class TaskRun_DistributionStat extends DM_Daemon
{
    const CRON_SLEEP = 10000000;
    const SERVICE='distributionStat';

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
            $sendTime = date('Y-m-d', strtotime('-29 days')); //查询30天之内的派单记录
            $now = date('Y-m-d H:i:s');
            $model = new Model_Distribution();
            $deviceModel = new Model_Device();
            $db = $model->getHashSlaveDB();
            $pagesize = 50; //一次处理50条
            $page = 1;
            $flag = true;
            do {
                $data = $model->select()->where('SendTime >= ?', $sendTime.' 00:00:00')->where('SendTime <= ?', $now)->limitPage($page, $pagesize)->query()->fetchAll();
                if(!$data){
                    $flag = false;
                }else {
                    $onlineWeixin = $deviceModel->getOneOnlineWeixin();
                    if(!$onlineWeixin){
                        self::getLog()->add('未找到在线微信设备,无法下发查询公众号阅读数任务');
                    }
                    foreach ($data as $res){
                        $updateData = [];
                        if($res['Devices'] == ''){
                            self::getLog()->add("个号设备为空，不统计，ID：".$res['DistributionID']);
                            continue;
                        }

                        if($res['WeixinIDs'] == ''){
                            self::getLog()->add("微信ID为空，不统计，ID：".$res['DistributionID']);
                            continue;
                        }

                        $weixinIDs = explode(',', $res['WeixinIDs']);
                        //根据微信ID查询微信号,由于订单里可能是weixin也可能是alias
                        $sql = 'select Weixin from weixins_view where WeixinID IN ('.$res['WeixinIDs'].')';
                        $weixins = $db->query($sql)->fetchAll();
                        if(empty($weixins)){
                            self::getLog()->add('未找到微信号，ID：'.$res['DistributionID']);
                            continue;
                        }


                        $hDateTime = date('Y-m-d H:00:00', strtotime($res['SendTime']));
                        $h2DateTime = date("Y-m-d H:00:00", strtotime('+2 hours', strtotime($hDateTime)));
                        $h24DateTime = date('Y-m-d H:00:00', strtotime('+24 hours', strtotime($hDateTime)));
                        $h48DateTime = date('Y-m-d H:00:00', strtotime('+48 hours', strtotime($hDateTime)));
                        $h72DateTime = date('Y-m-d H:00:00', strtotime('+72 hours', strtotime($hDateTime)));

                        //查询这些微信从派单时间72H内的进粉情况
                        $h2Friends = 0; //2H总进粉数
                        $h24Friends = 0; //24H总进粉数
                        $h48Friends = 0; //48H总进粉数
                        $h72Friends = 0; //72H总进粉数
                        $friend_sql = [];
                        foreach ($weixinIDs as $weixinID){
                            $friend_sql[] = " (select WeixinID,Sum(NewFriendNum) as newNum,'h2' as 'hours' from stat_hours where WeixinID = '{$weixinID}' and DateTime >= '{$hDateTime}' and DateTime < '{$h2DateTime}') ";
                            $friend_sql[] = " (select WeixinID,Sum(NewFriendNum) as newNum,'h24' as 'hours' from stat_hours where WeixinID = '{$weixinID}' and DateTime >= '{$hDateTime}' and DateTime < '{$h24DateTime}') ";
                            $friend_sql[] = " (select WeixinID,Sum(NewFriendNum) as newNum,'h48' as 'hours' from stat_hours where WeixinID = '{$weixinID}' and DateTime >= '{$hDateTime}' and DateTime < '{$h48DateTime}') ";
                            $friend_sql[] = " (select WeixinID,Sum(NewFriendNum) as newNum,'h72' as 'hours' from stat_hours where WeixinID = '{$weixinID}' and DateTime >= '{$hDateTime}' and DateTime < '{$h72DateTime}') ";
                        }
                        $friends = $db->query(implode('UNION ALL', $friend_sql))->fetchAll();
                        if(!empty($friends)){
                            foreach ($friends as $friend){
                                if($friend['hours'] == 'h2'){
                                    $h2Friends += $friend['newNum'] > 0?$friend['newNum']:0;
                                }elseif($friend['hours'] == 'h24'){
                                    $h24Friends += $friend['newNum'] > 0?$friend['newNum']:0;
                                }elseif($friend['hours'] == 'h48'){
                                    $h48Friends += $friend['newNum'] > 0?$friend['newNum']:0;
                                }elseif($friend['hours'] == 'h72'){
                                    $h72Friends += $friend['newNum'] > 0?$friend['newNum']:0;
                                }
                            }
                        }
                        $updateData['2HFriendNum'] = $h2Friends;
                        $updateData['24HFriendNum'] = $h24Friends;
                        $updateData['48HFriendNum'] = $h48Friends;
                        $updateData['72HFriendNum'] = $h72Friends;
                        $updateData['TotalFriendNum'] = $h72Friends;

                        //查询这些微信的下单情况
                        $h24OrderAmount = 0;
                        $h48OrderNum = 0;
                        $h48OrderAmount = 0;
                        $d15OrderAmount = 0;
                        $d15OrderNum = 0;
                        $d30OrderAmount = 0;
                        $d30OrderNum = 0;

                        $order_sql = [];
                        foreach ($weixins as $weixin){
                            //24小时下单量和销售额
                            $order_sql[] = " (select Seller,Sum(TotalAmount) as TotalOrderAmount,count(OrderID) as TotalOrderNum,'h24' as 'hours' from orders where Seller = '{$weixin['Weixin']}' and OrderDate = '".date('Y-m-d', strtotime($res['SendTime']))."' and BuyerAddTime >= '".date('Y-m-d', strtotime($res['SendTime']))."' and BuyerAddTime <'".date('Y-m-d', strtotime('+3 days', strtotime($res['SendTime'])))."' and Status IN (1,2) ) ";
                            //48小时
                            $order_sql[] = " (select Seller,Sum(TotalAmount) as TotalOrderAmount,count(OrderID) as TotalOrderNum,'h48' as 'hours' from orders where Seller = '{$weixin['Weixin']}' and OrderDate >= '".date('Y-m-d', strtotime($res['SendTime']))."' and OrderDate < '".date('Y-m-d', strtotime('+2 days', strtotime($res['SendTime'])))."' and BuyerAddTime >= '".date('Y-m-d', strtotime($res['SendTime']))."' and BuyerAddTime <'".date('Y-m-d', strtotime('+3 days', strtotime($res['SendTime'])))."' and Status IN (1,2) ) ";
                            //15天
                            $order_sql[] = " (select Seller,Sum(TotalAmount) as TotalOrderAmount,count(OrderID) as TotalOrderNum,'d15' as 'hours' from orders where Seller = '{$weixin['Weixin']}' and OrderDate >= '".date('Y-m-d', strtotime($res['SendTime']))."' and OrderDate < '".date('Y-m-d', strtotime('+15 days', strtotime($res['SendTime'])))."' and BuyerAddTime >= '".date('Y-m-d', strtotime($res['SendTime']))."' and BuyerAddTime <'".date('Y-m-d', strtotime('+3 days', strtotime($res['SendTime'])))."' and Status IN (1,2) ) ";
                            //30天
                            $order_sql[] = " (select Seller,Sum(TotalAmount) as TotalOrderAmount,count(OrderID) as TotalOrderNum,'d30' as 'hours' from orders where Seller = '{$weixin['Weixin']}' and OrderDate >= '".date('Y-m-d', strtotime($res['SendTime']))."' and OrderDate < '".date('Y-m-d', strtotime('+30 days', strtotime($res['SendTime'])))."' and BuyerAddTime >= '".date('Y-m-d', strtotime($res['SendTime']))."' and BuyerAddTime <'".date('Y-m-d', strtotime('+3 days', strtotime($res['SendTime'])))."' and Status IN (1,2) ) ";
                        }
                        $orders = $db->query(implode('UNION ALL', $order_sql))->fetchAll();
                        foreach ($orders as $order){
                            if($order['hours'] == 'h24'){
                                $h24OrderAmount += $order['TotalOrderAmount'] > 0?$order['TotalOrderAmount']:0;
                            }elseif($order['hours'] == 'h48'){
                                $h48OrderAmount += $order['TotalOrderAmount'] > 0?$order['TotalOrderAmount']:0;
                                $h48OrderNum += $order['TotalOrderNum'] > 0?$order['TotalOrderNum']:0;
                            }elseif($order['hours'] == 'd15'){
                                $d15OrderAmount += $order['TotalOrderAmount'] > 0?$order['TotalOrderAmount']:0;
                                $d15OrderNum += $order['TotalOrderNum'] > 0?$order['TotalOrderNum']:0;
                            }elseif($order['hours'] == 'd30'){
                                $d30OrderAmount += $order['TotalOrderAmount'] > 0?$order['TotalOrderAmount']:0;
                                $d30OrderNum += $order['TotalOrderNum'] > 0?$order['TotalOrderNum']:0;
                            }
                        }
                        $updateData['48HOrderNum'] = $h48OrderNum;
                        $updateData['48HOrderAmount'] = $h48OrderAmount;
                        $updateData['TotalOrderNum'] = $d30OrderNum;
                        $updateData['TotalOrderAmount'] = $d30OrderAmount;
                        $updateData['24HIncomePerFriend'] = empty($h72Friends)?0:bcdiv($h24OrderAmount, $h72Friends, 2);
                        $updateData['15DIncomePerFriend'] = empty($h72Friends)?0:bcdiv($d15OrderAmount, $h72Friends, 2);
                        $updateData['30DIncomePerFriend'] = empty($h72Friends)?0:bcdiv($d30OrderAmount, $h72Friends, 2);
                        if($updateData){
                            $model->update($updateData, ['DistributionID = ?' => $res['DistributionID']]);
                        }

                        //查询公众号、服务号文章的阅读数,找到空闲设备下发任务
                        // 暂时不下发，客户端在研究是否会封号
//                        if(in_array($res['Platform'], [Model_Distribution::DISTRIBUTION_PLATFORM_SEVICE, Model_Distribution::DISTRIBUTION_PLATFORM_GZH])
//                            && $res['ViewNumGetTime'] <= date('Y-m-d H:i:s', strtotime('-1 hour'))
//                            && $res['ArticleUrl'] != '' && $onlineWeixin
//                        ){
//                            $task_config = [
//                                'Url' => $res['ArticleUrl']
//                            ];
//                            Model_Task::addCommonTask(TASK_CODE_GET_GZHURL_VIEWNUM, $onlineWeixin['OnlineWeixinID'], json_encode($task_config), $res['AdminID']);
//                        }
                    }
                    $page++;
                }

            }while ($flag);
            die();
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