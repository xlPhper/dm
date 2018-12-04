<?php

require_once APPLICATION_PATH . '/controllers/Open/OpenBase.php';

class Open_OperationController extends OpenBase
{

    /**
     * 首页订单数据
     */
    public function homeOrderAction()
    {
        $num = $this->_getParam('Day', 0);
        if ($num == 0) {
            $day = date('Y-m-d');
        } else {
            $day = date('Y-m-d', strtotime(' -' . $num . ' day'));
        }
        $yesterDay = date('Y-m-d', strtotime($day . ' -1 day'));

        $weixinFriendModel = new Model_Weixin_Friend();
        $orderModel = new Model_Orders();
        $weixinModel = new Model_Weixin();

        if ($this->adminWxIds) {
            // 获取微信帐号
            $weixinWxs = $weixinModel->getWeixinAccount($this->adminWxIds);

            // 获取这两天的订单数/总金额
            $orders = $orderModel->getTheseDayData($weixinWxs,$yesterDay, $day);

            // 好友总数
            $todayFriendNum = $weixinFriendModel->finWeixinFriends($this->adminWxIds, $day);
            $yesterdayFriendNum = $weixinFriendModel->finWeixinFriends($this->adminWxIds, $yesterDay);

            // 今天下订单人数
            $todayOrderNum = $orderModel->getTheDayBuyerNum($day);

            // 昨天的下订单人数
            $yesterdayOrderNum = $orderModel->getTheDayBuyerNum($yesterDay);

            // 订单数
            $orderData['OrderNum'] = empty($orders[$day]['OrderNum']) ? 0 : $orders[$day]['OrderNum'];
            $orderData['OrderNumCompare'] = $orderData['OrderNum'] - (empty($orders[$yesterDay]['OrderNum']) ? 0 : $orders[$yesterDay]['OrderNum']);

            // 总额
            $orderData['TotalAmount'] = empty($orders[$day]['TotalAmount']) ? 0 : $orders[$day]['TotalAmount'];
            $orderData['TotalAmountCompare'] = $orderData['TotalAmount'] - (empty($orders[$yesterDay]['TotalAmount']) ? 0 : $orders[$yesterDay]['TotalAmount']);

            // 单价
            $orderData['UnitPrice'] = $todayOrderNum == 0 ? 0 : number_format($orderData['TotalAmount'] / $todayOrderNum, 2, '.', '');
            if (empty($yesterdayOrderNum) || $yesterdayOrderNum == 0) {
                $orderData['UnitPriceCompare'] = $orderData['UnitPrice'];
            } else {
                $orderData['UnitPriceCompare'] = $orderData['UnitPrice'] - (number_format($orders[$yesterDay]['TotalAmount'] / $yesterdayOrderNum, 2, '.', ''));
            }

            // 单粉收益
            $orderData['AverageRevenue'] = $todayFriendNum == 0 ? 0 : number_format($orderData['TotalAmount'] / $todayFriendNum, 2, '.', '');
            $orderData['AverageRevenueCompare'] = $orderData['AverageRevenue'] - ($yesterdayFriendNum == 0 ? 0 : number_format(empty($orders[$yesterDay]['TotalAmount']) ? 0 : $orders[$yesterDay]['TotalAmount'] / $yesterdayFriendNum, 2, '.', ''));

            // 客户购买率

            $orderData['Rate'] = $todayFriendNum == 0 ? 00.00 : (number_format($todayOrderNum / $todayFriendNum, 4, '.', '')*100);
            $orderData['RateCompare'] = $orderData['Rate'] - ($yesterdayFriendNum == 0 ? 00.00 : (number_format($yesterdayOrderNum / $yesterdayFriendNum, 4, '.', '')*100));

            $this->showJson(1, '订单数据', $orderData);

        } else {
            $this->showJson(0, '无管理的微信号信息');
        }

    }

