<?php
/**
 * Created by PhpStorm.
 * User: jakins
 * Date: 2018/10/8
 * Time: 14:27
 * 检测二维码图片，并存入数据库
 */
class TaskRun_CheckGroupQrcode extends DM_Daemon
{
    const CRON_SLEEP = 1000000;
    const SERVICE='checkGroupQrcode';
    const QRCODE_DECODE_URL = 'http://qrcode.testdoc.cn/urlDecode?qrcodeUrl=';

    /**
     * 执行Daemon任务
     *
     */
    protected function run()
    {
        $this->done();
    }

    protected function done()
    {
        try{
            set_time_limit(0);
            $img = Helper_Redis::getInstance()->lpop(Model_Group_Tmps::CACHE_CHECK_QRCODE_IMG);

            if($img && !preg_match('/favicon.ico/', $img)){
                self::getLog()->add('Img:'.$img.',memory:'.DM_Daemon_MemoryCheck::formatMemory(memory_get_usage()));
                self::getLog()->flush();

                $channel = 'douban';
                //判断是否存在来源
                $img_arr = explode("|||", $img);
                if(isset($img_arr[1]) && $img_arr[1]){
                    $channel = $img_arr[0];
                    $img = $img_arr[1];
                }

                $model = new Model_Group_Tmps();

                $info = $model->getInfoByUrl($channel, $img);
                if($info){
                    self::getLog()->add('此图片已存在');
                }else{
                    //判断图片格式
                    $mime = DM_Controller::curl_getType($img);
                    if(preg_match("/image/", $mime)){
                        //是图片才处理
                        if(preg_match("/webp/", $mime)) {
                            //webp先上传到七牛转换成png
                            $originImg = DM_Qiniu::upload($img,1,'preview-');
                            $imgpath = $originImg . '?imageView2/2/format/png';
                        }else{
                            $imgpath = $img;
                        }
                        $qrcodeResponse = DM_Controller::curl(self::QRCODE_DECODE_URL.urlencode($imgpath), null, false);
                        $res = json_decode($qrcodeResponse, true);
                        if(!empty($res) && isset($res['f']) && $res['f'] == 1){
                            $qrcode = empty($res['d'])?'':$res['d'];
                            self::getLog()->add('qrcode:'.$qrcode);
                            $baiduModel = new Model_Baidu();

                            if(preg_match("/\/wechat\//", $qrcode)) {
                                $insert_data = [
                                    'Channel' => $channel,
                                    'Type' => 'u',
                                    'Url' => $img,
                                    'QRCodeImg' => $qrcode,
                                    'AddTime' => date("Y-m-d H:i:s"),
                                    'Title' =>  '',
                                ];
                                $model->insert($insert_data);
                            }elseif(preg_match("/\/g\//", $qrcode)){
                                $insert_data = [
                                    'Channel' => $channel,
                                    'Type' => 'g',
                                    'Url' => $img,
                                    'QRCodeImg' => $qrcode,
                                    'AddTime' => date("Y-m-d H:i:s"),
                                ];
                                $data = $baiduModel->text($img);
                                $insert_data['Title'] = isset($data['words_result'])?$data['words_result'][0]['words']:'';
                                $model->insert($insert_data);
                            }elseif(preg_match("/\/weixin\//", $qrcode)){
                                $insert_data = [
                                    'Channel' => $channel,
                                    'Type' => 'other',
                                    'Url' => $img,
                                    'AddTime' => date("Y-m-d H:i:s"),
                                    'Title' =>  '',
                                ];
                                $model->insert($insert_data);
                            }else{
                                self::getLog()->add('非二维码图片');
                            }
                        }else{
                            self::getLog()->add('解析有误:'.$qrcodeResponse);
                        }
                    }else{
                        self::getLog()->add('此地址不是图片');
                    }
                    self::getLog()->add('end,memory:'.DM_Daemon_MemoryCheck::formatMemory(memory_get_usage()));
                }
            }
        } catch (Exception $e){
            self::getLog()->add('error:'.$e->getMessage().','.$img);
            self::getLog()->flush();
        }
    }

    protected function init()
    {
        try{
            $redisconfig = Zend_Registry::get("config")['redis'];
            Helper_Redis::init($redisconfig);
            parent::init();
            self::getLog()->add("\n\n**********************定时更新**************************");
        } catch (Exception $e){
            self::getLog()->add("init error:".$e->getMessage());
        }
        self::getLog()->flush();
    }

    /**
     * 发现新版本的事件
     */
    protected function onNewReleaseFind()
    {
        self::getLog()->add('Found new release: '.$this->getReleaseCheck()->getRelease().', will quit for update.');
        die();
    }

    /**
     * 系统运行过程检测到内存不够的事件
     */
    protected function onOutOfMemory()
    {
        self::getLog()->add('System find that daemon will be out of memory, will quit for restart.');
        die();
    }

}