<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/8/8
 * Time: 11:26
 */


class Gather_DoubanController extends DM_Controller
{

    public function qrcodeAction()
    {
        $img = $this->_getParam('img');
        $img = trim($img);

        if(empty($img)){
            $this->showJson(0, "无图片地址");
        }
//        if(!preg_match('/favicon.ico/', $img)){
//            $redis = Helper_Redis::getInstance();
//            $redis->rPush(Model_Group_Tmps::CACHE_CHECK_QRCODE_IMG, $img);
//        }
        $this->showJson(1, "操作成功");
    }

    public function indexAction()
    {
        set_time_limit(0);

        $model = new Model_Group_Tmps();
        $doubanModel = new Model_Gather_Douban();
        $first_data = $doubanModel->getFirstList();
        foreach($first_data as $first_datum){
            $second_data = $doubanModel->getSecondList($first_datum);
            foreach($second_data as $second_datum){
                //判断是否存在
                $info = $model->getInfoByUrl('douban', $second_datum);
                if($info){
                    continue;
                }

                $imgs = $doubanModel->getImgs($second_datum);
                $qrcodes = $doubanModel->findGroupQrcode($imgs, true);
                if(empty($qrcodes)){
                    continue;
                }
                $insert_data = [
                    'Type'  =>  'douban',
                    'Url'   =>  $second_datum,
                    'QRCodeImg' =>  json_encode($qrcodes),
                    'AddTime'   =>  date("Y-m-d H:i:s"),
                ];
                $model->insert($insert_data);
                sleep($doubanModel->sleep);
            }
        }
    }

    public function index2Action()
    {

        set_time_limit(0);

        $model = new Model_Group_Tmps();

        $fields['cookie'] = 'll="118172"; bid=nPM63biUAE0; __utmc=30149280; _vwo_uuid_v2=D5D86EFF97FDE8CD18BBE439600472BD5|576846fab4363bbded99fc83e99d01a8; douban-fav-remind=1; __utmz=30149280.1532333794.3.3.utmcsr=baidu|utmccn=(organic)|utmcmd=organic; gr_user_id=4b643316-e0c8-4c9b-9b5b-bb275142d6a8; ap=1; __yadk_uid=WhFdiqzvJ4uqiJSGAhi9GwHBey1P9WEb; ct=y; ps=y; dbcl2="142105776:tPwg32E7Clk"; ck=iOcb; _pk_ref.100001.8cb4=%5B%22%22%2C%22%22%2C1533895672%2C%22https%3A%2F%2Fbook.douban.com%2F%22%5D; _pk_id.100001.8cb4=384a47b038dbfa47.1531983606.18.1533895672.1533886111.; _pk_ses.100001.8cb4=*; push_noty_num=0; push_doumail_num=0; __utma=30149280.1288063041.1531911425.1533885327.1533895672.19; __utmt=1; __utmv=30149280.14210; __utmb=30149280.2.10.1533895672';


        $base_url = 'https://www.douban.com/search?cat=1019&q=%E5%BE%AE%E4%BF%A1%E7%BE%A4';
        $content = DM_Controller::curl($base_url);

        phpQuery::newDocumentHTML($content, 'utf-8');
        $data = pq(".title a");

        foreach($data as $datum){
            $list_url = pq($datum)->attr("href");
            $content = DM_Controller::curl($list_url);
            phpQuery::unloadDocuments();
            phpQuery::newDocumentHTML($content);
            $article_data = pq("td.title a");
            foreach($article_data as $article_datum){
                $article_url = pq($article_datum)->attr("href");

                //判断是否存在
                $info = $model->getInfoByUrl('douban', $article_url);
                if($info){
                    continue;
                }

                $content = DM_Controller::curl($article_url);
                phpQuery::unloadDocuments();
                phpQuery::newDocumentHTML($content, 'utf-8');

                $imgs = pq("#link-report img");
                $img_url = [];
                foreach($imgs as $img){
                    $img_url[] = pq($img)->attr("src");
                }
                if(empty($img_url)){
                    continue;
                }
                $insert_data = [
                    'Type'  =>  'douban',
                    'Url'   =>  $article_url,
                    'QRCodeImg' =>  json_encode($img_url),
                    'AddTime'   =>  date("Y-m-d H:i:s"),
                ];
                $model->insert($insert_data);
                sleep(5);
            }
        }
    }

    public function testAction()
    {
        $url = "https://www.douban.com/group/topic/120209505/";
        $content = DM_Controller::curl($url);
        phpQuery::newDocumentHTML($content, 'utf-8');

        $imgs = pq("#link-report img");
        $img_url = [];
        foreach($imgs as $img){
             $img_url[] = pq($img)->attr("src");
        }

    }

    /**
     * 获取二维码内容
     */
    public function getQrcodeAction(){
        try{
            set_time_limit(0);
            $img = trim($this->_getParam('img', ''));
            $qrcode = '';
            if($img != ''){
                $qrcode = (new Model_Gather_Douban())->qrcode($img);
            }
            $this->showJson(1,'ok', $qrcode?$qrcode:'');
        }catch (Exception $e){
            $this->showJson(0,'抛出异常'.$e->getMessage());
        }
    }
}