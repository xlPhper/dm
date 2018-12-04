<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/8/9
 * Time: 13:49
 */

require_once APPLICATION_PATH . "/../library/phpQuery/phpQuery.php";

class Model_Gather_Douban extends Model_Gather
{

    public $cookie = 'll="118172"; bid=nPM63biUAE0; __utmc=30149280; _vwo_uuid_v2=D5D86EFF97FDE8CD18BBE439600472BD5|576846fab4363bbded99fc83e99d01a8; douban-fav-remind=1; __utmz=30149280.1532333794.3.3.utmcsr=baidu|utmccn=(organic)|utmcmd=organic; gr_user_id=4b643316-e0c8-4c9b-9b5b-bb275142d6a8; ap=1; __yadk_uid=WhFdiqzvJ4uqiJSGAhi9GwHBey1P9WEb; ct=y; ps=y; dbcl2="142105776:tPwg32E7Clk"; ck=iOcb; _pk_ref.100001.8cb4=%5B%22%22%2C%22%22%2C1533895672%2C%22https%3A%2F%2Fbook.douban.com%2F%22%5D; _pk_id.100001.8cb4=384a47b038dbfa47.1531983606.18.1533895672.1533886111.; _pk_ses.100001.8cb4=*; push_noty_num=0; push_doumail_num=0; __utma=30149280.1288063041.1531911425.1533885327.1533895672.19; __utmt=1; __utmv=30149280.14210; __utmb=30149280.2.10.1533895672';
    static $_onLineWeixinIDs = ['234', '67', '225']; //线上可发送任务的微信

    public function getFirstList()
    {
        $start = 0;
        $pagesize = 20;
        $data = [];

        do {
            $url = "https://www.douban.com/j/search?q=%E5%BE%AE%E4%BF%A1%E7%BE%A4&start=" . ($start * $pagesize) . "&cat=1019";
            echo $url . "\n";
            $content = DM_Controller::curl($url, ['cookie' => $this->cookie]);

            $file_data = json_decode($content, true);

            Zend_Debug::dump($file_data);
            exit;

            foreach ($file_data['items'] as $datum) {
                phpQuery::unloadDocuments();
                phpQuery::newDocumentHTML($datum);
                $url = pq("a.nbg")->attr("href");
                //获取真实地址
                $data[] = $this->getRealUrl($url);
            }
            if (count($file_data['items']) < $pagesize) {
                break;
            }
            $start++;
            sleep($this->sleep);
        } while (1);
        return $data;
    }

    public function getSecondList($url)
    {
        $start = 0;
        $pagesize = 25;
        $data = [];
        do {
            $url .= "discussion?start=" . ($start * $pagesize);

            $content = DM_Controller::curl($url);
            phpQuery::unloadDocuments();
            phpQuery::newDocumentHTML($content);

            $article_data = pq("table.olt tr:gt(0)");
            foreach ($article_data as $article_datum) {
                $a = pq($article_datum)->find("td:first a");
                $a_url = pq($a)->attr("href");
                $b = pq($article_datum)->find("td:last");
                $date = pq($b)->text();
                Zend_Debug::dump($date);
                $check_date = date("Y") . "-" . $date . ":00";
                if ($this->checkExpireTime($check_date)) {
                    break;
                }
//                $data[] = [
//                    'url'   =>  $a_url,
//                    'date'  =>  $date,
//                ];
                $data[] = $a_url;
            }
            $start++;
            sleep($this->sleep);
        } while (1);
        return $data;
    }

    public function getImgs($url)
    {
        $content = DM_Controller::curl($url);
        phpQuery::unloadDocuments();
        phpQuery::newDocumentHTML($content, 'utf-8');

        $imgs = pq("#link-report img");
        $img_url = [];
        foreach ($imgs as $img) {
            $img_url[] = pq($img)->attr("src");
        }
        return $img_url;
    }

    /**
     * @return array
     * @throws Zend_Exception
     * 初始化豆瓣网页抓取,并返回待发送获取html任务数组
     */
    public function initGatherTask()
    {
        $pageSize = 20;
        //进行豆瓣抓取
        $url = 'https://www.douban.com/group/search?q=%E5%BE%AE%E4%BF%A1%E7%BE%A4&cat=1019&sort=relevance';
        $response = DM_Controller::curl($url, null, false);
        preg_match('/data-total-page=\"(.*)\"/', $response, $matchTotalPage);
        if (!empty($matchTotalPage)) {
            //找到页码
            $page = $matchTotalPage[1];
            for ($i = 0; $i < $page; $i++) {
                try {
                    $this->dealData($url . '&start=' . ($i * $pageSize), Model_Linkurl::GATHER_URL_TYPE_SEARCH, Model_Linkurl::GATHER_CHANNEL_DOUBAN);
                } catch (Exception $e) {
                    DM_Log::create('doubanGatherDeal')->add('init gather task error:'.$e->getMessage());
                }
            }
        } else {
            DM_Log::create('doubanGatherDeal')->add('未查询到此链接搜索结果,Url:' . $url);
        }
    }