    /**
     * 首页客户数据
     */
    public function homeCustomerAction()
    {

        $num = $this->_getParam('Day', 0);
        if ($num == 0) {
            $day = date('Y-m-d');
        } else {
            $day = date('Y-m-d', strtotime(' -' . $num . ' day'));
        }
        $yesterDay = date('Y-m-d', strtotime($day . ' -1 day'));
        $lastWeek = date('Y-m-d', strtotime($day . ' -7 day'));
        $lastMonth = date('Y-m-d', strtotime($day . ' -30 day'));

        $weixinFriendModel = new Model_Weixin_Friend();
        $meaasgeModel = new Model_Message();
        $weixinModel = new Model_Weixin();




        if ($this->adminWxIds) {

            // 获取微信帐号
            $weixinWxs = $weixinModel->getWeixinAccount($this->adminWxIds);

            // 好友总数
            $todayFriendNum = $weixinFriendModel->finWeixinFriends($this->adminWxIds, $day);
            $yesterdayFriendNum = $weixinFriendModel->finWeixinFriends($this->adminWxIds, $yesterDay);
            $lastWeekFriendNum = $weixinFriendModel->finWeixinFriends($this->adminWxIds, $lastWeek);
            $lastMonthFriendNum = $weixinFriendModel->finWeixinFriends($this->adminWxIds, $lastMonth);

            // 新增好友数
            $addFriendNum = $weixinFriendModel->getAddFriend($this->adminWxIds, [$day, $yesterDay, $lastWeek, $lastMonth]);
            $oneAddFriendNum = empty($addFriendNum[$day]['FriendNum']) ? 0 : $addFriendNum[$day]['FriendNum'];
            $towAddFriendNum = empty($addFriendNum[$yesterDay]['FriendNum']) ? 0 : $addFriendNum[$yesterDay]['FriendNum'];
            $weekAddFriendNum = empty($addFriendNum[$lastWeek]['FriendNum']) ? 0 : $addFriendNum[$lastWeek]['FriendNum'];
            $monthAddFriendNum = empty($addFriendNum[$lastMonth]['FriendNum']) ? 0 : $addFriendNum[$lastMonth]['FriendNum'];

            // 删除好友数
            $delFriendNum = $weixinFriendModel->getDelFriend($this->adminWxIds, [$day, $yesterDay, $lastWeek, $lastMonth]);
            $oneDelFriendNum = empty($delFriendNum[$day]['FriendNum']) ? 0 : $delFriendNum[$day]['FriendNum'];
            $towDelFriendNum = empty($delFriendNum[$yesterDay]['FriendNum']) ? 0 : $delFriendNum[$yesterDay]['FriendNum'];
            $weekDelFriendNum = empty($delFriendNum[$lastWeek]['FriendNum']) ? 0 : $delFriendNum[$lastWeek]['FriendNum'];
            $monthDelFriendNum = empty($delFriendNum[$lastMonth]['FriendNum']) ? 0 : $delFriendNum[$lastMonth]['FriendNum'];

            // 用户活跃数
            $activeFriendNum = $meaasgeModel->getActiveFirend($weixinWxs, [$day, $yesterDay, $lastWeek, $lastMonth]);
            $oneActiveFriendNum = empty($activeFriendNum[$day]['FriendNum']) ? 0 : $activeFriendNum[$day]['FriendNum'];
            $towActiveFriendNum = empty($activeFriendNum[$yesterDay]['FriendNum']) ? 0 : $activeFriendNum[$yesterDay]['FriendNum'];
            $weekActiveFriendNum = empty($activeFriendNum[$lastWeek]['FriendNum']) ? 0 : $activeFriendNum[$lastWeek]['FriendNum'];
            $monthActiveFriendNum = empty($activeFriendNum[$lastMonth]['FriendNum']) ? 0 : $activeFriendNum[$lastMonth]['FriendNum'];

            // 客户总量
            $customerData['CustomerNum'] = $todayFriendNum;
            $customerData['CustomerNumCompare'] = $todayFriendNum - $yesterdayFriendNum;
            $customerData['CustomerNumData'] = [
                'Day' => $yesterdayFriendNum == 0 ? 00.00 : number_format(($todayFriendNum - $yesterdayFriendNum) / $yesterdayFriendNum, 2, '.', ''),
                'Week' => $lastWeekFriendNum == 0 ? 00.00 : number_format(($todayFriendNum - $lastWeekFriendNum) / $lastWeekFriendNum, 2, '.', ''),
                'Month' => $lastMonthFriendNum == 0 ? 00.00 : number_format(($todayFriendNum - $lastMonthFriendNum) / $lastMonthFriendNum, 2, '.', '')
            ];

            // 客户新增
            $customerData['NewCustomerNum'] = $oneAddFriendNum;
            $customerData['NewCustomerNumCompare'] = $oneAddFriendNum - $towAddFriendNum;
            $customerData['NewCustomerNumData'] = [
                'Day' => $towAddFriendNum == 0 ? 00.00 : number_format(($oneAddFriendNum - $towAddFriendNum) / $towAddFriendNum, 2, '.', ''),
                'Week' => $weekAddFriendNum == 0 ? 00.00 : number_format(($oneAddFriendNum - $weekAddFriendNum) / $weekAddFriendNum, 2, '.', ''),
                'Month' => $monthAddFriendNum == 0 ? 00.00 : number_format(($oneAddFriendNum - $monthAddFriendNum) / $monthAddFriendNum, 2, '.', '')
            ];

            // 客户流失
            $customerData['DelCustomerNum'] = $oneDelFriendNum;
            $customerData['DelCustomerNumCompare'] = $oneDelFriendNum - $towDelFriendNum;
            $customerData['DelCustomerNumData'] = [
                'Day' => $towDelFriendNum == 0 ? 00.00 : number_format(($oneDelFriendNum - $towDelFriendNum) / $towDelFriendNum, 2, '.', ''),
                'Week' => $weekDelFriendNum == 0 ? 00.00 : number_format(($oneDelFriendNum - $weekDelFriendNum) / $weekDelFriendNum, 2, '.', ''),
                'Month' => $monthDelFriendNum == 0 ? 00.00 : number_format(($oneDelFriendNum - $monthDelFriendNum) / $monthDelFriendNum, 2, '.', '')
            ];

            // 客户活跃数
            $customerData['ActiveCustomerNum'] = $oneActiveFriendNum;
            $customerData['ActiveCustomerNumCompare'] = $oneActiveFriendNum - $towActiveFriendNum;
            $customerData['ActiveCustomerNumData'] = [
                'Day' => $towActiveFriendNum == 0 ? 00.00 : number_format(($oneActiveFriendNum - $towActiveFriendNum) / $towActiveFriendNum, 2, '.', ''),
                'Week' => $weekActiveFriendNum == 0 ? 00.00 : number_format(($oneActiveFriendNum - $weekActiveFriendNum) / $weekActiveFriendNum, 2, '.', ''),
                'Month' => $monthActiveFriendNum == 0 ? 00.00 : number_format(($oneActiveFriendNum - $monthActiveFriendNum) / $monthActiveFriendNum, 2, '.', '')
            ];

            $this->showJson(1, '用户数据', $customerData);

        } else {
            $this->showJson(0, '无管理的微信号信息');
        }

    }

