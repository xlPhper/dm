<?php
/**
 * Created by PhpStorm.
 * User: jakins
 * Date: 2018/11/21
 * Time: 10:06
 * 统计相关
 */
require_once APPLICATION_PATH . '/controllers/Open/OpenBase.php';

class Open_StatController extends OpenBase
{
    /**
     * 客户数据
     */
    public function customersAction()
    {
        try{
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


            if ($this->adminWxIds) {
                //获取微信账号
                $weixinWxs = Model_Weixin::getInstance()->getWeixinAccount($this->adminWxIds);
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

                // 好友申请数
                $friendApplyNum = Model_Weixin_FriendApply::getInstance()->getApplyNumByDate($this->adminWxIds, [$day, $yesterDay, $lastWeek, $lastMonth]);
                $oneFriendApplyNum = empty($friendApplyNum[$day])? 0 : $friendApplyNum[$day];
                $towFriendApplyNum = empty($friendApplyNum[$yesterDay])? 0 : $friendApplyNum[$yesterDay];
                $weekFriendApplyNum = empty($friendApplyNum[$lastWeek]) ? 0 : $friendApplyNum[$lastWeek];
                $monthFriendApplyNum = empty($friendApplyNum[$lastMonth]) ? 0 : $friendApplyNum[$lastMonth];

                // 好友通过数
                $friendAgreeNum = Model_Weixin_FriendApply::getInstance()->getAgreeNumByDate($this->adminWxIds, [$day, $yesterDay, $lastWeek, $lastMonth]);
                $oneFriendAgreeNum = empty($friendAgreeNum[$day])? 0 : $friendAgreeNum[$day];
                $towFriendAgreeNum = empty($friendAgreeNum[$yesterDay])? 0 : $friendAgreeNum[$yesterDay];
                $weekFriendAgreeNum = empty($friendAgreeNum[$lastWeek]) ? 0 : $friendAgreeNum[$lastWeek];
                $monthFriendAgreeNum = empty($friendAgreeNum[$lastMonth]) ? 0 : $friendAgreeNum[$lastMonth];

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

                // 好友申请
                $customerData['FriendApplyNum'] = $oneFriendApplyNum;
                $customerData['FriendApplyNumCompare'] = $oneFriendApplyNum - $towFriendApplyNum;
                $customerData['FriendApplyNumData'] = [
                    'Day' => $towFriendApplyNum == 0 ? 00.00 : number_format(($oneFriendApplyNum - $towFriendApplyNum) / $towFriendApplyNum, 2, '.', ''),
                    'Week' => $weekFriendApplyNum == 0 ? 00.00 : number_format(($oneFriendApplyNum - $weekFriendApplyNum) / $weekFriendApplyNum, 2, '.', ''),
                    'Month' => $monthFriendApplyNum == 0 ? 00.00 : number_format(($oneFriendApplyNum - $monthFriendApplyNum) / $monthFriendApplyNum, 2, '.', '')
                ];

                // 好友通过
                $customerData['FriendAgreeNum'] = $oneFriendAgreeNum;
                $customerData['FriendAgreeNumCompare'] = $oneFriendAgreeNum - $towFriendAgreeNum;
                $customerData['FriendAgreeNumNumData'] = [
                    'Day' => $towFriendAgreeNum == 0 ? 00.00 : number_format(($oneFriendAgreeNum - $towFriendAgreeNum) / $towFriendAgreeNum, 2, '.', ''),
                    'Week' => $weekFriendAgreeNum == 0 ? 00.00 : number_format(($oneFriendAgreeNum - $weekFriendAgreeNum) / $weekFriendAgreeNum, 2, '.', ''),
                    'Month' => $monthFriendAgreeNum == 0 ? 00.00 : number_format(($oneFriendAgreeNum - $monthFriendAgreeNum) / $monthFriendAgreeNum, 2, '.', '')
                ];

                $this->showJson(1, '用户数据', $customerData);

            } else {
                $this->showJson(0, '无管理的微信号信息');
            }
        }catch(Exception $e){
            $this->showJson(0, '抛出异常:'.$e->getMessage());
        }
    }

