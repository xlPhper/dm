<?php
/**
 * Created by PhpStorm.
 * User: Tim
 * Date: 2018/4/24
 * Time: 23:58
 */

require_once APPLICATION_PATH . "/../library/phpqrcode/phpqrcode.php";

class GroupController extends DM_Controller
{

    public function createTaskAction()
    {
        $WeixinID = $this->_getParam('WeixinID', Model_Task::WEIXIN_FREE);
        $Name = $this->_getParam('Name');
        $Num = $this->_getParam('Num', 10);

        $taskModel = new Model_Task();
        $Password = [];
        for($i = 0; $i < $Num; $i++){
            $Password[] = rand(0,9).rand(0,9).rand(0,9).rand(0,9);
        }
        $data = [
            'Name'  =>  $Name,
            'Password'  =>  $Password
        ];
        $taskModel->add($WeixinID, 'GroupCreate', $data);
        $this->showJson(1);
    }

    /**
     * 加入微信群
     */
    public function joinGroupTaskAction()
    {
        $QRCode = $this->_getParam('QRCode');
        $CategoryID = $this->_getParam('CategoryID',null,1);
        $Type = $this->_getParam('Type', 'OTHER');
        $groupModel = new Model_Group();
        $flag = $groupModel->add($QRCode,$CategoryID,$Type);
        if($flag){
            $this->showJson(1);
        }else{
            $this->showJson(0);
        }
    }

    public function listGroupAction()
    {
        $page      = $this->getParam('Page', 1);
        $pagesize  = $this->getParam('Pagesize', 100);
        $groupModel = new Model_Group();
        $select = $groupModel->select()->setIntegrityCheck(false);
        $select = $select->from($groupModel->getTableName() ." as g");
        $select->joinLeft('group_categorys as gc','g.CategoryID = gc.CategoryID','gc.Name as CategoryName');
        $res = $groupModel->getResult($select, $page, $pagesize);
        $this->showJson(1,'',$res);
    }

    public function delGroupAction()
    {
        $GroupID      = $this->getParam('GroupID');
        $groupModel = new Model_Group();
        $result = $groupModel->delete(['GroupID = ?' => $GroupID]);
        if($result){
            $this->showJson(1);
        }else{
            $this->showJson(0);
        }
    }


    /**
     * 退出微信群
     */
    public function quitGroupTaskAction()
    {
        $ChatroomID = $this->_getParam('ChatroomID');
        $DeviceID = $this->_getParam('DeviceID');
        $groupModel = new Model_Group();
        $flag = $groupModel->quit($ChatroomID,$DeviceID);
        if($flag){
            $this->showJson(1);
        }else{
            $this->showJson(0);
        }
    }

    /**
     * 拉人
     */
    public function groupMemberInAction()
    {
        $ChatroomID = $this->_getParam('ChatroomID');
        $DeviceID = $this->_getParam('DeviceID');
        $Friends = $this->_getParam('Friends');
        $groupModel = new Model_Group();
        $flag = $groupModel->MemberIn($ChatroomID,$Friends,$DeviceID);
        if($flag){
            $this->showJson(1);
        }else{
            $this->showJson(0);
        }
    }

    /**
     * 微信群加入后同步信息
     */
    public function weixinInGroupAction()
    {
        $WeixinID = $this->_getParam('WeixinID');
        $QRCode = $this->_getParam('QRCode');
        $Type = $this->_getParam('Type');
        $groupModel = new Model_Group();
        $findCode = $groupModel->inGroup($WeixinID,$QRCode, $Type);
        if ($findCode){
            $this->showJson(1);
        }else{
            $this->showJson(0);
        }
    }

    /**
     * 更新群信息
     */
    public function updateAction()
    {
        $groupModel = new Model_Group();
        $groupWeixinModel = new Model_Group_Weixin();

        //检测设备与微信是否为同一个用户
        $DeviceNO = $this->_getParam('DeviceNO');
        $Weixin = $this->_getParam('Weixin');
        $info = $this->checkDeviceAndWeixin($DeviceNO, $Weixin);
        if($info === false){
            return false;
        }
        $data = [];
        //$data['WeixinID'] = $info['weixinInfo']['WeixinID'];
        $data['ChatroomID'] = trim($this->_getParam('ChatroomID'));
        $data['Name'] = trim($this->_getParam('Name'));
        $data['UserNum'] = intval($this->_getParam('UserNum'));
        $data['QRCode'] = trim($this->_getParam('QRCode'));
        $data['IsSelf'] = intval($this->_getParam('IsSelf', Model_Group::TYPE_OTHER));

        $GroupID = $groupModel->updateInfo($data);

        if($GroupID) {
            $groupWeixinModel->join($GroupID, $data['WeixinID']);
            $this->showJson(1);
        }else{
            $this->showJson(0);
        }
    }

    /**
     * 更新二维码
     * @return bool
     */
    public function updateQrcodeAction()
    {
        $DeviceNO = $this->_getParam('DeviceNO');
        $Weixin = $this->_getParam('Weixin');
        $ChatroomID = $this->_getParam('ChatroomID');
        $qrcode = $this->_getParam('QRCode');

        $info = $this->checkDeviceAndWeixin($DeviceNO, $Weixin);
        if($info === false){
            $this->showJson(0);
        }

        $groupModel = new Model_Group();
        $groupInfo = $groupModel->getInfoByChatroom($ChatroomID);
        //检查群信息及安全性
        if(!isset($groupInfo['GroupID']) || $groupInfo['WeixinID'] <> $info['weixinInfo']['WeixinID']){
            $this->showJson(0);
        }
        $groupModel->updateQrcode($groupInfo['GroupID'], $qrcode);
        $this->showJson(1);
    }