    /**
     * 首页的运营统计数据
     */
    public function homeOperationAction()
    {
        $day = $this->_getParam('Day', 0);
        if ($day > 1) {
            $startDate = date('Y-m-d');
            $endDate = date('Y-m-d', strtotime($startDate . ' -'.$day.' day'));
        } else {
            $startDate = $endDate = date('Y-m-d', strtotime(' -' . $day . ' day'));
        }

        $meaasgeModel = new Model_Message();
        $albumModel = new Model_Album();
        $albmuReplyModel = new Model_AlbumReply();
        $weixinModel = new Model_Weixin();
        $weixinFriendModel = new  Model_Weixin_Friend();

        if ($this->adminWxIds) {

            // 好友总数
            $friendNum = $weixinFriendModel->finWeixinFriends($this->adminWxIds,$endDate);

            // 获取微信帐号
            $weixinWxs = $weixinModel->getWeixinAccount($this->adminWxIds);

            // 信息发送数
            $messageNum = $meaasgeModel->getMessageData($weixinWxs,$startDate,$endDate);

            // 回复总数
            $answerMessageNum = $meaasgeModel->getAnswerMessageData($this->adminWxIds,$startDate,$endDate);

            // 获取朋友圈数据
            $albmuData = $albumModel->getAlbumData($weixinWxs,$startDate,$endDate);

            // 消息发送总数
            $operactionData['MessageNum'] = $messageNum;
            $operactionData['MessageNumCompare'] = $friendNum == 0 ? 00.00 : number_format($messageNum / $friendNum, 2, '.', '');

            // 回复总数
            $operactionData['AnswerMessageNum'] = $answerMessageNum;
            $operactionData['AnswerMessageNumCompare'] = $messageNum == 0 ? 00.00 : number_format($answerMessageNum / $messageNum, 2, '.', '');

            // 未回复总数
            $operactionData['UnanswerMessageNum'] = $messageNum - $answerMessageNum;
            $operactionData['UnanswerMessageCompare'] = $messageNum == 0 ? 00.00 : number_format(($messageNum - $answerMessageNum) / $messageNum, 2, '.', '');

            //朋友圈推送
            $operactionData['AlbumNum'] = count($albmuData);
            $textNum = 0;
            $videoNum = 0;
            $photoNum = 0;
            $albumIds = [];
            foreach ($albmuData as $a) {
                if ($a['VideoCover'] != '' && $a['VideoMediaID'] != '') {
                    $videoNum++;
                } elseif ($a['Photos'] != '') {
                    $photoNum++;
                } else {
                    $textNum++;
                }
                $albumIds[] = $a['AlbumID'];
            }
            $operactionData['AlbumData'] = [
                'VideoNum' => $videoNum,
                'PhotoNum' => $photoNum,
                'TextNum' => $textNum,
            ];

            // 获取朋友圈互动数据
            if ($albumIds) {
                $albumReplyData = $albmuReplyModel->getAlbumReply($albumIds);

                $likeNum = 0;
                $commentNum = 0;
                foreach ($albumReplyData as $ar) {
                    if ($ar['Type'] == 'COMMENT') {
                        $commentNum++;
                    } elseif ($ar['Type'] == 'LIKE') {
                        $likeNum++;
                    }
                }
                $operactionData['AlbumRepliesNum'] = count($albumReplyData);
                $operactionData['AlbumRepliesData'] = [
                    'LikeNum' => $likeNum,
                    'CommentNum' => $commentNum
                ];

            } else {
                $operactionData['AlbumRepliesNum'] = 0;
                $operactionData['AlbumRepliesData'] = [
                    'LikeNum' => 0,
                    'CommentNum' => 0
                ];
            }


            $this->showJson(1, '用户数据', $operactionData);

        } else {
            $this->showJson(0, '无管理的微信号信息');
        }
    }

}