    /**
     * 客户详情数据
     */
    public function customerDetailAction(){
        try{
            $column = trim($this->_getParam('Column', 'CustomerNum'));
            $unit = intval($this->_getParam('Unit', 2)); //1:30分钟,2:1小时,3:1天
            $startTime = trim($this->_getParam('StartTime', date('Y-m-d 00:00:00')));
            $endTime = trim($this->_getParam('EndTime', date('Y-m-d 00:00:00', strtotime("+1 day"))));
            $comStartTime = trim($this->_getParam('ComStartTime', ''));  //对比起始时间
            $comEndTime = trim($this->_getParam('ComEndTime', '')); //对比结束时间
            $export = intval($this->_getParam('Export', 0)); //导出 0不导出,1导出
            if(!strtotime($startTime) || !strtotime($endTime)){
                $this->showJson(0, '时间格式有误');
            }
            if($endTime < $startTime){
                $this->showJson(0, '截止时间不可小于起始时间');
            }
            if($comStartTime && $comEndTime && (!strtotime($comStartTime) || !strtotime($comEndTime))){
                $this->showJson(0, '对比时间格式有误');
            }
            $data = [];
            $res = [];
            if($this->adminWxIds) {
                switch ($unit){
                    case '1':
                        if(date('Y-m-d', strtotime($startTime)) < date('Y-m-d', strtotime('-2 days', strtotime(date('Y-m-d', strtotime($endTime)))))){
                            $this->showJson(0, '时间粒度选择30分钟时，支持选择最长时间范围为3天');
                        }
                        if($comStartTime && $comEndTime && date('Y-m-d', strtotime($comStartTime)) < date('Y-m-d', strtotime('-2 days', strtotime(date('Y-m-d', strtotime($comEndTime)))))){
                            $this->showJson(0, '对比时间粒度选择30分钟时，支持选择最长时间范围为3天');
                        }
                        $start = date('Y-m-d H:00', strtotime($startTime));
                        $end = date('Y-m-d H:00', strtotime($endTime));
                        if($comStartTime && $comEndTime && !$export){
                            $comStart = date('Y-m-d H:00', strtotime($comStartTime));
                            $comEnd = date('Y-m-d H:00', strtotime($comEndTime));
                        }
                        if($export){
                            $exportTimeString = '时间';
                        }
                        $dateFormat = 'Y-m-d H:i';
                        $addUnit = '+30 minutes';
                        break;
                    case '3':
                        if(date('Y-m-d', strtotime($startTime)) < date('Y-m-d', strtotime('-1 month', strtotime(date('Y-m-d', strtotime($endTime)))))){
                            $this->showJson(0, '时间粒度为1天时，支持选择最长时间范围为1个月');
                        }
                        if($comStartTime && $comEndTime && date('Y-m-d', strtotime($comStartTime)) < date('Y-m-d', strtotime('-1 month', strtotime(date('Y-m-d', strtotime($comEndTime)))))){
                            $this->showJson(0, '对比时间粒度为1天时，支持选择最长时间范围为1个月');
                        }
                        $start = date('Y-m-d', strtotime($startTime));
                        $end = date('Y-m-d', strtotime($endTime));
                        if($comStartTime && $comEndTime && !$export){
                            $comStart = date('Y-m-d', strtotime($comStartTime));
                            $comEnd = date('Y-m-d', strtotime($comEndTime));
                        }
                        if($export){
                            $exportTimeString = '日期';
                        }
                        $dateFormat = 'Y-m-d';
                        $addUnit = '+1 day';
                        break;
                    case '2':
                    default:
                        if(date('Y-m-d', strtotime($startTime)) < date('Y-m-d', strtotime('-6 days', strtotime(date('Y-m-d', strtotime($endTime)))))){
                            $this->showJson(0, '时间粒度为1小时时，支持选择最长时间范围为7天');
                        }
                        if($comStartTime && $comEndTime && date('Y-m-d', strtotime($comStartTime)) < date('Y-m-d', strtotime('-6 days', strtotime(date('Y-m-d', strtotime($comEndTime)))))){
                            $this->showJson(0, '对比时间粒度为1小时时，支持选择最长时间范围为7天');
                        }
                        $start = date('Y-m-d H:00', strtotime($startTime));
                        $end = date('Y-m-d H:00', strtotime($endTime));
                        if($comStartTime && $comEndTime && !$export){
                            $comStart = date('Y-m-d H:00', strtotime($comStartTime));
                            $comEnd = date('Y-m-d H:00', strtotime($comEndTime));
                        }
                    if($export){
                        $exportTimeString = '时间';
                    }
                        $addUnit = '+1 hour';
                        $dateFormat = 'Y-m-d H:i';
                        break;
                }
                switch ($column){
                    case 'CustomerNum': //客户总数
                        $data = Model_Weixin_Friend::getInstance()->getAllFriendNumStat($this->adminWxIds, date('Y-m-d H:00:00', strtotime($startTime)), date('Y-m-d H:00:00', strtotime($endTime)), $unit);
                        if($comStartTime && $comEndTime && !$export){
                            $compareData =Model_Weixin_Friend::getInstance()->getAllFriendNumStat($this->adminWxIds, date('Y-m-d H:00:00', strtotime($comStartTime)), date('Y-m-d H:00:00', strtotime($comEndTime)), $unit);
                        }
                        break;
                    case 'NewCustomerNum': //客户新增数
                        $data = Model_Weixin_Friend::getInstance()->getAllFriendNumStat($this->adminWxIds, date('Y-m-d H:00:00', strtotime($startTime)), date('Y-m-d H:00:00', strtotime($endTime)), $unit, 2);
                        if($comStartTime && $comEndTime && !$export){
                            $compareData =Model_Weixin_Friend::getInstance()->getAllFriendNumStat($this->adminWxIds, date('Y-m-d H:00:00', strtotime($comStartTime)), date('Y-m-d H:00:00', strtotime($comEndTime)), $unit, 2);
                        }
                        break;
                    case 'DelCustomerNum': //客户流失数
                        $data = Model_Weixin_Friend::getInstance()->getStatData($this->adminWxIds, date('Y-m-d H:00:00', strtotime($startTime)), date('Y-m-d H:00:00', strtotime($endTime)), $unit, 2);
                        if($comStartTime && $comEndTime && !$export){
                            $compareData = Model_Weixin_Friend::getInstance()->getStatData($this->adminWxIds, date('Y-m-d H:00:00', strtotime($comStartTime)), date('Y-m-d H:00:00', strtotime($comEndTime)), $unit, 2);
                        }
                        break;
                    case 'ActiveCustomerNum': //客户活跃数
                        //获取微信账号
                        $weixinWxs = Model_Weixin::getInstance()->getWeixinAccount($this->adminWxIds);
                        $data = Model_Message::getInstance()->getActiveStatData($weixinWxs, date('Y-m-d H:00:00', strtotime($startTime)), date('Y-m-d H:00:00', strtotime($endTime)), $unit);
                        if($comStartTime && $comEndTime && !$export){
                            $compareData = Model_Message::getInstance()->getActiveStatData($weixinWxs, date('Y-m-d H:00:00', strtotime($comStartTime)), date('Y-m-d H:00:00', strtotime($comEndTime)), $unit);
                        }
                        break;
                    case 'FriendApplyNum': //客户申请数
                        $data = Model_Weixin_FriendApply::getInstance()->getStatData($this->adminWxIds, date('Y-m-d H:00:00', strtotime($startTime)), date('Y-m-d H:00:00', strtotime($endTime)), $unit, 1);
                        if($comStartTime && $comEndTime && !$export){
                            $compareData = Model_Weixin_FriendApply::getInstance()->getStatData($this->adminWxIds, date('Y-m-d H:00:00', strtotime($comStartTime)), date('Y-m-d H:00:00', strtotime($comEndTime)), $unit, 1);
                        }
                        break;
                    case 'FriendAgreeNum': //客户通过申请数
                        $data = Model_Weixin_FriendApply::getInstance()->getStatData($this->adminWxIds, date('Y-m-d H:00:00', strtotime($startTime)), date('Y-m-d H:00:00', strtotime($endTime)), $unit, 2);
                        if($comStartTime && $comEndTime && !$export){
                            $compareData = Model_Weixin_FriendApply::getInstance()->getStatData($this->adminWxIds, date('Y-m-d H:00:00', strtotime($comStartTime)), date('Y-m-d H:00:00', strtotime($comEndTime)), $unit, 2);
                        }
                        break;
                    default:
                        $this->showJson(0, '无此数据详情:'.$column);
                        break;
                }
                //补全数据
                $timeData = [];
                do{
                    if(isset($data[$start])){
                        $timeData[] = ['TimeString' => $start, 'Num' => $data[$start]];
                    }else{
                        $timeData[] = ['TimeString' => $start, 'Num' => 0];
                    }
                    $start = date($dateFormat, strtotime($addUnit, strtotime($start)));
                }while($start <= $end);
                $res[] = [
                    'Name' => $column,
                    'TimeData' => $timeData
                ];
                if(!$export && $comStartTime && $comEndTime){
                    //补全对比时间的数据
                    $timeComData = [];
                    do{
                        if(isset($compareData[$comStart])){
                            $timeComData[] = ['TimeString' => $comStart, 'Num' => $compareData[$comStart]];
                        }else{
                            $timeComData[] = ['TimeString' => $comStart, 'Num' => 0];
                        }
                        $comStart = date($dateFormat, strtotime($addUnit, strtotime($comStart)));
                    }while($comStart <= $comEnd);
                    $res[] = [
                        'Name' => $column.'Compare',
                        'TimeData' => $timeComData
                    ];
                }
            }
            if($export){
                $firstRow = [
                    "TimeString" => $exportTimeString,
                ];
                switch ($column){
                    case 'CustomerNum': //客户总数
                        $firstRow['Num'] = "客户总数";
                        break;
                    case 'NewCustomerNum': //客户新增数
                        $firstRow['Num'] = "客户新增数";
                        break;
                    case 'DelCustomerNum': //客户流失数
                        $firstRow['Num'] = "客户流失数";
                        break;
                    case 'ActiveCustomerNum': //客户活跃数
                        $firstRow['Num'] = "客户活跃数";
                        break;
                    case 'FriendApplyNum': //客户申请数
                        $firstRow['Num'] = "客户申请数";
                        break;
                    case 'FriendAgreeNum': //客户通过申请数
                        $firstRow['Num'] = "申请通过数";
                        break;
                    default:
                        break;
                }
                array_unshift($timeData, $firstRow);
                $excel = new DM_ExcelExport();
                $excel->setTemplateFile(APPLICATION_PATH . "/data/excel/default.xls")
                    ->setFirstRow(1)
                    ->setData($timeData)->export();
            }else{
                $this->showJson(1, '客户详情数据', $res);
            }
        }catch(Exception $e){
            $this->showJson(0, '抛出异常:'.$e->getMessage());
        }
    }

