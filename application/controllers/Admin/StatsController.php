<?php

require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_StatsController extends AdminBase
{

    /**
     * 数据统计-管理员分组
     */
    public function listAction()
    {
        $admin_ids = $this->_getParam('AdminID',null);
        $start_date = $this->_getParam('StartDate',null);
        $end_date = $this->_getParam('EndDate',null);

        // Model
        $stats_model = new Model_Stat();
        $weixin_model = new Model_Weixin();
        $admin_model = new Model_Role_Admin();

        // 获取管理员
        if (!$admin_ids){
            $admins = $weixin_model->findWeixinAdminID();
        }else{
            $admins = explode(',',$admin_ids);
        }

        // Day
        $day_num = date('z',strtotime($end_date))-date('z',strtotime($start_date));

        $results = array();

        $data = $stats_model->stats($admins,$start_date,$end_date);

        // 根据管理员循环
        foreach ($admins as $admin_id){

            $admin = array();

            $admin['AdminID'] = $admin_id;

            if ($admin_id === 0){

                $admin['AdminName'] = '';

            }elseif ($admin_id == 'all'){

                $admin['AdminName'] = '汇总';

            }else{
                $admin_info = $admin_model->getInfoByID($admin_id);
                $admin['AdminName'] = empty($admin_info['Username'])?'':$admin_info['Username'];
            }

            // 循环天数
            for ($i=0;$i<=$day_num;$i++){
                $day = date('Y-m-d',strtotime($start_date.'+'.$i.' day'));

                $adminstates = empty($data[$admin_id.'+'.$day])?false:$data[$admin_id.'+'.$day];

                $res['FriendNum'] = empty($adminstates)?0:$adminstates['FriendNum'];
                $res['GroupNum'] = empty($adminstates)?0:$adminstates['GroupNum'];
                $res['PhSendFriendNum'] = empty($adminstates)?0:$adminstates['PhSendFriendNum'];
                $res['PhSendWeixinNum'] = empty($adminstates)?0:$adminstates['PhSendWeixinNum'];
                $res['PhSendUnknownNum'] = empty($adminstates)?0:$adminstates['PhSendUnknownNum'];
                $res['PhAddFriendNum'] = empty($adminstates)?0:$adminstates['PhAddFriendNum'];
                $res['WeixinNum'] = empty($adminstates)?0:$adminstates['WeixinNum'];

                $res['Date'] = $day;
                if (!empty($adminstates) && $adminstates['PhSendFriendNum'] !=0){
                    $res['Pass'] = (number_format($adminstates['PhAddFriendNum']/$adminstates['PhSendFriendNum'],2)*100).'%';
                }else{
                    $res['Pass'] = '00%';
                }

                $admin['AdminData'][] = $res;
            }

            $results[] = $admin;
        }

        $this->showJson(1,'统计列表',$results);

    }


    /**
     * 数据统计-渠道分组
     */
    public function channelListAction()
    {
        $channel_ids = $this->_getParam('ChannelID',null);
        $start_date = $this->_getParam('StartDate',null);
        $end_date = $this->_getParam('EndDate',null);

        // Model
        $stats_model = new Model_Stat();
        $weixin_model = new Model_Weixin();
        $create_model = new Model_Category();

        // 获取管理员
        if (!$channel_ids){
            $channels = $weixin_model->findChannels();
        }else{
            $channels = explode(',',$channel_ids);
            array_splice($channels,0,0,['all']);
        }

        // Day
        $day_num = date('z',strtotime($end_date))-date('z',strtotime($start_date));

        $results = array();

        // 根据管理员循环
        foreach ($channels as $channel_id){

            $channel = array();

            $channel['ChannelID'] = $channel_id;

            if ($channel_id === 0){
                $channel['ChannelName'] = '';

            }elseif ($channel_id == 'all'){
                $channel['ChannelName'] = '汇总';

            }else{
                $channel_info = $create_model->getCategoryById($channel_id);
                $channel['ChannelName'] = $channel_info['Name'];
            }

            // 循环天数
            for ($i=0;$i<=$day_num;$i++){
                $day = date('Y-m-d',strtotime($start_date.'+'.$i.' day'));

                // 获取渠道微信个数
                if ($channel_id == 'all'){
                    $weixins = 'all';
                }else{
                    $weixins = $weixin_model->findChannel($channel_id);
                }

                $res = $stats_model->channel($weixins,$day);

                $res['FriendNum'] = $res['FriendNum']==null?0:$res['FriendNum'];
                $res['GroupNum'] = $res['GroupNum']==null?0:$res['GroupNum'];
                $res['PhSendFriendNum'] = $res['PhSendFriendNum']==null?0:$res['PhSendFriendNum'];
                $res['PhAddFriendNum'] = $res['PhAddFriendNum']==null?0:$res['PhAddFriendNum'];

                $res['Date'] = $day;
                if ($res['PhSendFriendNum'] !=0){
                    $res['Pass'] = (number_format($res['PhAddFriendNum']/$res['PhSendFriendNum'],2)*100).'%';
                }else{
                    $res['Pass'] = '00%';
                }

                $channel['ChannelData'][] = $res;
            }

            $results[] = $channel;
        }

        $this->showJson(1,'统计列表',$results);

    }


    /**
     * 数据统计-微信分组
     */
    public function phoneListAction()
    {
        $page = $this->getParam('Page', 1);
        $pagesize = $this->getParam('Pagesize', 100);
        $admin_id = $this->getParam('AdminID',null);
        $weixin_tags = $this->_getParam('WeixinTags',null);
        $start_date = $this->_getParam('StartDate',date("Y-m-d",strtotime("-3 days")));
        $end_date = $this->_getParam('EndDate',date("Y-m-d",strtotime("-1 days")));
        $nickname = $this->_getParam('Nickname',null);
        $serial_num = $this->_getParam('SerialNum',null);
        $export = (int)$this->_getParam("Export",0);

        if ($start_date == null || $end_date == null){
            $this->showJson(0,'请输入时间');
        }
        $days = round((strtotime($end_date)-strtotime($start_date))/3600/24);

        if ($export == 1){
            if ($days>=6){
                $this->showJson(0,'打印时间控制在6天及以内');
            }
            $pagesize = 10000*$days;
        }

        // Model
        $stats_model = new Model_Stat();
        $weixin_model = new Model_Weixin();

        $stat_data = $stats_model->wxStats($admin_id,$weixin_tags,$start_date,$end_date,$nickname,$serial_num,$page,$pagesize);

        $weixin_ids = [];
        foreach ($stat_data['Results'] as $w){
            if (!in_array($w['WeixinID'],$weixin_ids)){
                $weixin_ids[] = $w['WeixinID'];
            }
        }
        $all_weixins = $weixin_model->findWeixinCategory($weixin_tags);
        $all_weixin_ids = [];
        foreach ($all_weixins as $a){
            $all_weixin_ids[] = $a['WeixinID'];
        }

        $weixins = $weixin_model->getWeixins($weixin_ids);

        $res = array();
        $title[0] = '手机编号';
        $cells_data = [];

        for ($i=0;$i<=$days;$i++){
            $date = date('Y-m-d',strtotime($end_date.'-'.$i.' day'));

            // 表格
            $cells_data[] = [
                'Title'=>$date
            ];

            $title[$i*4+1] = '申请次数'.$date;
            $title[$i*4+2] = '新增'.$date;
            $title[$i*4+3] = '粉丝总数'.$date;
            $title[$i*4+4] = '通过率'.$date;

            // 总汇(搜索不进行汇总)
            if ($nickname == null && $serial_num == null){
                $all_data = $stats_model->findAllData($date,$all_weixin_ids);

                $res[0]= [
                    "WeixinID" => 'all',
                    "Weixin" => 'all',
                    "WeixinName" => '汇总',
                    "SerialNum" => ''

                ];

                if ($all_data['PhSendFriendNum'] !=0){
                    $pass = (number_format($all_data['PhAddFriendNum']/$all_data['PhSendFriendNum'],4)*100).'%';
                }else{
                    $pass = '00%';
                }
                $data[] = [
                    "Date" => $date,
                    "FriendNum" => empty($all_data['FriendNum'])?0:$all_data['FriendNum'],
                    "PhSendFriendNum" => empty($all_data['PhSendFriendNum'])?0:$all_data['PhSendFriendNum'],
                    "PhAddFriendNum" => empty($all_data['PhAddFriendNum'])?0:$all_data['PhAddFriendNum'],
                    'Pass' => $pass
                ];

            }

        }
        $res[0]['Data'] = $data;

        // 从新排序
        ksort($title);

        foreach ($weixins as $sk=>$w){
            $res[$sk+1]=[
                'WeixinID'=>$w['WeixinID'],
                'Weixin'=>$w['Weixin'],
                'WeixinName'=>$w['Nickname'],
                'SerialNum'=>$w['SerialNum'],
            ];
            // 微信号下的数据
            $data = [];
            for ($i=0;$i<=$days;$i++){
                $date = date('Y-m-d',strtotime($end_date.'-'.$i.' day'));
                $key = $w['WeixinID'].':'.$date;

                if (!empty($stat_data['Results'][$key])){
                    $data[] = [
                        'Date'=>$date,
                        'FriendNum'=>empty($stat_data['Results'][$key]['FriendNum'])?0:$stat_data['Results'][$key]['FriendNum'],
                        'PhSendFriendNum'=>empty($stat_data['Results'][$key]['PhSendFriendNum'])?0:$stat_data['Results'][$key]['PhSendFriendNum'],
                        'PhAddFriendNum'=>empty($stat_data['Results'][$key]['PhAddFriendNum'])?0:$stat_data['Results'][$key]['PhAddFriendNum'],
                        'Pass'=>empty($stat_data['Results'][$key]['Pass'])?0:$stat_data['Results'][$key]['Pass'],
                    ];
                    $res[$sk+1]['Data'] = $data;
                }else{
                    $data[] = [
                        'Date'=>$date,
                        'FriendNum'=>0,
                        'PhSendFriendNum'=>0,
                        'PhAddFriendNum'=>0,
                        'Pass'=>'00%',
                    ];
                    $res[$sk+1]['Data'] = $data;
                }
            }
        }
        $result_data = array(
            'Page' => $stat_data['Page'],
            'Pagesize' => $stat_data['Pagesize'],
            'TotalCount' => $stat_data['TotalCount'],
            'TotalPage' => $stat_data['TotalPage'],
            'Results' => $res
        );

        $export_data = array();
        $export_data[1] = $title;
        // 是否打印
        if ($export){
            $centent = array();
            foreach ($res as $k=>$r){
                $centent[0] = $r['SerialNum'];
                for ($i=0;$i<=$days;$i++){
                    $centent[$i*4+1] = empty($r['Data'][$i]['PhSendFriendNum'])?0:$r['Data'][$i]['PhSendFriendNum'];
                    $centent[$i*4+2] = empty($r['Data'][$i]['PhAddFriendNum'])?0:$r['Data'][$i]['PhAddFriendNum'];
                    $centent[$i*4+3] = empty($r['Data'][$i]['FriendNum'])?0:$r['Data'][$i]['FriendNum'];
                    $centent[$i*4+4] = empty($r['Data'][$i]['Pass'])?'00%':$r['Data'][$i]['Pass'];
                }
                ksort($centent);
                $export_data[] = $centent;
            }
            $excel = new DM_ExcelExport();
            try{
                $excel->setTemplateFile(APPLICATION_PATH . "/data/excel/default.xls")
                    ->setFirstRow(1)
                    ->setMergeCells(1,1,3,$cells_data)
                    ->setData($export_data)->export();
            }catch (Exception $e){
                $this->showJson(0,'抛出异常'.$e->getMessage());
            }

        }else{
            $this->showJson(1,'统计列表',$result_data);
        }

    }


    /**
     * 数据统计微信分组-详情
     */
    public function weixinInfoAction()
    {
        $weixin_id = $this->_getParam('WeixinID',null);
        $start_date = $this->_getParam('StartDate',null);
        $end_date = $this->_getParam('EndDate',null);

        // Model
        $stats_model = new Model_Stat();
        $weixin_model = new Model_Weixin();

        $weixin_info = $weixin_model->getDataByWeixinID($weixin_id);

        $res['WeixinID'] = $weixin_id;
        $res['WeixinName'] = $weixin_info['Nickname'];

        $res['Stats'] = $stats_model->findWeixinStats($weixin_id,$start_date,$end_date);

        foreach ($res['Stats'] as &$v){

            if ($v['PhSendFriendNum'] !=0){
                $v['Pass'] = (number_format($v['PhAddFriendNum']/$v['PhSendFriendNum'],2)*100).'%';
            }else{
                $v['Pass'] = '00%';
            }
        }

        $this->showJson(1,'详情',$res);

    }
    /**
     * 粉丝报表
     */
    public function fansAction()
    {
        $AdminID = (int)$this->_getParam('AdminID',0);
        $CategoryID = $this->_getParam('CategoryID',0);
        $StartDate = $this->_getParam('StartDate',date("Y-m-d",strtotime("-7 days")));
        $EndDate = $this->_getParam('EndDate',date("Y-m-d",strtotime("-1 days")));
        $Export = (int)$this->_getParam("Export",0);
        $model = new Model_Stat();
        $select = $model->select()->setIntegrityCheck(false);
        $select->from("weixins as w",["WeixinID","NickName"]);
        $select->joinLeft("admins as a","w.AdminID = a.AdminID","ifnull(Username,'') as AdminName");
        $select->joinLeft("devices as d","w.DeviceID = d.DeviceID","SerialNum");
        $select->joinLeft("weixin_friends as wf","w.WeixinID = wf.WeixinID and wf.IsDeleted = 0",
            "count(*) as TotalFriends");
        if ($AdminID > 0){
            $select->where("w.AdminID = ?",$AdminID);
        }
        if ($CategoryID > 0){
            $select->where("w.CategoryIds != '' and FIND_IN_SET(?,w.CategoryIds) > 0",$CategoryID);
        }
        $select->group("w.WeixinID");
        $select->order("TotalFriends desc");
//        var_dump($select->__toString());exit();
        $res = $select->query()->fetchAll();
        $ids = [];
        foreach ($res as $r) {
            $ids[] = $r["WeixinID"];
        }
        if(count($ids)==0){
            $this->showJson(1,"",[]);
        }
        $select = $model->select()->setIntegrityCheck(false);
        $select->from($model->getTableName(),["WeixinID","Date","AddFriendNum"]);
        $select->where("Date >= ?",$StartDate);
        $select->where("Date <= ?",$EndDate);
        $select->where("WeixinID in (?)",$ids);
        $results = $select->query()->fetchAll();
        $friends = [];
        foreach ($results as $r) {
            // 新增好友数量
            $friends[$r["WeixinID"]][$r["Date"]] = $r["AddFriendNum"];
        }
        $first = true;
        $firstRow = [
            "管理员","手机编号","微信号"
        ];
        $data = [
            null
        ];
        foreach ($res as $k => $r){
            $dates = $friends[$r["WeixinID"]]??[];
            $preDate = 0;
            $start = $StartDate;
            $d = [
                $r["AdminName"],
                $r["SerialNum"],
                $r["NickName"]
            ];
            do{
                // 改为限制 好友增量
                if(!array_key_exists($start,$dates)){
                    $dates[$start] = null;
                }
//                $preDate = $dates[$start];
//                if($first) {
//                    $firstRow[] = date("m月d日",strtotime($start));
//                }
//                $d[] = $preDate;
                $start = date("Y-m-d",strtotime($start ." +1 days"));
            }while ($start < $EndDate);
            $res[$k]["list"] = $dates;
            $d[] = $r["TotalFriends"];
            $data[] = $d;
            $first = false;
        }
        $firstRow[] = "总粉丝数";
        $data[0] = $firstRow;
        if($Export){
            $excel = new DM_ExcelExport();
            $excel->setTemplateFile(APPLICATION_PATH . "/data/excel/default.xls")
                ->setFirstRow(1)
                ->setData($data)->export();
        }else{
            $this->showJson(1,'ok',$res);
        }
    }

    public function weixinListAction()
    {
        $page = $this->_getParam('Page', 1);
        $pagesize = $this->_getParam('Pagesize', 100);
        $weixin_tag = $this->_getParam('CategoryIds',null);
        $admin_id = $this->_getParam('AdminID',null);
        $search = $this->_getParam('Search',null);
        $serial_num = $this->_getParam('SerialNum',null);
        $start_date = $this->_getParam('StartDate',date('Y-m-d',strtotime("-3 day")));
        $end_date = $this->_getParam('EndDate',date('Y-m-d',strtotime("-1 day")));
        $export = $this->_getParam('Export',0);   // 是否打印
        $days = round((strtotime($end_date)-strtotime($start_date))/3600/24);
        if ($export == 1){
            if ($days>=3){
                $this->showJson(0,'打印时间控制在3天及以内');
            }
            set_time_limit(0);
            ini_set('memory_limit', '1024M');
            $pagesize = 10000;
        }


        $weixin_model = new Model_Weixin();
        $weixin_stat_model = new Model_Stat();

        $weixin_data = $weixin_model->weixinJoinFirendStat($weixin_tag,$admin_id,$search,$serial_num,$page,$pagesize);

        if ($weixin_data['Results']){
            $weixinIds = array();

            foreach ($weixin_data['Results'] as $id){
                $weixinIds[] = $id['WeixinID'];

            }

            // 查询出分页数据
            $stat_data = $weixin_stat_model->weixinStat($weixinIds,$start_date,$end_date);

            // 汇总数据查询
            $all_data = $weixin_stat_model->findAllStat($weixin_tag,$admin_id,$search,$serial_num,$start_date,$end_date);

            $all = [
                'WeixinID' => 'all',
                'Nickname' => '汇总',
                'SerialNum' => 0,
            ];
            $cells_data = $export_data = [];
            $title[0] = '手机编号';

            for ($i=0;$i<=$days;$i++){
                $date = date('Y-m-d',strtotime($end_date.'-'.$i.' day'));

                $cells_data[] = [
                    'Title'=>$date
                ];

                $title[$i*4+1] = '申请次数'.$date;
                $title[$i*4+2] = '新增'.$date;
                $title[$i*4+3] = '粉丝总数'.$date;
                $title[$i*4+4] = '通过率'.$date;

                $all_content = [];
                foreach ($all_data as &$a) {
                    if ($a['Date'] == $date){

                        $all_content = $a;
                        if ($a['WxSendFriendNum'] == 0){
                            $all_content['Pass'] = '00%';
                        }else{
                            $all_content['Pass']= (number_format($a['WxAddFriendNum'] / $a['WxSendFriendNum'], 2) * 100) . '%';
                        }
                        break;
                    }else{
                        $all_content = [
                            'Date' =>$date,
                            'FriendNum' =>0,
                            'WxSendFriendNum' =>0,
                            'WxAddFriendNum' =>0,
                            'Pass' => '00%'
                        ];
                    }
                }

                $all['Data'][] = $all_content;
            }
            $export_data[1] = $title;

            foreach ($weixin_data['Results'] as &$w){
                $content = [];
                $content[0] = $w['SerialNum'];

                for ($i=0;$i<=$days;$i++){
                    $date = date('Y-m-d',strtotime($end_date.'-'.$i.' day'));


                    $key = $w['WeixinID'].':'.$date;

                    if (empty($stat_data[$key])){
                        $data = [
                            'Date' =>$date,
                            'FriendNum' =>0,
                            'WxSendFriendNum' =>0,
                            'WxAddFriendNum' =>0,
                            'Pass' => '00%'

                        ];
                    }else{

                        $data = [
                            'Date' =>$date,
                            'FriendNum' =>$stat_data[$key]['FriendNum'],
                            'WxSendFriendNum' =>$stat_data[$key]['WxSendFriendNum'],
                            'WxAddFriendNum' =>$stat_data[$key]['WxAddFriendNum'],
                        ];
                        if ($stat_data[$key]['WxSendFriendNum'] == 0){
                            $data['Pass'] = '00%';
                        }else{
                            $data['Pass']= (number_format($stat_data[$key]['WxAddFriendNum'] / $stat_data[$key]['WxSendFriendNum'], 2) * 100) . '%';
                        }
                    }
                    $content[$i*4+1] = $data['WxSendFriendNum'];
                    $content[$i*4+2] = $data['WxAddFriendNum'];
                    $content[$i*4+3] = $data['FriendNum'];
                    $content[$i*4+4] = $data['Pass'];

                    $w['Data'][] = $data;
                }

                $export_data[] = $content;

            }

            // 汇总插入数据前端
            array_splice($weixin_data['Results'],0,0,[$all]);
            $excel = new DM_ExcelExport();

            if ($export){
                $excel->setTemplateFile(APPLICATION_PATH . "/data/excel/default.xls")
                    ->setFirstRow(1)
                    ->setMergeCells(1,1,3,$cells_data)
                    ->setData($export_data)->export();
            }else{
                $this->showJson(1,'统计列表',$weixin_data);
            }
        }else{
            $this->showJson(1,'统计列表',$weixin_data);
        }

    }
}