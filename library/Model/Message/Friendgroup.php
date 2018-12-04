<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/11
 * Time: 13:52
 */
class Model_Message_Friendgroup extends DM_Model
{
    public static $table_name = "msg_friendgroup";
    protected $_name = "msg_friendgroup";
    protected $_primary = "ID";


    const TYPE_TEXT = 'TEXT';

    const TYPE_IMG = 'IMG';

    const TYPE_VIDEO = 'VIDEO';

    const TYPE_LINK = 'LINK';

    const STATUS_NOTUSED = 'NOTUSED';

    const STATUS_USED = 'USED';

    /**
     * 发送朋友圈
     */
    public function add($data)
    {
        $data['AddDate'] = date("Y-m-d H:i:s");
        $data['Status'] = self::STATUS_USED;
        $this->insert($data);
        $taskModel = new Model_Task();
        $param = [
            'TYPE'  =>  $data['Type'],
            'TEXT'  =>  $data['Text'],
            'MEDIA' =>  $data['Media']
        ];
        $taskModel->add($data['WeixinID'], $taskModel::TYPE_SEND_FRIENDGROUP, $param);
    }
}