    /**
     * 自定义导出报表
     */
    public function defineExportCustomersAction(){
        try{
            $weixinIDs = array_unique(array_filter(explode(',', trim($this->_getParam('WeixinIDs', '')))));//勾选特定几个微信ID
            $columns = array_unique(array_filter(explode(',', trim($this->_getParam('Columns', '')))));//勾选特定几个项目
            $startTime = trim($this->_getParam('StartTime'));
            $endTime = trim($this->_getParam('EndTime'));
            if($startTime === '' || $endTime === '' || !strtotime($startTime) || !strtotime($endTime)){
                $this->showJson(0, '时间格式有误');
            }
            if(date('Y-m-d', strtotime($startTime)) < date('Y-m-d', strtotime('-1 month', strtotime(date('Y-m-d', strtotime($endTime)))))){
                $this->showJson(0, '支持选择最长时间范围为1个月');
            }
            if(empty($weixinIDs)){
                $this->showJson(0,'请勾选微信设备');
            }
            if(empty($columns)){
                $this->showJson(0,'请勾选打印项目');
            }
            $firstRow = [
                "SerialNum"=>"设备ID","TimeString" => "日期"
            ];
            $total = [
                'SerialNum' => '汇总',
                'TimeString' => ''
            ];
            foreach ($columns as $column){
                $total[$column] = 0;
                switch ($column){
                    case 'CustomerNum': //客户总数
                        $firstRow['CustomerNum'] = "客户总数";
                        break;
                    case 'NewCustomerNum': //客户新增数
                        $firstRow['NewCustomerNum'] = "客户新增数";
                        break;
                    case 'DelCustomerNum': //客户流失数
                        $firstRow['DelCustomerNum'] = "客户流失数";
                        break;
                    case 'ActiveCustomerNum': //客户活跃数
                        $firstRow['ActiveCustomerNum'] = "客户活跃数";
                        break;
                    case 'FriendApplyNum': //客户申请数
                        $firstRow['FriendApplyNum'] = "客户申请数";
                        break;
                    case 'FriendAgreeNum': //客户通过申请数
                        $firstRow['FriendAgreeNum'] = "申请通过数";
                        break;
                    default:
                        break;
                }

            }
            $data = [];
            if($this->adminWxIds){
                foreach ($weixinIDs as $weixinID){
                    $numData = []; //查询数据
                    $serialNum = Model_Weixin::getInstance()->findSerialNum($weixinID);
                    foreach ($columns as $column){
                        switch ($column){
                            case 'CustomerNum': //客户总数
                                $numData[$column] = Model_Weixin_Friend::getInstance()->getAllFriendNumStat([$weixinID], date('Y-m-d H:00:00', strtotime($startTime)), date('Y-m-d H:00:00', strtotime($endTime)), 3);
                                break;
                            case 'NewCustomerNum': //客户新增数
                                $numData[$column] = Model_Weixin_Friend::getInstance()->getAllFriendNumStat([$weixinID], date('Y-m-d H:00:00', strtotime($startTime)), date('Y-m-d H:00:00', strtotime($endTime)), 3, 2);
                                break;
                            case 'DelCustomerNum': //客户流失数
                                $numData[$column] = Model_Weixin_Friend::getInstance()->getStatData([$weixinID], date('Y-m-d H:00:00', strtotime($startTime)), date('Y-m-d H:00:00', strtotime($endTime)), 3, 2);
                                break;
                            case 'ActiveCustomerNum': //客户活跃数
                                //获取微信账号
                                $weixinWxs = Model_Weixin::getInstance()->getWeixinAccount([$weixinID]);
                                $numData[$column] = Model_Message::getInstance()->getActiveStatData($weixinWxs, date('Y-m-d H:00:00', strtotime($startTime)), date('Y-m-d H:00:00', strtotime($endTime)), 3);
                                break;
                            case 'FriendApplyNum': //客户申请数
                                $numData[$column] = Model_Weixin_FriendApply::getInstance()->getStatData([$weixinID], date('Y-m-d H:00:00', strtotime($startTime)), date('Y-m-d H:00:00', strtotime($endTime)), 3, 1);
                                break;
                            case 'FriendAgreeNum': //客户通过申请数
                                $numData[$column] = Model_Weixin_FriendApply::getInstance()->getStatData([$weixinID], date('Y-m-d H:00:00', strtotime($startTime)), date('Y-m-d H:00:00', strtotime($endTime)), 3, 2);
                                break;
                            default:
                                break;
                        }
                    }
                    // 补全数据
                    $start = date('Y-m-d', strtotime($startTime));
                    $i = 0;
                    do{
                        $timeData = [
                            'SerialNum' => $i == 0? ($serialNum?$serialNum['SerialNum']:"无设备($weixinID)") : '',
                            'TimeString' => $start
                        ];
                        foreach ($columns as $column){
                            if(isset($numData[$column][$start])){
                                $timeData[$column] = $numData[$column][$start];
                                $total[$column] += $numData[$column][$start];
                            }else{
                                $timeData[$column] = 0;
                            }
                        }
                        $data[] = $timeData;
                        $start = date('Y-m-d', strtotime('+1 day', strtotime($start)));
                        $i++;
                    }while($start <= date('Y-m-d', strtotime($endTime)));
                }
                $data[] = $total;
            }

            array_unshift($data, $firstRow);
            $excel = new DM_ExcelExport();
            $excel->setTemplateFile(APPLICATION_PATH . "/data/excel/default.xls")
                ->setFirstRow(1)
                ->setData($data)->export();
        }catch(Exception $e){
            $this->showJson(0, '抛出异常:'.$e->getMessage());
        }
    }