    /**
     * @param $url
     * @return array
     * @throws Zend_Exception
     * 设置豆瓣讨论小组列表页面地址抓取
     */
    public function initGatherDiscussionTask($url, $curlConfig)
    {
        $pageSize = 25;
        $url = $url.'discussion';
        try {
            $response = DM_Controller::curl($url, null, false, $curlConfig);
            preg_match('/data-total-page=\"(.*)\"/', $response, $matchTotalPage);
            if (!empty($matchTotalPage)) {
                //找到页码
                $page = $matchTotalPage[1];
                if($this->checkFirstDiscussionExpire($response)){
                    //如果第一页的讨论列表页，有最后回应时间超时的,则只获取第一页,后续页码就不去获取html了，节省抓取
                    $page = 1;
                };
                for ($i = 0; $i < $page; $i++) {
                    try {
                        $this->dealData($url . '?start=' . ($i * $pageSize), Model_Linkurl::GATHER_URL_TYPE_LIST, Model_Linkurl::GATHER_CHANNEL_DOUBAN);
                    } catch (Exception $e) {
                        DM_Log::create('doubanGatherDeal')->add('init discussions task error:'.$e->getMessage());
                    }
                }
            } else if(preg_match('/<table class=\"olt\">/', $response)){
                try {
                    $this->dealData($url, Model_Linkurl::GATHER_URL_TYPE_LIST, Model_Linkurl::GATHER_CHANNEL_DOUBAN);
                } catch (Exception $e) {
                    DM_Log::create('doubanGatherDeal')->add('init discussion task error:'.$e->getMessage());
                }
            }else{
                DM_Log::create('doubanGatherDeal')->add('未查询到此讨论页下的列表地址数据,Url:' . $url);
            }
        } catch (Exception $e) {
            throw new Exception('gather discussion html error：'.$e->getMessage());
        }

    }

    /**
     * 讨论列表页面处理
     * @param $html
     * @return array 返回待发送获取html任务数组
     */
    public function discussionListHtmlDeal($html){
        if(!preg_match('/<\/html>/', $html)){
            $html = '<html>'.$html.'</html>';
        }
        phpQuery::unloadDocuments();
        phpQuery::newDocumentHTML($html);
        $article_data = pq("table.olt tr:gt(0)");
        foreach ($article_data as $article_datum) {
            $a = pq($article_datum)->find("td:first a");
            $a_url = pq($a)->attr("href");
            $b = pq($article_datum)->find("td:last");
            $date = pq($b)->text();
            $check_date = date("Y") . "-" . $date . ":00";
            if ($this->checkExpireTime($check_date)) {
                break;
            }
            try {
                $this->dealData($a_url, Model_Linkurl::GATHER_URL_TYPE_DETAIL, Model_Linkurl::GATHER_CHANNEL_DOUBAN);
            } catch (Exception $e) {
                DM_Log::create('doubanGatherDeal')->add('init detail task error:'.$e->getMessage());
            }
        }
    }

    /**
     * @param $html
     * @return bool
     * 查看讨论列表页的第一页是否最后评论时间在7天前
     */
    public function checkFirstDiscussionExpire($html){
        if(!preg_match('/<\/html>/', $html)){
            $html = '<html>'.$html.'</html>';
        }
        phpQuery::unloadDocuments();
        phpQuery::newDocumentHTML($html);
        $article_data = pq("table.olt tr:gt(0)");
        foreach ($article_data as $article_datum) {
            $b = pq($article_datum)->find("td:last");
            $date = pq($b)->text();
            $check_date = date("Y") . "-" . $date . ":00";
            if ($this->checkExpireTime($check_date)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $html
     * 搜索页列表抓取处理
     * 讨论列表地址html获取
     */
    public function searchHtmlDeal($html, $curlConfig){
        if(!preg_match('/<\/html>/', $html)){
            $html = '<html>'.$html.'</html>';
        }
        phpQuery::unloadDocuments();
        phpQuery::newDocumentHTML($html);
        $urls = pq(".result")->find(".content")->find('.title a');
        foreach ($urls as $url){
            $this->initGatherDiscussionTask($url->getAttribute('href'), $curlConfig);
        }
    }

    /**
     * @param $html
     * 详情页图片抓取处理
     */
    public function detailHtmlDeal($html){
        if(!preg_match('/<\/html>/', $html)){
            $html = '<html>'.$html.'</html>';
        }
        phpQuery::unloadDocuments();
        phpQuery::newDocumentHTML($html, 'utf-8');
        $imgs = pq("#link-report img");
        foreach ($imgs as $img) {
            //将详情页图片丢入二维码验证队列
            try{
                DM_Log::create('doubanGatherDeal')->add('push img to redis :'.pq($img)->attr("src"));
                Helper_Redis::getInstance()->rPush(Model_Group_Tmps::CACHE_CHECK_QRCODE_IMG, Model_Group_Tmps::CHANNEL_DOUBAN.'|||'.pq($img)->attr("src"));
            }catch(Exception $e){
                Helper_Redis::getInstance(true);
                DM_Log::create('doubanGatherDeal')->add('push redis error:'.$e->getMessage());
            }

        }
    }

    /**
     * @param $url
     * @param $type
     * @param $channel
     * @throws Exception
     * 处理url数据库信息,设置状态为待执行
     */
    public function dealData($url, $type, $channel){
        $model = new Model_Linkurl();
        try {
            $res = $model->fetchRow(['Url = ?' => $url]);
            if (!$res) {
                $linkID = $model->insert([
                    'Url' => $url,
                    'Type' => $type,
                    'Channel' => $channel,
                    'AddDate' => date('Y-m-d H:i:s')
                ]);
            } else {
                $linkID = $res->LinkurlID;
            }
        } catch (Exception $e) {
            if ($e->getCode() == '23000') {
                $res = $model->fetchRow(['Url = ?' => $url]);
                $linkID = $res->LinkurlID;
            }
            //其他错误
            throw new Exception('db error:' . $e->getMessage());
        }
//        if ($res && $res->UpdateDate >= date('Y-m-d 00:00:00')) {
//            //如果这条地址今天内已经抓取过html,则不再下发任务
//            throw new Exception('此地址今日已抓取过,LinkurlID'.$linkID);
//        }

        $model->update(['Status' => Model_Linkurl::STATUS_PENDING], ['LinkurlID = ?' => $linkID]);
    }
}