    /**
     * 添加微信群二维码
     * @return bool
     */
    public function addQRCodeAction()
    {
        $qrcodeimg = $this->_getParam('QRCodeImg');
        $model = new Model_Group_Tmps();
        $res = $model->insert(['QRCodeImg'=>$qrcodeimg,'ExpireTime'=>date('Y-m-d H:i:s',strtotime('+7 day'))]);
        if ($res){
            $this->showJson(1);
        }else{
            $this->showJson(0);
        }
    }


    /**
     * 测试图片合成
     */
    public function styleAction()
    {
        if(!isset($_GET['debug'])) {
            header("Content-type: image/png");
        }
        $originImg = "http://wxgroup-img.duomai.com/dae182f63baa5d19e5573469e22af353";

        $config = [
            'front' =>  [
                'type'  =>  'front',
                'img'   =>  '/data/style/3_front.png',
            ],
            'title' =>  [
                'type'  =>  'text',
                'font'  =>  'msyhbd.ttc',
                'size'  =>  26,
                'color' =>  "c167fd",
                'coordinate' => ['x' => 160, 'y' => 760],
            ],
            'new-price'  =>  [
                'type'  =>  'price',
                'font'  =>  'msyhbd.ttc',
                'color' =>  "fff33f",
                'coordinate' => [
                    'nd1'   =>  ['x' => 625, 'y' => 215,'size'  =>  55],
                    'nd2'   =>  ['x' => 610, 'y' => 210,'size'  =>  52],
                    'nd3'   =>  ['x' => 605, 'y' => 205,'size'  =>  40],
                    'nd4'   =>  ['x' => 605, 'y' => 190,'size'  =>  28],
                    'd3'   =>  ['x' => 610, 'y' => 200,'size'  =>  40],
                    'd4'   =>  ['x' => 610, 'y' => 195,'size'  =>  30],
                    'd5'   =>  ['x' => 610, 'y' => 190,'size'  =>  25],
                ],
            ],
            'qrcode'  =>  [
                'type'  =>  'qrcode',
                'height'  =>  155,
                'coordinate' => ['x' => 40, 'y' => 540],
                'back_color'    =>  'ffffff',
                'front_color'   =>  '000000',
            ]
        ];



        $titles = [
            'title' => 'ewstsetst',
            'new-price' => 9909,
            'qrcode' => 'http://baidu.com'
        ];
        $im = Model_Styles::initStyle($config, $titles)->buildImg($originImg);

        if(!isset($_GET['debug'])) {
//            ob_start();
//            imagejpeg($im);
//            $contents = ob_get_contents();
//            ob_end_clean();
//            $img = DM_Qiniu::uploadBinary(time(), $contents);
//            var_dump($img);
            imagepng($im);
        }else{
            echo json_encode($config);
        }

    }

    /**
     * 预览功能
     */
    public function previewAction()
    {
        $styleId = (int)$this->_getParam('StyleID');
        if ($styleId < 1) {
            $this->showJson(0, '样式id非法');
        }
        $title = trim($this->_getParam('ProductTitle', ''));
        if ($title === '') {
            $this->showJson(0, '商品标题必填');
        }
        $salePrice = trim($this->_getParam('SalePrice', '0'));
        if ($salePrice <= 0) {
            $this->showJson(0, '售价非法');
        }
        $link = trim($this->_getParam('ProductLink', ''));
        if ('' === $link) {
            $this->showJson(0, '商品二维码链接非法');
        }
        $imageUrl = trim($this->_getParam('ImageUrl', ''));
        if ('' === $imageUrl) {
            $this->showJson(0, '图片链接非法');
        }

        $style = (new Model_Styles())->fetchRow(['StyleID = ?' => $styleId]);
        $styleConfig = json_decode($style->Config, 1);

        $titles = [
            'title' => $title,
            'new-price' => $salePrice,
            'qrcode' => $link
        ];

        try {

            $im = Model_Styles::initStyle($styleConfig, $titles)->buildImg($imageUrl);

            ob_start();
            imagejpeg($im);
            $contents = ob_get_contents();
            ob_end_clean();

            $imgName = 'preview-' . md5($title . time());
            $imgUrl = DM_Qiniu::uploadBinary($imgName, $contents);

            imagedestroy($im);

            $this->showJson(1, '操作成功', ['ImgUrl' => $imgUrl]);
        } catch (\Exception $e) {
            $this->showJson(0, '操作失败,err:'.$e->getMessage());
        }

    }
    /**
     * 更新任务
     */
    public function qrJoinUpdateAction()
    {
        $JoinID = (int)$this->_getParam("JoinID",0);
        $Code = trim($this->_getParam("Code"));
        $IsError = (int)$this->_getParam('IsError',0);
        $Error = $this->_getParam('Error',"");
        try {
            DM_Controller::Log("qrJoinUpdate","{$Code}:error {$IsError},{$Error}");
            if(!$IsError){
                $model = new Model_Group_QrJoin();
                $model->update(["SuccessNum" => new Zend_Db_Expr("SuccessNum + 1")],"JoinID = {$JoinID}");
            }
            $this->showJson(1,'保存成功');
        } catch (Exception $e) {
            $this->showJson(0,"保存失败");
        }
    }
}