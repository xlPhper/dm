<?php

require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_PositionController extends AdminBase
{
    /**
     * 时间的选项列表
     */
    public function stopListAction()
    {
        $stop_model = new Model_Stop();

        $res = $stop_model->findAll();

        $this->showJson(1,'',$res);

    }


    /**
     * 定位信息
     */
    public function listAction()
    {
        try{
            $page = $this->getParam('Page', 1);
            $pagesize = $this->getParam('Pagesize', 100);
            $city = $this->getParam('City', null);
            $tags = $this->getParam('Tags', null);
            $in_wx = (int)$this->getParam('InWx', null);

            $position_model = new Model_Position();
            $category_model = new Model_Category();

            $select = $position_model->fromSlaveDB()->select()->from($position_model->getTableName().' as p')->setIntegrityCheck(false);
            $select->joinLeft('weixins as w','w.WeixinID = p.WeixinID',['w.Weixin','w.Alias','w.Nickname']);

            // 城市搜索
            if ($city){
                $select->where('City like ?',"%".$city."%");
            }

            // 标签搜索
            if (!empty($tags)) {
                $where_msg ='';
                $tags_data = explode(',',$tags);
                foreach($tags_data as $t){
                    $where_msg .= "FIND_IN_SET(".$t.",p.Tags) OR ";
                }
                $where_msg = rtrim($where_msg,'OR ');
                $select->where($where_msg);
            }

            // 微信是否匹配搜索
            if ($in_wx == 1){
                $select->where('p.WeixinID = 0');
            }elseif($in_wx == 2){
                $select->where('p.WeixinID > 0');
            }

            $res = $position_model->getResult($select,$page,$pagesize);

            $categories = $category_model->getIdToName('POSITION');
            foreach ($res['Results'] as &$v){
                if ($v['Tags']){
                    $arr = explode(",", $v['Tags']);
                    $label = [];
                    foreach ($arr as $id) {
                        $label[] = $categories[$id]??$id;
                    }
                    $v['Tags']= implode(',',$label);
                }
            }
            $this->showJson(1,'列表',$res);
        }catch(Exception $e){
            $this->showJson(self::STATUS_FAIL,'抛出异常'.$e->getMessage());
        }
    }

    /**
     * 修改标签
     */
    public function saveTagsAction()
    {
        $position_ids = $this->_getParam('PositionIds',null);
        $tags = $this->_getParam('Tags',null);

        $position_model = new Model_Position();

        $up = $position_model->update(['Tags'=>$tags],['PositionID in (?)'=>explode(',',$position_ids)]);

        if ($up){
            $this->showJson(1,'修改成功');
        }else{
            $this->showJson(0,'修改失败');
        }
    }

    /**
     * 删除
     */
    public function delAction()
    {
        $position_ids = $this->_getParam('PositionIds',null);

        $position_model = new Model_Position();

        $del = $position_model->delete(['PositionID in (?)'=>explode(',',$position_ids)]);

        if ($del){
            $this->showJson(1,'删除成功');
        }else{
            $this->showJson(0,'删除失败');
        }
    }

    /*
     * 批量添加
     */
    public function addAction()
    {

        try{
            $city = $this->getParam('City', null);
            $tags = $this->getParam('Tags', null);
            $name = $this->getParam('Name', null);
            $real_name = $this->getParam('RealName', null);
            $address = $this->getParam('Address', null);
            $longitude = $this->getParam('Longitude', null);
            $latitude = $this->getParam('Latitude', null);
            $address_id = $this->getParam('AddressID', null);

            $model = new Model_Position();

            $data = [
                'City' => $city,
                'Tags' => $tags,
                'UpdateDate' => date('Y-m-d H:i:s'),
                'Name' => $name,
                'RealName' => $real_name,
                'Address' => $address,
                'Longitude' => $longitude,
                'Latitude' => $latitude,
                'AddressID' => $address_id
            ];
            $insert = $model->insert($data);

            if (!$insert){
                $this->showJson(0,'添加失败');
            }

            $this->showJson(1,'批量添加成功');
        }catch (Exception $e){
            $this->showJson(0,'抛出异常'.$e->getMessage());
        }

    }

    /**
     * 随机定位
     */
    public function positionAction()
    {
        $weixinIds = trim($this->_getParam('WeixinIds',''),'');
        $tags = $this->getParam('Tags', null); // 位置标签
        $startDate = $this->getParam('StartDate', null);
        $endDate = $this->getParam('EndDate', null);
        $execTime = $this->getParam('ExecTime', null);

        if ($weixinIds == ''){
            $this->showJson(self::STATUS_FAIL, '微信信息不存在');
        }

        if (strtotime($startDate) === false) {
            $this->showJson(self::STATUS_FAIL, '开始时间非法');
        }
        if (strtotime(date('Y-m-d')) > strtotime($startDate)) {
            $this->showJson(self::STATUS_FAIL, '开始时间须 >= 今天');
        }
        if (strtotime($endDate) === false) {
            $this->showJson(self::STATUS_FAIL, '结束时间非法');
        }
        if (strtotime(date('Y-m-d')) > strtotime($endDate)) {
            $this->showJson(self::STATUS_FAIL, '结束时间须 >= 今天');
        }
        if (strtotime($startDate) > strtotime($endDate)) {
            $this->showJson(self::STATUS_FAIL, '结束时间须 >= 开始时间');
        }
        if ($execTime === '') {
            $this->showJson(self::STATUS_FAIL, '执行时间非法');
        }
        $execTime = json_decode($execTime, 1);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->showJson(self::STATUS_FAIL, '执行时间非法');
        }
        $validExecTime = Helper_Timer::getValidOptions($execTime);
        if ($validExecTime === false) {
            $this->showJson(self::STATUS_FAIL, '执行时间非法');
        }

        // Get Next Run Time
        list($nextRunTime, $newxtRunType) = Helper_Timer::getNextRunTime($startDate, $endDate, $validExecTime);
        if ($nextRunTime == '0000-00-00 00:00:00') {
            $this->showJson(self::STATUS_FAIL, '没有找到合适的下一次执行时间');
        }

        $positionWeixinModel= new Model_PositionWeixin;

        $positionTask = $positionWeixinModel->findPositionTask($tags,$weixinIds,null);
        if ($positionTask){
            $this->showJson(self::STATUS_FAIL, '选择的微信号在改位置标签下已经有任务');
        }

        $data = [
            'PositionTagID' => $tags,
            'Weixins' => $weixinIds,
            'StartDate' => $startDate,
            'EndDate' => $endDate,
            'ExecTime' => json_encode($validExecTime),
            'NextRunType' => $newxtRunType,
            'NextRunTime' => $nextRunTime,
            'AddDate' => date('Y-m-d H:i:s'),
        ];

        $positionWeixinModel->insert($data);

        $this->showJson(1,'添加成功');

    }

    /**
     * 修改位置
     */
    public function saveAddressAction()
    {
        $position_id = $this->_getParam('PositionID',null);
        $address = $this->getParam('Address', null);
        $name = $this->getParam('Name', null);
        $real_name = $this->getParam('RealName', null);
        $longitude = $this->getParam('Longitude', null);
        $latitude = $this->getParam('Latitude', null);
        $address_id = $this->getParam('AddressID', null);

        $model = new Model_Position();

        $data = [
            'Address' => $address,
            'Name' => $name,
            'RealName' => $real_name,
            'Longitude' => $longitude,
            'Latitude' => $latitude,
            'AddressID' => $address_id,
            'NextRunTime' => date('Y-m-d H:i:s'),
            'UpdateDate' => date('Y-m-d H:i:s')
        ];

        $up = $model->update($data,['PositionID = ?'=>$position_id]);

        if ($up){
            $this->showJson(1,'修改成功');
        }else{
            $this->showJson(0,'修改失败');
        }

    }

    /**
     * 解除绑定
     */
    public function removeAction()
    {
        $position_id = $this->_getParam('PositionID',null);

        $model = new Model_Position();

        $up = $model->update(['WeixinID'=>0],['PositionID = ?'=>$position_id]);

        if ($up){
            $this->showJson(1,'解除成功');
        }else{
            $this->showJson(0,'解除失败');
        }
    }

    /**
     * 随机定位任务列表
     */
    public function taskListAction()
    {
        $page           = $this->getParam('Page', 1);
        $pagesize       = $this->getParam('Pagesize', 100);
        $PositionTagID  = $this->getParam('PositionTagID');//定位标签
        $status         = (int)$this->getParam('Status');//任务状态
        $StartDate      = $this->getParam('StartDate');
        $EndDate        = $this->getParam('EndDate');
        $nickName       = trim($this->getParam('Nickname'));
        $SerialNum      = $this->getParam('SerialNum');//设备编号
        $CategoryIds    = $this->getParam('CategoryIds');//微信标签

        $positionWeixinModel = new Model_PositionWeixin();
        $categoryModel = new Model_Category();

        $select = $positionWeixinModel->select()->setIntegrityCheck(false);
        $select = $select->from($positionWeixinModel->getTableName()." as p");
        $select->joinLeft('weixins as w','w.WeixinID = p.Weixins');
        $select->joinLeft('devices as d','d.OnlineWeixinID = p.Weixins',["Status as DevicesStatus","SerialNum"]);
        if($PositionTagID)
        {
            $select->where('p.PositionTagID = ?',$PositionTagID);
        }

        if($nickName)
        {
            $select->where('w.Nickname like ?',"%$nickName%");
        }

        if($CategoryIds)
        {
            $select->where('w.CategoryIds = ?',$CategoryIds);
        }

        if($SerialNum)
        {
            $select->where('d.SerialNum like ?',"%$SerialNum%");
        }

        if($status)
        {
            if($status==2){
                $select->where('p.status = ?',0);
            }else{
                $select->where('p.status = ?',$status);
            }
        }

        if($StartDate)
        {
            $select->where('p.StartDate > ?',$StartDate);
        }

        if($EndDate)
        {
            $select->where('p.StartDate < ?',$EndDate);
        }

        $res = $positionWeixinModel->getResult($select,$page,$pagesize);
        $categories = $categoryModel->getIdToName('POSITION');
        foreach ($res['Results'] as &$v){
            if ($v['PositionTagID']){
                $v['PositionTagID']= $categories[$v['PositionTagID']]??$v['PositionTagID'];
            }
            if($v['Status']==0)
            {
                $v['Status']=2;
            }
        }
        $this->showJson(1,'随机定位任务列表',$res);

    }

    /**
     * 定位任务状态的开启
     */

    public function taskStatusAction()
    {
        $status         = $this->_getParam('Status');
        $PositionWxID   = $this->_getParam('PositionWxID');
        if(!$PositionWxID)
        {
            $this->showJson(0,'参数错误');
        }
        $pModel = new Model_PositionWeixin();
        $result = $pModel->update(['Status'=>$status],['PositionWxID = ?'=>$PositionWxID]);
        if($result)
        {
            $this->showJson(1,'操作成功');
        }else{
            $this->showJson(0,'操作失败');
        }

    }

    /**
     * 随机定位任务详情
     */
    public function taskInfoAction()
    {
        $pwID = $this->getParam('PositionWxID', 1);

        $positionWeixinModel = new Model_PositionWeixin();

        $res = $positionWeixinModel->findByID($pwID);

        $this->showJson(1,'随机定位任务列表',$res);
    }

    /**
     * 随机定位任务编辑
     */
    public function taskEditAction()
    {
        $positionWeixinID = $this->getParam('PositionWxID', null);
        $weixinIds = trim($this->_getParam('WeixinIds',''),'');
        $tags = $this->getParam('Tags', null); // 位置标签
        $startDate = $this->getParam('StartDate', null);
        $endDate = $this->getParam('EndDate', null);
        $execTime = $this->getParam('ExecTime', null);

        if ($positionWeixinID == ''){
            $this->showJson(self::STATUS_FAIL, '随机定位任务Id不存在');
        }

        if ($weixinIds == ''){
            $this->showJson(self::STATUS_FAIL, '微信信息不存在');
        }

        if (strtotime($startDate) === false) {
            $this->showJson(self::STATUS_FAIL, '开始时间非法');
        }
        if (strtotime(date('Y-m-d')) > strtotime($startDate)) {
            $this->showJson(self::STATUS_FAIL, '开始时间须 >= 今天');
        }
        if (strtotime($endDate) === false) {
            $this->showJson(self::STATUS_FAIL, '结束时间非法');
        }
        if (strtotime(date('Y-m-d')) > strtotime($endDate)) {
            $this->showJson(self::STATUS_FAIL, '结束时间须 >= 今天');
        }
        if (strtotime($startDate) > strtotime($endDate)) {
            $this->showJson(self::STATUS_FAIL, '结束时间须 >= 开始时间');
        }
        if ($execTime === '') {
            $this->showJson(self::STATUS_FAIL, '执行时间非法');
        }
        $execTime = json_decode($execTime, 1);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->showJson(self::STATUS_FAIL, '执行时间非法');
        }
        $validExecTime = Helper_Timer::getValidOptions($execTime);
        if ($validExecTime === false) {
            $this->showJson(self::STATUS_FAIL, '执行时间非法');
        }

        // Get Next Run Time
        list($nextRunTime, $newxtRunType) = Helper_Timer::getNextRunTime($startDate, $endDate, $validExecTime);
        if ($nextRunTime == '0000-00-00 00:00:00') {
            $this->showJson(self::STATUS_FAIL, '没有找到合适的下一次执行时间');
        }

        $positionWeixinModel= new Model_PositionWeixin;

        $positionTask = $positionWeixinModel->findPositionTask($tags,$weixinIds,$positionWeixinID);
        if ($positionTask){
            $this->showJson(self::STATUS_FAIL, '选择的微信号在该位置标签下已经有任务');
        }

        $data = [
            'PositionTagID' => $tags,
            'Weixins' => $weixinIds,
            'StartDate' => $startDate,
            'EndDate' => $endDate,
            'ExecTime' => json_encode($validExecTime),
            'NextRunType' => $newxtRunType,
            'NextRunTime' => $nextRunTime,
            'AddDate' => date('Y-m-d H:i:s'),
        ];

        $positionWeixinModel->update($data,['PositionWxID = ?'=>$positionWeixinID]);

        $this->showJson(1,'修改成功');
    }

}