<?php

require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_PhoneController extends AdminBase
{
    /**
     * 手机列表
     */
    public function listAction()
    {
        $category_id = $this->getParam('CategoryID', '');
        $phone_model = new Model_Phones();
        $res = $phone_model->getCategory($category_id);
        $this->showJson(1, '', $res);
    }

    /**
     * 筛选未被录入的手机号
     */
    public function findPhonesAction()
    {
        $phone_model = new Model_Phones();
        $res = $phone_model->getPhones();
        $this->showJson(1, '', $res);
    }

    /**
     * 添加手机
     */
    public function addAction()
    {
        set_time_limit(0);
        $phone = $this->getParam('Phone', '');
        $category_id = $this->getParam('CategoryID', '');
        if (empty($phone)) {
            $this->showJson(0, '请输入手机号');
        }
        // 参数处理
        $arr = explode("\n", $phone);
        $len = count($arr);
        $insert_num = 0;
        $repeat_num = 0;
        // 数据库
        $model = new Model_Phones();
        $model->getAdapter()->beginTransaction();

        foreach ($arr as $ph) {
            if ($ph != '' && $ph != "\n" && $ph != "\r") {
                // 去除左右两边空格
                $values = trim($ph, ' ');
                // 判断手机格式
                if (!preg_match('/^1([0-9]{9})/', $values)) {
                    $this->showJson(0, '手机号：' . $values . '格式错误');
                    $model->getAdapter()->rollBack();
                }
                // 判断手机是否已存数据库
                $is_repeat = $model->findPhone($values,$category_id);
                if ($is_repeat == false) {
                    $model->insert(array('Phone' => $values, 'CategoryID' => $category_id,'CreateDate' => date('Y-m-d H:i:s')));
                    $insert_num++;
                } else {
                    $repeat_num++;
                }
            }
        }
        $model->getAdapter()->commit();
        $data = [
            'insert_num' => $insert_num,
            'repeat_num' => $repeat_num,
            'total_num' => $len
        ];
        $this->showJson(1, '添加成功', $data);
    }

    // 删除手机
    public function delAction()
    {
        $type = $this->getParam('Type', 1);
        $group_model = new Model_Phones();
        switch ($type) {
            case 1:
                $phone_id = $this->getParam('PhoneID', '');
                if (empty($phone_id)) {
                    $this->showJson(0, '无手机ID信息');
                }
                $res = $group_model->delete(['PhoneID = ?' => $phone_id]);
                if (!$res) {
                    $this->showJson(0, '删除失败');
                }
                break;
            case 2:
                $phones = $this->getParam('Phones', '');
                if (empty($phones)) {
                    $this->showJson(0, '无手机ID信息');
                }
                $data = json_decode($phones);
                $len = count($data);
                for ($i = 0; $i < $len; $i++) {
                    $res = $group_model->delete(['Phone = ?' => $data[$i]]);
                    if (!$res) {
                        $this->showJson(0, '删除失败');
                    }
                }
                break;
        }

        $this->showJson(1, '删除成功');
    }


}