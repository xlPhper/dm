<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/4
 * Time: 9:20
 */
class UserController extends DM_Controller
{
    /**
     * 执行添加好友任务
     */
    public function joinAction()
    {

    }

    public function checkMobileTaskAction()
    {
        $taskModel = new Model_Task();
        $numberModel = new Model_Gather_Number();
        $UrlID = $this->_getParam('UrlID', null);
        if($UrlID) {
            $UrlIDs = explode(",", $UrlID);
        }else{
            $UrlIDs = [];
        }
        $init_num = 0;
        do {
            $data = $numberModel->getNotCheck($UrlIDs);
            foreach ($data as $datum) {
                $new_data = [
                    'ID' => $datum['NumberID'],
                    'Number' => $datum['Number']
                ];
                $numberModel->setWeixin($datum['NumberID'], -2);
                $taskModel->add($taskModel::WEIXIN_FREE, $taskModel::TYPE_CHECK_MOBILE, $new_data);
            }
            if($init_num == 0){
                $init_num = count($data);
            }elseif(count($data) < $init_num){
                break;
            }
        }while(1);
    }

    public function checkMobileAction()
    {
        $taskModel = new Model_Task();
        $numberModel = new Model_Gather_Number();
        $ID = $this->_getParam('ID');
        $IsWeixin = $this->_getParam('IsWeixin');

        if($ID > 0){
            $where = "NumberID = '{$ID}'";
            $data = [
                'IsWeixin'    => $IsWeixin
            ];
            $numberModel->update($data, $where);

            //$taskModel->set($TaskID, ['Status' => $taskModel::STATUS_SUCCESS]);
        }
        $this->showJson(1);
    }
}