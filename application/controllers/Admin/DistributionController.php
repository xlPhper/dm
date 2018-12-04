<?php
/**
 * Created by PhpStorm.
 * User: jakins
 * Date: 2018/10/10
 * Time: 15:33
 * 派单统计相关
 */
require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_DistributionController extends AdminBase
{
    /**
     * 派单列表
     */
    public function listAction()
    {
        $page = $this->_getParam('Page', 1);
        $pagesize = $this->_getParam('Pagesize', 100);
        $order_field = strtolower($this->_getParam('Order_Field', 'asc')); //排序
        $sort_field = $this->_getParam('Sort_Field', 'SendTime'); //排序字段
        $order_field = $order_field != 'asc'? 'desc':'asc';

        $channel = intval($this->_getParam("Channel", 0)); //渠道
        $sendTimeStart = $this->_getParam("SendTimeStart",""); //推送时间
        $sendTimeEnd = $this->_getParam("SendTimeEnd","");
        $title = trim($this->_getParam("Title", '')); //文案标题
        $platform = intval($this->_getParam("DisPlatform", 0)); //平台
        $cateID = intval($this->_getParam("CategoryID", 0)); //文章类型
        $fanNumStart = intval($this->_getParam("FanNumStart", 0)); //粉丝数
        $fanNumEnd = intval($this->_getParam("FanNumEnd", 0));
        $viewNumStart = intval($this->_getParam("ViewNumStart", 0)); //阅读数
        $viewNumEnd = intval($this->_getParam("ViewNumEnd", 0));
        $Export = (int)$this->_getParam("Export", 0); //导出

        $model = new Model_Distribution();
        $select = $model->fromSlaveDB()->select();
        if (!empty($channel)){
            $select->where("Channel = ?", $channel);
        }
        if (!empty($platform)){
            $select->where("Platform = ?", $platform);
        }
        if (!empty($cateID)){
            $select->where("ArticleCategoryID = ?", $cateID);
        }
        if ($title != ''){
            $select->where("ArticleTitle Like ?", '%'.$title.'%');
        }
        if (!empty($fanNumStart)){
            $select->where("FanNum >= ?", $fanNumStart);
        }
        if (!empty($fanNumEnd)){
            $select->where("FanNum <= ?", $fanNumEnd);
        }
        if (!empty($viewNumStart)){
            $select->where("ArticleViewNum >= ?", $viewNumStart);
        }
        if (!empty($viewNumEnd)){
            $select->where("ArticleViewNum <= ?", $viewNumEnd);
        }
        if ($sendTimeStart){
            $select->where("SendTime >= ?", $sendTimeStart.' 00:00:00');
        }
        if ($sendTimeEnd){
            $select->where("SendTime <= ?", $sendTimeEnd.' 23:59:59');
        }
        switch($sort_field){
            case 'ViewNumFriendPer':
                $sort_field = 'TotalFriendNum/ArticleViewNum';
                break;
            case 'ViewNumPer':
                $sort_field = 'ArticleViewNum/FanNum';
                break;
            case 'Cost':
                $sort_field = 'Price/TotalFriendNum';
                break;
            default:
                break;
        }
        $select->order(new Zend_Db_Expr("$sort_field $order_field"));
        $res = $model->getResult($select, $page, $pagesize);
        if($res['Results']){
            foreach ($res['Results'] as &$row){
                $row['ViewNumFriendPer'] = (empty($row['ArticleViewNum'])?0:bcdiv($row['TotalFriendNum'], $row['ArticleViewNum'], 3)*100).'%';
                $row['ViewNumPer'] = (empty($row['FanNum'])?0:bcdiv($row['ArticleViewNum'], $row['FanNum'], 3)*100).'%';
                $row['Cost'] = empty($row['TotalFriendNum'])?0:bcdiv($row['Price'], $row['TotalFriendNum'], 2);
            }
            $model->getFiled($res['Results'], "ArticleCategoryID","categories" ,"Name","ArticleCategoryName",'CategoryID' );
        }
        if($Export) {
            $data = $res["Results"];
            $excel = new DM_ExcelExport();
                $excel->setTemplateFile(APPLICATION_PATH . "/data/excel/distribution.xls")
                    ->setData($data)->export();
        }else{
            $this->showJson(1, '派单列表', $res);
        }
    }

    /**
     * 添加派单信息
     */
    public function addAction()
    {
        $data = trim($this->_getParam('data', ''));
        if(empty($data)){
            $this->showJson(0, '请填写数据');
        }
        $datas = json_decode($data, true);
        if (json_last_error() != JSON_ERROR_NONE || empty($datas)) {
            $this->showJson(0, '数据格式提交有误');
        }
        $sql = 'insert into distribution (AdminID,Distributer,Channel,Platform,FanNum,ObjectName,Devices,Price,ArticleCategoryID,ArticlePosition,ArticleTitle,ArticleUrl,SendTime,ArticleViewNum,WeixinIDs) values ';
        $sql_values = [];
        $adminID = $this->getLoginUserId();
        $deviceModel = new Model_Device();
        $unOnlineDevices = []; //未上线设备
        foreach ($datas as $row){
            if(!isset($row["Distributer"]) || trim($row['Distributer']) == ''){
                $this->showJson(0, '派单人不能为空');
            }
            if(!isset($row["Channel"]) || empty(intval($row['Channel']))){
                $this->showJson(0, '渠道不能为空');
            }
            if(!isset($row["Platform"]) || empty(intval($row['Platform'])) || !array_key_exists(intval($row['Platform']), Model_Distribution::$_platform)){
                $this->showJson(0, '派单平台有误');
            }
            if(!isset($row["ObjectName"]) || trim($row['ObjectName']) == ''){
                $this->showJson(0, '派单对象不能为空');
            }
            if(!isset($row["Devices"]) || trim($row['Devices']) == ''){
                $this->showJson(0, '个号设备编号不能为空');
            }
            if(!isset($row["Price"]) || empty(floatval($row['Price']))){
                $this->showJson(0, '派单价不能为空');
            }
            if(!isset($row["ArticleUrl"]) || trim($row['ArticleUrl']) == ''){
                $this->showJson(0, '文案链接不能为空');
            }
            if(!isset($row["SendTime"]) || !strtotime($row['SendTime'])){
                $this->showJson(0, '推送时间不能为空');
            }
            if(!isset($row['FanNum'])){
                $this->showJson(0, '总粉丝数未设置');
            }
            if(!isset($row['ArticleCategoryID'])){
                $this->showJson(0, '文案类型未设置');
            }
            if(!isset($row['ArticlePosition'])){
                $this->showJson(0, '文案位置未设置');
            }
            if(!isset($row['ArticleTitle'])){
                $this->showJson(0, '文案标题未设置');
            }
            if(!isset($row['ArticleViewNum'])){
                $this->showJson(0, '文案阅读数未设置');
            }
            //根据设备编码获取微信ID
            $onlineInfos = $deviceModel->getOnlineWxIDBySerialNums(explode(',', trim($row['Devices'])));
            if(isset($onlineInfos['UnOnlineSerialNums']) && !empty($onlineInfos['UnOnlineSerialNums'])){
                $unOnlineDevices = array_merge($unOnlineDevices, $onlineInfos['UnOnlineSerialNums']);
            }
            $sql_values[] = "('".$adminID."', '".trim($row['Distributer'])."', '".intval($row['Channel'])."', '".intval($row['Platform'])."', '".intval($row['FanNum'])."', '".trim($row['ObjectName'])."', '".trim($row['Devices'])."', '".floatval($row['Price'])."', '".intval($row['ArticleCategoryID'])."', '".trim($row['ArticlePosition'])."', '".trim($row['ArticleTitle'])."', '".trim($row['ArticleUrl'])."', '".trim($row['SendTime'])."', '".intval($row['ArticleViewNum'])."', '".implode(',', $onlineInfos['WeixinIDs'])."')";
        }
        if(!empty($sql_values)){
            $model = new Model_Distribution();
            $sql .= implode(",", $sql_values);
            $db = $model->getDb();
            $db->query($sql);
        }
        $this->showJson(1, '添加成功'.(!empty($unOnlineDevices)? (',请上线这些设备:'.implode(',', array_unique($unOnlineDevices))) : ''));
    }

    /**
     * 编辑派单信息
     */
    public function editAction()
    {
        $id = intval($this->_getParam('DistributionID', 0));
        if(empty($id)){
            $this->showJson(0, '请提交派单ID');
        }
        $model = new Model_Distribution();
        $res = $model->find($id)->current();
        if(!$res){
            $this->showJson(0, '未找到此派单信息,ID：'.$id);
        }
        $data = [];
        $data['Price'] = floatval($this->_getParam('Price', 0));
        if (empty($data['Price'])){
            $this->showJson(0, '派单价不能为0');
        }
        $data['FanNum'] = intval($this->_getParam('FanNum', 0));
        $data['ArticleUrl'] = trim($this->_getParam('ArticleUrl', ''));
        $viewNum = $this->_getParam('ArticleViewNum');
        if(isset($viewNum)){
            // 由于客户端抓取阅读数可能会被封号,给手填
            $data['ArticleViewNum'] = intval($viewNum);
        }
        if(in_array($res->Platform, [Model_Distribution::DISTRIBUTION_PLATFORM_GZH, Model_Distribution::DISTRIBUTION_PLATFORM_SEVICE])){
            //公众号服务号,可修改标题
            $data['ArticleTitle'] = trim($this->_getParam('ArticleTitle', ''));
        }

        $model->update($data, "DistributionID = {$id}");
        $this->showJson(1,"更新成功");
    }

    /**
     * 删除派单信息
     */
    public function delAction()
    {
        $id = intval($this->_getParam('DistributionID', 0));
        if(empty($id)){
            $this->showJson(0, '请提交派单ID');
        }
        $model = new Model_Distribution();
        $model->delete("DistributionID = '{$id}'");
        $this->showJson(1,"删除成功");
    }

    public function testAction(){
        try{
            $sendTime = date('Y-m-d', strtotime('-29 days')); //查询30天之内的派单记录
            $now = date('Y-m-d H:i:s');
            $model = new Model_Distribution();
            $deviceModel = new Model_Device();
            $db = $model->getHashSlaveDB();
            $pagesize = 50; //一次处理50条
            $page = 1;
            $data = $model->select()->where('SendTime >= ?', $sendTime.' 00:00:00')->where('SendTime <= ?', $now)->limitPage($page, $pagesize)->query()->fetchAll();
            if(!$data){
                exit('no data');
            }else {
                $onlineWeixin = $deviceModel->getOneOnlineWeixin();
                if(!$onlineWeixin){
                    DM_Controller::Log('info','未找到在线微信设备,无法下发查询公众号阅读数任务');
                }
                foreach ($data as $res){
                    $updateData = [];
                    if($res['Devices'] == ''){
                        DM_Controller::Log('info',"个号设备为空，不统计，ID：".$res['DistributionID']);
                        continue;
                    }

                    if($res['WeixinIDs'] == ''){
                        DM_Controller::Log('info',"微信ID为空，不统计，ID：".$res['DistributionID']);
                        continue;
                    }

                    $weixinIDs = explode(',', $res['WeixinIDs']);
                    //根据微信ID查询微信号,由于订单里可能是weixin也可能是alias
                    $sql = 'select Weixin from weixins_view where WeixinID IN ('.$res['WeixinIDs'].')';
                    $weixins = $db->query($sql)->fetchAll();
                    if(empty($weixins)){
                        DM_Controller::Log('info','未找到微信号，ID：'.$res['DistributionID']);
                        continue;
                    }

                    //查询公众号、服务号文章的阅读数,找到空闲设备下发任务
                    if(in_array($res['Platform'], [Model_Distribution::DISTRIBUTION_PLATFORM_SEVICE, Model_Distribution::DISTRIBUTION_PLATFORM_GZH])
                        && $res['ViewNumGetTime'] <= date('Y-m-d H:i:s', strtotime('-1 hour'))
                        && $res['ArticleUrl'] != '' && $onlineWeixin
                    ){
                        $task_config = [
                            'Url' => $res['ArticleUrl']
                        ];
                        Model_Task::addCommonTask(TASK_CODE_GET_GZHURL_VIEWNUM, 67, json_encode($task_config), $res['AdminID']);
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
                    $orderToday = date('Y-m-d', strtotime($res['SendTime']));
                    $buyerAddDate = date('Y-m-d', strtotime('+3 days', strtotime($res['SendTime']))); //进粉3天内
                    $h48OrderDate = date('Y-m-d', strtotime('+2 days', strtotime($res['SendTime']))); //48H订单
                    $d15OrderDate = date('Y-m-d', strtotime('+15 days', strtotime($res['SendTime']))); //15天订单
                    $d30OrderDate = date('Y-m-d', strtotime('+30 days', strtotime($res['SendTime']))); //30天订单

                    $order_sql = [];
                    foreach ($weixins as $weixin){
                        //24小时下单量和销售额
                        $order_sql[] = " (select Seller,Sum(TotalAmount) as TotalOrderAmount,count(OrderID) as TotalOrderNum,'h24' as 'hours' from orders where Seller = '{$weixin['Weixin']}' and OrderDate = '{$orderToday}' and BuyerAddTime >= '{$orderToday}' and BuyerAddTime <'{$buyerAddDate}' and Status IN (1,2)) ";
                        //48小时
                        $order_sql[] = " (select Seller,Sum(TotalAmount) as TotalOrderAmount,count(OrderID) as TotalOrderNum,'h48' as 'hours' from orders where Seller = '{$weixin['Weixin']}' and OrderDate >= '{$orderToday}' and OrderDate < '{$h48OrderDate}' and BuyerAddTime >= '{$orderToday}' and BuyerAddTime <'{$buyerAddDate}' and Status IN (1,2)) ";
                        //15天
                        $order_sql[] = " (select Seller,Sum(TotalAmount) as TotalOrderAmount,count(OrderID) as TotalOrderNum,'d15' as 'hours' from orders where Seller = '{$weixin['Weixin']}' and OrderDate >= '{$orderToday}' and OrderDate < '{$d15OrderDate}' and BuyerAddTime >= '{$orderToday}' and BuyerAddTime <'{$buyerAddDate}' and Status IN (1,2)) ";
                        //30天
                        $order_sql[] = " (select Seller,Sum(TotalAmount) as TotalOrderAmount,count(OrderID) as TotalOrderNum,'d30' as 'hours' from orders where Seller = '{$weixin['Weixin']}' and OrderDate >= '{$orderToday}' and OrderDate < '{$d30OrderDate}' and BuyerAddTime >= '{$orderToday}' and BuyerAddTime <'{$buyerAddDate}' and Status IN (1,2)) ";
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
                }
            }
        } catch (Exception $e){
            DM_Controller::Log('info','error:'.$e->getMessage());
        }
    }
}