<?php

require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_SendweixinController extends AdminBase
{
    /**
     * 送好友请求的微信列表
     */
    public function listAction()
    {
        $category_id = $this->getParam('CategoryID', '');
        $send_weixin_model = new Model_Sendweixin();
        $res = $send_weixin_model->getCategory($category_id);
        $this->showJson(1, '', $res);
    }

    /**
     * 筛选未被录入的微信号
     */
    public function findWeixinsAction()
    {
        $send_weixin_model = new Model_Sendweixin();
        $res = $send_weixin_model->getSendWeixins();
        $this->showJson(1, '', $res);
    }

    /**
     * 添加好友请求的微信号
     */
    public function addAction()
    {
        set_time_limit(0);
        $weixins = $this->getParam('Weixins', '');
        $categoryId = $this->getParam('CategoryID', '');
        $type = $this->getParam('Type', 1);
        if (empty($weixins)) {
            $this->showJson(0, '请输入手机号');
        }
        // 参数处理
        $arr = explode("\n", $weixins);
        $len = count($arr);
        $insertNum = 0;
        $repeatNum = 0;
        // 数据库
        $model = new Model_Sendweixin();

        foreach ($arr as $ph) {
            try{
                if ($ph != '' && $ph != "\n" && $ph != "\r") {
                    // 去除左右两边空格
                    $values = trim($ph, ' ');

                    // 判断微信是否已存数据库
                    $isRepeat = $model->findWeixin($categoryId,$values);
                    if ($isRepeat == false) {
                        $model->insert(array('Weixin' => $values, 'CategoryID' => $categoryId,'Type'=>$type,'AddDate'=>date('Y-m-d H:i:s')));
                        $insertNum++;
                    } else {
                        $repeatNum++;
                    }
                }
            }catch (Exception $e){
                $this->showJson(0,'抛出异常'.$e->getMessage());
            }
        }

        $data = [
            'insert_num' => $insertNum,
            'repeat_num' => $repeatNum,
            'total_num' => $len
        ];
        $this->showJson(1, '添加成功', $data);
    }



}