    /**
     * 销售统计汇总
     */
    public function statAction(){
        try{
            $newFanDay = intval($this->_getParam('NewFanDay', 1));
            if(!in_array($newFanDay, [1,2])){
                $this->showJson(0, '新粉时段只可是1天或2天');
            }
            $startTime = trim($this->_getParam('StartTime', date('Y-m-d')));
            $endTime = trim($this->_getParam('EndTime', date('Y-m-d')));
            if($startTime === '' || $endTime === '' || !strtotime($startTime) || !strtotime($endTime)){
                $this->showJson(0, '时间筛选格式有误');
            }
            $option = intval($this->_getParam('Option', 1)); //1设备数据,2部门数据,3销售人员数据
            $export = intval($this->_getParam('Export', 0)); //导出
            $devices = trim($this->_getParam('Devices', '')); //设备
            if(!$this->admin['CompanyId']){
                $this->showJson(0, '当前登录管理员不存在公司');
            }
            $company = (new Model_Company())->fetchRow(['CompanyID = ?'=>$this->admin['CompanyId']]);
            if(!$company){
                $this->showJson(0, '未找到公司,ID:'.$this->admin['CompanyId']);
            }
            $wxArr = array_unique(explode(',',$company['WeixinIds']));
            if(empty($wxArr)){
                $this->showJson(0, '当前公司未分配微信');
            }
            // 查询微信及设备信息
            $wSelect = Model_weixin::getInstance()->fromSlaveDB()->select()->from('weixins as w', ['WeixinID', 'YyAdminID'])->setIntegrityCheck(false)
                ->joinLeft('devices as d', 'd.DeviceID = w.DeviceID', ['SerialNum'])->where('w.WeixinID IN (?)', $wxArr);
            if($devices !== ''){
                $SerialNumS = str_replace('，',',',$devices);
                $SerialNum  = explode(',', $SerialNumS);
                $SerialNum  = array_filter($SerialNum);
                $tmpSerialNum = [];
                foreach ($SerialNum as $s) {
                    $s = trim($s);
                    if (!empty($s)) {
                        $tmpSerialNum[] = "d.SerialNum like '%" . $s . "'";
                    }
                }
                if (!empty($tmpSerialNum)) {
                    $wSelect->where(implode(' or ', $tmpSerialNum));
                }
            }
            $weixins = $wSelect->query()->fetchAll();
            if(empty($weixins)){
                $this->showJson(0, '未找到微信及设备信息');
            }
            $departments = Model_Department::getInstance()->getAllList($this->admin['CompanyId'], '');
            $admins = Model_Role_Admin::getInstance()->fromSlaveDB()->select()->from('admins', ['AdminID', 'Username', 'DepartmentID'])->where('CompanyId = ?', $this->admin['CompanyId'])->query()->fetchAll();
            $adminInfos = []; //管理员信息 AdminID => ['AdminID' => '','Username' => '', 'DepartmentID' => '', 'DepartmentName' => '', 'WeixinIDs' => '管理员所有的微信']
            $departmentInfos = []; //部门信息 DepartmentID => ['DepartmentID' => '','Name' => '','WeixinIDs' => '部门下的微信','AdminIDs' => '部门下的管理员']
            foreach ($departments as $de){
                $de['WeixinIDs'] = [];
                $de['AdminIDs'] = [];
                $departmentInfos[$de['DepartmentID']] = $de;
            }
            foreach ($admins as $a){
                $a['WeixinIDs'] = [];
                $a['DepartmentName'] = '';
                if($a['DepartmentID'] && isset($departmentInfos[$a['DepartmentID']])){
                    $a['DepartmentName'] = $departmentInfos[$a['DepartmentID']]['Name'];
                    $departmentInfos[$a['DepartmentID']]['AdminIDs'][] = $a['AdminID'];
                }
                $adminInfos[$a['AdminID']] = $a;
            }

            $weixinInfos = []; //微信设备信息 WeixinID => ['WeixinID' => '','YyAdminID' => '','AdminName' => '管理员', 'DepartmentName' => '部门', 'SerialNum' => '设备']
            foreach ($weixins as $w){
                $w['AdminName'] = '';
                $w['DepartmentName'] = '';
                if($w['YyAdminID']){
                    $adminNames = [];
                    $departmentNames = [];
                    foreach (explode(',', $w['YyAdminID']) as $adminID){
                        if(isset($adminInfos[$adminID])){
                            $adminNames[] = $adminInfos[$adminID]['Username'];
                            $adminInfos[$adminID]['WeixinIDs'][] = $w['WeixinID'];
                            if($adminInfos[$adminID]['DepartmentName']){
                                $departmentNames[] = $adminInfos[$adminID]['DepartmentName'];
                                $departmentInfos[$adminInfos[$adminID]['DepartmentID']]['WeixinIDs'][] = $w['WeixinID'];
                            }
                        }
                    }
                    if(!empty($adminNames)){
                        $w['AdminName'] = implode(',', $adminNames);
                    }
                    if(!empty($departmentNames)){
                        $w['DepartmentName'] = implode(',', $departmentNames);
                    }
                }
                $weixinInfos[$w['WeixinID']] = $w;
            }

            $friendApplyInfo = Model_Weixin_FriendApply::getInstance()->getNumGroupByWeixin($wxArr, date('Y-m-d 00:00:00', strtotime($startTime)), date('Y-m-d 23:59:59', strtotime($endTime)));

            //根据新粉时段计算新粉订单的统计规则
            if($newFanDay == 2){
                $newFanOrderStr = 'o.BuyerAddTime <= o.OrderDate and o.BuyerAddTime >= DATE_SUB(o.OrderDate,interval 1 day)';
                $newFanDate = date('Y-m-d', strtotime('-1 day', strtotime($startTime)));
            }else{
                $newFanOrderStr = 'o.BuyerAddTime = o.OrderDate';
                $newFanDate = date('Y-m-d', strtotime($startTime));
            }
            $data = [];
            $tmp = [
                'FriendApplyNum' => '0', //好友申请数
                'FriendNum' => '0', //总粉数
                'NewCustomRate' => '0.00', //新粉转化率
                'NewFriendNum' => '0', //新粉总数
                'NewOrderAvg' => '0',  //新粉客单价
                'NewOrderRate' => '0.00',  //新粉单粉收益
                'NewTotalAmount' => '0',  //新粉销售额
                'NewTotalCount' => '0',  //新粉订单数
                'OldFriendNum' => '0',  //老粉总数
                'OldOrderAvg' => '0',  //老粉客单价
                'OldOrderRate' => '0.00', //老粉单粉收益
                'OldTotalAmount' => '0',  //老粉销售额
                'OldTotalCount' => '0',  //老粉订单数
                'OrderAvg' => '0',  //客单价
                'TotalAmount' => '0',  //总销售额
                'TotalCount' => '0'  //总订单数
            ];
            if($option == 1){
                $select = Model_Orders::getInstance()->fromSlaveDB()->select()->setIntegrityCheck(false);
                $select->from("orders as o",["sum(TotalAmount) as TotalAmount","count(o.OrderID) as TotalCount","o.WeixinID"]);
                $select->columns(new Zend_Db_Expr("sum(if($newFanOrderStr,o.TotalAmount,0)) as NewTotalAmount"));
                $select->columns(new Zend_Db_Expr("sum(if($newFanOrderStr,1,0)) as NewTotalCount"));
                $select->columns(new Zend_Db_Expr("count(distinct if($newFanOrderStr,Buyer,null)) as NewCustomCount"));
                $select->where("o.Status != 9");
                $select->where('o.WeixinID IN (?)', $wxArr);
                $select->where("OrderDate >= ?", date('Y-m-d', strtotime($startTime)));
                $select->where("OrderDate <= ?", date('Y-m-d', strtotime($endTime)));
                $select->group(["o.WeixinID"]);
                $orders = $select->query()->fetchAll();
                $orderInfos = [];
                if(!empty($orders)){
                    foreach ($orders as $r) {
                        $r["OldTotalAmount"] =  number_format($r["TotalAmount"] - $r["NewTotalAmount"],2);
                        $r["OldTotalCount"] = $r["TotalCount"] - $r["NewTotalCount"];
                        $r['OrderAvg'] = $r["TotalCount"]==0?"0.00":number_format($r["TotalAmount"]/$r["TotalCount"],2);
                        $r['OldOrderAvg'] = $r["OldTotalCount"]==0?"0.00":number_format($r["OldTotalAmount"]/$r["OldTotalCount"],2);
                        $r['NewOrderAvg'] = $r["NewTotalCount"]==0?"0.00":number_format($r["NewTotalAmount"]/$r["NewTotalCount"],2);
                        $orderInfos[$r['WeixinID']] = $r;
                    }
                }
                foreach ($weixinInfos as $weixinID => $weixin){
                    if(!$weixin['SerialNum']){
                        continue;
                    }
                    $res = $tmp;
                    $res['DepartmentName'] = $weixin['DepartmentName'];
                    $res['AdminName'] = $weixin['AdminName'];
                    $res['SerialNum'] = $weixin['SerialNum'];
                    $res['WeixinID'] = $weixinID;
                    $friendNum = Model_Stat::getInstance()->select()->from('stats', ['FriendNum'])->where('WeixinID = ?', $weixinID)->where('Date = ?', date('Y-m-d', strtotime($endTime)))
                        ->query()->fetch();
                    $newAddFriendNum = Model_Stat::getInstance()->select()->from('stats', ['sum(AddFriendNum) as Num'])->where('WeixinID = ?', $weixinID)->where('Date >= ?', $newFanDate)->where('Date <= ?', date('Y-m-d', strtotime($endTime)))
                        ->query()->fetch();
                    if(!empty($friendNum['FriendNum'])){

                        $res['FriendNum'] = $friendNum['FriendNum'];
                    }
                    if(!empty($newAddFriendNum['Num'])){
                        $res['NewFriendNum'] = $newAddFriendNum['Num'];
                    }
                    if(isset($orderInfos[$weixinID])){
                        $res = array_merge($res, $orderInfos[$weixinID]);
                    }
                    if(isset($friendApplyInfo[$weixinID])){
                        $res['FriendApplyNum'] = $friendApplyInfo[$weixinID];
                    }
                    if(!empty($res['NewCustomCount']) && $res['NewCustomCount'] > $res['NewFriendNum']){
                        $res['NewFriendNum'] = $res['NewCustomCount'];
                    }
                    if($res['NewFriendNum']){
                        $res['NewOrderRate'] = number_format($res["NewTotalAmount"]/$res["NewFriendNum"],2);
                        $res['NewCustomRate'] = number_format($res["NewTotalCount"]/$res["NewFriendNum"],2);
                    }
                    if($res['FriendNum'] && $res['FriendNum'] > $res['NewFriendNum']){
                        $res['OldFriendNum'] = $res['FriendNum'] - $res['NewFriendNum'];
                        $res['OldOrderRate'] = number_format($res["OldTotalAmount"]/$res["OldFriendNum"],2);
                    }
                    unset($res['NewCustomCount']);
                    $data[] = $res;
                }
            }else if($option == 2){
                foreach ($departmentInfos as $department){
                    $res = $tmp;
                    $res['DepartmentName'] = $department['Name'];
                    $res['WeixinNum'] = '0';
                    if(!empty($department['WeixinIDs'])) {
                        $res['WeixinNum'] = count(array_unique($department['WeixinIDs']));
                        foreach (array_unique($department['WeixinIDs']) as $wID) {
                            if (isset($friendApplyInfo[$wID])) {
                                $res['FriendApplyNum'] = $res['FriendApplyNum'] + $friendApplyInfo[$wID];
                            }
                        }
                        $friendNum = Model_Stat::getInstance()->select()->from('stats', ['Sum(FriendNum) as FriendNum'])->where('WeixinID IN (?)', $department['WeixinIDs'])->where('Date = ?', date('Y-m-d', strtotime($endTime)))
                            ->query()->fetch();
                        $newAddFriendNum = Model_Stat::getInstance()->select()->from('stats', ['sum(AddFriendNum) as Num'])->where('WeixinID IN (?)', $department['WeixinIDs'])->where('Date >= ?', $newFanDate)->where('Date <= ?', date('Y-m-d', strtotime($endTime)))
                            ->query()->fetch();
                        if(!empty($friendNum['FriendNum'])){
                            $res['FriendNum'] = $friendNum['FriendNum'];
                        }
                        if(!empty($newAddFriendNum['Num'])){
                            $res['NewFriendNum'] = $newAddFriendNum['Num'];
                        }
                    }
                    if(!empty($department['AdminIDs'])){
                        $select = Model_Orders::getInstance()->fromSlaveDB()->select()->setIntegrityCheck(false);
                        $select->from("orders as o",[new Zend_Db_Expr("IFNULL(sum(o.TotalAmount), 0) as TotalAmount"),"count(o.OrderID) as TotalCount"]);
                        $select->columns(new Zend_Db_Expr("IFNULL(sum(if($newFanOrderStr,o.TotalAmount,0)), 0) as NewTotalAmount"));
                        $select->columns(new Zend_Db_Expr("IFNULL(sum(if($newFanOrderStr,1,0)), 0) as NewTotalCount"));
                        $select->columns(new Zend_Db_Expr("count(distinct if($newFanOrderStr,Buyer,null)) as NewCustomCount"));
                        $select->where("o.Status != 9");
                        $select->where('o.AdminID IN (?)', array_unique($department['AdminIDs']));
                        $select->where("OrderDate >= ?", date('Y-m-d', strtotime($startTime)));
                        $select->where("OrderDate <= ?", date('Y-m-d', strtotime($endTime)));
                        $r = $select->query()->fetch();
                        if($r){
                            $r["OldTotalAmount"] = number_format($r["TotalAmount"] - $r["NewTotalAmount"],2);
                            $r["OldTotalCount"] = $r["TotalCount"] - $r["NewTotalCount"];
                            $r['OrderAvg'] = $r["TotalCount"]==0?"0.00":number_format($r["TotalAmount"]/$r["TotalCount"],2);
                            $r['OldOrderAvg'] = $r["OldTotalCount"]==0?"0.00":number_format($r["OldTotalAmount"]/$r["OldTotalCount"],2);
                            $r['NewOrderAvg'] = $r["NewTotalCount"]==0?"0.00":number_format($r["NewTotalAmount"]/$r["NewTotalCount"],2);
                            $res = array_merge($res, $r);
                        }
                    }
                    if(!empty($res['NewCustomCount']) && $res['NewCustomCount'] > $res['NewFriendNum']){
                        $res['NewFriendNum'] = $res['NewCustomCount'];
                    }
                    if($res['NewFriendNum']){
                        $res['NewOrderRate'] = number_format($res["NewTotalAmount"]/$res["NewFriendNum"],2);
                        $res['NewCustomRate'] = number_format($res["NewTotalCount"]/$res["NewFriendNum"],2);
                    }
                    if($res['FriendNum'] && $res['FriendNum'] > $res['NewFriendNum']){
                        $res['OldFriendNum'] = $res['FriendNum'] - $res['NewFriendNum'];
                        $res['OldOrderRate'] = number_format($res["OldTotalAmount"]/$res["OldFriendNum"],2);
                    }
                    unset($res['NewCustomCount']);
                    $data[] = $res;
                }
            }else{
                foreach ($adminInfos as $admin){
                    $res = $tmp;
                    $res['AdminName'] = $admin['Username'];
                    $res['DepartmentName'] = $admin['DepartmentName'];
                    $res['WeixinNum'] = '0';
                    if(!empty($admin['WeixinIDs'])) {
                        $res['WeixinNum'] = count(array_unique($admin['WeixinIDs']));
                        foreach ($admin['WeixinIDs'] as $id) {
                            if (isset($friendApplyInfo[$id])) {
                                $res['FriendApplyNum'] = $res['FriendApplyNum'] + $friendApplyInfo[$id];
                            }
                        }
                        $friendNum = Model_Stat::getInstance()->select()->from('stats', ['Sum(FriendNum) as FriendNum'])->where('WeixinID IN (?)', array_unique($admin['WeixinIDs']))->where('Date = ?', date('Y-m-d', strtotime($endTime)))
                            ->query()->fetch();
                        $newAddFriendNum = Model_Stat::getInstance()->select()->from('stats', ['sum(AddFriendNum) as Num'])->where('WeixinID IN (?)', array_unique($admin['WeixinIDs']))->where('Date >= ?', $newFanDate)->where('Date <= ?', date('Y-m-d', strtotime($endTime)))
                            ->query()->fetch();
                        if(!empty($friendNum['FriendNum'])){
                            $res['FriendNum'] = $friendNum['FriendNum'];
                        }
                        if(!empty($newAddFriendNum['Num'])){
                            $res['NewFriendNum'] = $newAddFriendNum['Num'];
                        }
                    }
                    $select = Model_Orders::getInstance()->fromSlaveDB()->select()->setIntegrityCheck(false);
                    $select->from("orders as o",[new Zend_Db_Expr("IFNULL(sum(o.TotalAmount), 0) as TotalAmount"),"count(o.OrderID) as TotalCount"]);
                    $select->columns(new Zend_Db_Expr("IFNULL(sum(if($newFanOrderStr,o.TotalAmount,0)), 0) as NewTotalAmount"));
                    $select->columns(new Zend_Db_Expr("IFNULL(sum(if($newFanOrderStr,1,0)), 0) as NewTotalCount"));
                    $select->columns(new Zend_Db_Expr("count(distinct if($newFanOrderStr,Buyer,null)) as NewCustomCount"));
                    $select->where("o.Status != 9");
                    $select->where('o.AdminID = ?', $admin['AdminID']);
                    $select->where("OrderDate >= ?", date('Y-m-d', strtotime($startTime)));
                    $select->where("OrderDate <= ?", date('Y-m-d', strtotime($endTime)));
                    $r = $select->query()->fetch();
                    if($r){
                        $r["OldTotalAmount"] = number_format($r["TotalAmount"] - $r["NewTotalAmount"],2);
                        $r["OldTotalCount"] = $r["TotalCount"] - $r["NewTotalCount"];
                        $r['OrderAvg'] = $r["TotalCount"]==0?"0.00":number_format($r["TotalAmount"]/$r["TotalCount"],2);
                        $r['OldOrderAvg'] = $r["OldTotalCount"]==0?"0.00":number_format($r["OldTotalAmount"]/$r["OldTotalCount"],2);
                        $r['NewOrderAvg'] = $r["NewTotalCount"]==0?"0.00":number_format($r["NewTotalAmount"]/$r["NewTotalCount"],2);
                        $res = array_merge($res, $r);
                    }
                    if(!empty($res['NewCustomCount']) && $res['NewCustomCount'] > $res['NewFriendNum']){
                        $res['NewFriendNum'] = $res['NewCustomCount'];
                    }
                    if($res['NewFriendNum']){
                        $res['NewOrderRate'] = number_format($res["NewTotalAmount"]/$res["NewFriendNum"],2);
                        $res['NewCustomRate'] = number_format($res["NewTotalCount"]/$res["NewFriendNum"],2);
                    }
                    if($res['FriendNum'] && $res['FriendNum'] > $res['NewFriendNum']){
                        $res['OldFriendNum'] = $res['FriendNum'] - $res['NewFriendNum'];
                        $res['OldOrderRate'] = number_format($res["OldTotalAmount"]/$res["OldFriendNum"],2);
                    }
                    unset($res['NewCustomCount']);

                    $data[] = $res;
                }
            }
            if($export){
                $excel = new DM_ExcelExport();
                $excel->setTemplateFile(APPLICATION_PATH . "/data/excel/stats_all.xls")
                    ->setData($data)->export();
            }else{
                $this->showJson(1, '统计汇总', $data);
            }
        }catch(Exception $e){
            $this->showJson(0, '抛出异常:'.$e->getMessage());
        }
    }

