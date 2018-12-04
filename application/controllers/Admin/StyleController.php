<?php

require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_StyleController extends AdminBase
{

    /**
     * 素材样式列表
     */
    public function listAction()
    {
        try{
            $styles_model = new Model_Styles();
            $res = $styles_model->findAll();

            $this->showJson(1,'素材样式列表',$res);
        }catch(Exception $e){
            $this->showJson(0,'抛出异常'.$e->getMessage());
        }
    }

    /**
     * 编辑详情
     */
    public function saveInfoAction()
    {
        $style_id = $this->_getParam('StyleID',null);

        try{
            $styles_model = new Model_Styles();
            $res = $styles_model->findByID($style_id);
            $res['Config'] = json_decode($res['Config'],1);
            $this->showJson(1,'编辑详情',$res);
        }catch(Exception $e){
            $this->showJson(0,'抛出异常'.$e->getMessage());
        }
    }

    /**
     * 添加/编辑
     */
    public function saveAction()
    {
        try{
            // 图片
            $style_id = $this->_getParam('StyleID',null);
            $backgroud_img = $this->_getParam('BackgroudImg',null);
            $origin_img = $this->_getParam('OriginImg',null);
            $front_img = $this->_getParam('FrontImg',null);

            // 配置
            $title = $this->_getParam('Title',null);
            $new_price = $this->_getParam('NewPrice',null);
            $qrcode = $this->_getParam('Qrcode',null);
            $status = $this->_getParam('Status',null);

//        $backgroud_img = "http://wxgroup-img.duomai.com/f175395425819d453b63dc582e1aeb5e";
//        $origin_img = "http://wxgroup-img.duomai.com/dae182f63baa5d19e5573469e22af353";
//        $front_img = "http://wxgroup-img.duomai.com/3053930f9e1e4e4985f52e9530f58224";

//        $img = $this->Synthesis($backgroud_img,$origin_img,$front_img);

            $config = [
                'background_img' => $backgroud_img,
                'origin_img' => $origin_img,
                'front' =>  [
                    'type'  =>  'front',
                    'img'   =>  $front_img,
                ],
                'title' =>  [
                    'type'  =>  'text',
                    'text'  =>  $title['Text'],
                    'font'  =>  $title['Font'],
                    'size'  =>  $title['Size'],
                    'color' =>  $title['Color'],
                    'coordinate' => ['x' => $title['X'], 'y' => $title['Y']],
                ],
                'new-price'  =>  [
                    'type'  =>  'price',
                    'font'  =>  $new_price['Font'],
                    'color' =>  $new_price['Color'],
                    'coordinate' => [
                        'nd1'   =>  ['x' => $new_price['ND1']['X'], 'y' => $new_price['ND1']['Y'],'size'  =>  $new_price['ND1']['Size']],
                        'nd2'   =>  ['x' => $new_price['ND2']['X'], 'y' => $new_price['ND2']['Y'],'size'  =>  $new_price['ND2']['Size']],
                        'nd3'   =>  ['x' => $new_price['ND3']['X'], 'y' => $new_price['ND3']['Y'],'size'  =>  $new_price['ND3']['Size']],
                        'nd4'   =>  ['x' => $new_price['ND4']['X'], 'y' => $new_price['ND4']['Y'],'size'  =>  $new_price['ND4']['Size']],
                        'd3'   =>  ['x' => $new_price['D3']['X'], 'y' => $new_price['D3']['Y'],'size'  =>  $new_price['D3']['Size']],
                        'd4'   =>  ['x' => $new_price['D4']['X'], 'y' => $new_price['D4']['Y'],'size'  =>  $new_price['D4']['Size']],
                        'd5'   =>  ['x' => $new_price['D5']['X'], 'y' => $new_price['D5']['Y'],'size'  =>  $new_price['D5']['Size']],
                    ],
                ],
                'qrcode'  =>  [
                    'type'  =>  'qrcode',
                    'height'  =>  $qrcode['Height'],
                    'coordinate' => ['x' => $qrcode['X'], 'y' => $qrcode['Y']],
                    'back_color'    =>  $qrcode['BackColor'],
                    'front_color'   =>  $qrcode['FontColor'],
                ]
            ];


            $titles = [
                'title' => 'ewstsetst',
                'new-price' => 9909,
                'qrcode' => 'http://baidu.com'
            ];

            $im = Model_Styles::initStyle($config, $titles)->buildImg($origin_img);

            $config =  json_encode($config);

            ob_start();
            imagepng($im);
            $contents = ob_get_contents();
            ob_end_clean();
            $img_url = DM_Qiniu::uploadBinary(time(), $contents);

            $model = new Model_Styles();

            $data = [
                'Config'=>$config,
                'ExampleImg'=>$img_url,
                'Status'=>$status,
            ];

            if ($style_id){
                $res = $model->insert($data);
            }else{
                $res = $model->update($data,['StyleID = ?'=>$style_id]);
            }
            if ($res){
                $this->showJson(1,'操作成功',$res);
            }else{
                $this->showJson(0,'操作失败');
            }

        }catch (Exception $e){
            $this->showJson(0,'抛出异常'.$e->getMessage());
        }

    }

    /**
     * 删除
     */
    public function delAction()
    {
        $style_id = $this->_getParam('StyleID',null);
        try{
            $styles_model = new Model_Styles();

            $res = $styles_model->delete(['StyleID = ?'=>$style_id]);

            $this->showJson(1,'删除成功',$res);
        }catch(Exception $e){
            $this->showJson(0,'抛出异常'.$e->getMessage());
        }
    }

    /**
     * 获取服务器支持的字体
     */
    public function getFontAction()
    {
        // 获取文件夹下的字体类型
//        $files = array();
//
//        $path = APPLICATION_PATH . "/data/font/";
//
//        if(is_dir($path)){
//            $dp = dir($path);
//        }else{
//            return null;
//        }
//        while ($file = $dp ->read()){
//            if($file !="." && $file !=".." && is_file($path.$file)){
//                $files[] = $file;
//            }
//        }
//        $dp->close();
        $res = [
            0 => [
                'Title'=>'微软雅黑',
                'Font' =>'msyh.ttc'
            ],
            1 => [
                'Title'=>'微软雅黑 Bold',
                'Font' =>'msyhbd.ttc'
            ],
            2 => [
                'Title'=>'微软雅黑 Light',
                'Font' =>'msyhl.ttc'
            ],
            3 => [
                'Title'=>'仿宋',
                'Font' =>'simfang.ttf'
            ]
        ];
        $this->showJson(1,'字体列表',$res);

    }

}