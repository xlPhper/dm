<?php
/**
 * Created by PhpStorm.
 * User: jakins
 * Date: 2018/11/13
 * Time: 10:08
 * 养号任务管理
 */
require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_TrainController extends AdminBase
{
    public function listAction(){
        try{
            $page = $this->getParam('Page', 1);
            $pagesize = $this->getParam('Pagesize', 100);
            $weixin_tags = trim($this->_getParam('WeixinTags', '')); //微信号标签
            $adminID = intval($this->_getParam('AdminID', 0)); //创建人ID

            // Model
            $model = Model_TrainTasks::getInstance();
            $select = $model->fromSlaveDB()->select()->from($model->getTableName(),['TrainTaskID','WeixinTags','AdminID','CreateTime','Status','StartDate','EndDate']);

            // 微信标签筛选
            if ($weixin_tags != '') {
                $tagIds = array_unique(explode(',', $weixin_tags));
                $conditions = [];
                foreach ($tagIds as $tagId) {
                    if ((int)$tagId > 0) {
                        $conditions[] = 'find_in_set(' . $tagId . ', WeixinTags)';
                    }
                }
                if ($conditions) {
                    $select->where(implode(' or ', $conditions));
                }
            }
            if($adminID){
                $select->where('AdminID = ?', $adminID);
            }
            $select->order('CreateTime DESC');
            $res = $model->getResult($select,$page,$pagesize);

            if ($res['Results']){
                $category_model = new Model_Category();
                foreach ($res['Results'] as &$row){
                    // 标签转换
                    if ($row['WeixinTags']){
                        $tags = $category_model->findCategoryName($row['WeixinTags']);
                        $row['WeixinTagsName'] = implode(',',$tags);
                    }else{
                        $row['WeixinTagsName'] = '';
                    }
                }
                $model->getFiled($res['Results'], 'AdminID', 'admins', 'Username', 'AdminName');
            }
            $this->showJson(1,'养号任务列表',$res);
        }catch(Exception $e){
            $this->showJson(0, '抛出异常:'.$e->getMessage());
        }
    }

    public function editAction(){
        try{
            $data = [];
            $weixinTags = array_unique(array_filter(explode(',', trim($this->_getParam('WeixinTags', '')))));
            if(empty($weixinTags)){
                $this->showJson(0, '请勾选微信号标签');
            }
            $data['WeixinTags'] = implode(',', $weixinTags);
            $data['ViewMessageEnable'] = intval($this->_getParam('ViewMessageEnable', 0)); //自动点未读消息
            $data['ViewNewEnable'] = intval($this->_getParam('ViewNewEnable', 0)); //自动读新闻
            $data['StartDate'] = $this->_getParam('StartDate', '');
            $data['EndDate'] = $this->_getParam('EndDate', '');
            if(!strtotime($data['StartDate']) || !strtotime($data['EndDate']) || $data['StartDate'] > $data['EndDate']){
                $this->showJson(0, '任务执行时间有误,'.$data['StartDate'].'~'.$data['EndDate']);
            }
            $data['AddFriendConfig'] = trim($this->_getParam('AddFriendConfig', ''));
            List($flag, $msg) = Model_TrainTasks::checkConfigData('AddFriendConfig', $data['AddFriendConfig']);
            if(!$flag){
                $this->showJson(0, $msg);
            }
            $data['ChatConfig'] = trim($this->_getParam('ChatConfig', ''));
            List($flag, $msg) = Model_TrainTasks::checkConfigData('ChatConfig', $data['ChatConfig']);
            if(!$flag){
                $this->showJson(0, $msg);
            }
            $data['SendAlbumConfig'] = trim($this->_getParam('SendAlbumConfig', ''));
            List($flag, $msg) = Model_TrainTasks::checkConfigData('SendAlbumConfig', $data['SendAlbumConfig']);
            if(!$flag){
                $this->showJson(0, $msg);
            }
            $data['AlbumInteractConfig'] = trim($this->_getParam('AlbumInteractConfig', ''));
            List($flag, $msg) = Model_TrainTasks::checkConfigData('AlbumInteractConfig', $data['AlbumInteractConfig']);
            if(!$flag){
                $this->showJson(0, $msg);
            }
            $id = intval($this->_getParam('TrainTaskID', 0));
            $model = new Model_TrainTasks();
            if($id){
                $train = $model->find($id)->current();
                if(!$train){
                    $this->showJson(0, '未找到此养号任务信息,TrainTaskID:'.$id);
                }
                $data['UpdateTime'] = date('Y-m-d H:i:s');
                $model->update($data, ['TrainTaskID = ?' => $id]);
            }else{
                $data['CreateTime'] = date('Y-m-d H:i:s');
                $data['AdminID'] = $this->getLoginUserId();
                $model->insert($data);
            }
            $this->showJson(1, '操作成功');
        }catch(Exception $e){
            $this->showJson(0, '抛出异常:'.$e->getMessage());
        }
    }

    /**
     * 详情
     */
    public function detailAction(){
        try{
            $id = intval($this->_getParam('TrainTaskID', 0));
            if(empty($id)){
                $this->showJson(0, '请选择TranTaskID');
            }
            $train = Model_TrainTasks::getInstance()->fromSlaveDB()->find($id)->current();
            if(!$train){
                $this->showJson(0, '未找到此养号任务信息,TrainTaskID:'.$id);
            }
            $data = $train->toArray();
            if($data['AddFriendConfig']){
                $data['AddFriendConfig'] = json_decode($data['AddFriendConfig'], true);
            }else{
                $data['AddFriendConfig'] = [];
            }
            if($data['ChatConfig']){
                $data['ChatConfig'] = json_decode($data['ChatConfig'], true);
            }else{
                $data['ChatConfig'] = [];
            }
            if($data['SendAlbumConfig']){
                $data['SendAlbumConfig'] = json_decode($data['SendAlbumConfig'], true);
            }else{
                $data['SendAlbumConfig'] = [];
            }
            if($data['AlbumInteractConfig']){
                $data['AlbumInteractConfig'] = json_decode($data['AlbumInteractConfig'], true);
            }else{
                $data['AlbumInteractConfig'] = [];
            }
            $this->showJson(1, '详情', $data);
        }catch(Exception $e){
            $this->showJson(0, '抛出异常:'.$e->getMessage());
        }
    }

    /**
     * 开启/暂停
     */
    public function setStatusAction(){
        try{
            $id = intval($this->_getParam('TrainTaskID', 0));
            $status = intval($this->_getParam('Status'));
            if(empty($id)){
                $this->showJson(0, '请选择TranTaskID');
            }
            if(!in_array($status, [Model_TrainTasks::STATUS_ON, Model_TrainTasks::STATUS_STOP])){
                $this->showJson(0, '不支持的状态:'.$status);
            }
            $train = Model_TrainTasks::getInstance()->fromSlaveDB()->find($id)->current();
            if(!$train){
                $this->showJson(0, '未找到此养号任务信息,TrainTaskID:'.$id);
            }
            Model_TrainTasks::getInstance()->fromMasterDB()->update(['Status' => $status], ['TrainTaskID = ?' => $id]);
            $this->showJson(1, '操作成功');
        }catch(Exception $e){
            $this->showJson(0, '抛出异常:'.$e->getMessage());
        }
    }
}