    /**
     * 数据统计的订单数据
     */
    public function statOrderAction()
    {

        $searchType  = $this->_getParam('SearchType',1);
        $dataType = $this->_getParam('DateType',1);  // 1-自定义时间 2-近7天数据 3-近30天数据
        $startDate = $this->_getParam('StartDate',date('Y-m-d',strtotime('-6 day')));
        $endDate = $this->_getParam('EndDate',date('Y-m-d'));
        $export = $this->_getParam('Export',0); // 0-不打印 1-打印

        // 时
        switch ($dataType){
            case 1:
                $extent = date('z',strtotime($endDate)) - date('z',strtotime($startDate));
                if ($extent >30){
                    $this->showJson(self::STATUS_FAIL, '时区范围请选择在30天之内');
                }
                break;

            case 2:
                $startDate = date('Y-m-d',strtotime('-6 day'));
                $endDate = date('Y-m-d');
                break;

            case 3:
                $startDate = date('Y-m-d',strtotime('-29 day'));
                $endDate = date('Y-m-d');
                break;
        }
        $days = round((strtotime($endDate)-strtotime($startDate))/3600/24);


        $weixinFriendModel = Model_Weixin_Friend::getInstance();
        $orderModel = Model_Orders::getInstance();
        $weixinModel = Model_Weixin::getInstance();

        // 微信帐号
        $weixins = $weixinModel->getWeixinAccount($this->adminWxIds);

        // 总订单数据
        $orderData = $orderModel->getOrderStat($weixins,$startDate,$endDate);

        // 新增好友
        $addFriend = $weixinFriendModel->getAddFriendData($orderData['Buyer'],$startDate,$endDate);

        $customer = [];

        for ($i=0;$i<=$days;$i++){

            $date = date('Y-m-d',strtotime($startDate.'+'.$i.' day'));

            if (!array_key_exists($date,$customer)){
                $customer[$date] = [
                    'TotalCustomer' => 0,
                    'RegularCustomer' => 0,
                    'NewCustomer' => 0,
                ];
            }

        }

        foreach($orderData['Result'] as $order){
            $customer[$order['OrderDate']]['TotalCustomer']++;

            $key = $order['Seller'].'-'.$order['Buyer'];

            if (!empty($addFriend[$key]) && isset($addFriend[$key]) && $order['OrderDate'] == $addFriend['AddDate']){
                $customer[$order['OrderDate']]['NewCustomer']++;
            }else{
                $customer[$order['OrderDate']]['RegularCustomer']++;
            }

        }

        var_dump('ok');exit;
    }
}