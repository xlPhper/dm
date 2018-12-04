<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/5
 * Time: 13:31
 */

require(APPLICATION_PATH . '/../library/phpQuery/phpQuery.php');

class Gather_NumberController extends DM_Controller
{
    public function runAction()
    {


        $urlModel = new Model_Gather_Url();
        $numberModel = new Model_Gather_Number();

        $UrlID = $this->_getParam('UrlID', null);
        if($UrlID) {
            $UrlIDs = explode(",", $UrlID);
        }else{
            $UrlIDs = [];
        }

        $urlData = $urlModel->getNotGather($UrlIDs);

        $reset = $this->_getParam('reset', false);

        if($reset){
            $urlModel->reset();
        }

        foreach($urlData as $datum){
            switch($datum['Mark']){
                case 'YD':
                    $numbers = $this->gatherYD($datum['Url']);
                    break;
                case 'LT':
                    $numbers = $this->gatherLT($datum['Url']);
                    break;
            }
            foreach ($numbers as $number){
                $numberModel->save($datum['UrlID'], $number);
            }
            $urlModel->setStatus($datum['UrlID'], 1);
        }
    }

    public function gatherYD($url)
    {
        $page = 1;
        $init_num = 0;
        $numbers = [];
        do{

            $gather_url = str_replace("[page]", $page, $url);
            $content = file_get_contents($gather_url);

            phpQuery::newDocumentHTML($content, 'utf-8');

            $tds = pq('td.name');
            foreach($tds as $td){
                $n = trim(pq($td)->text());
                if(in_array($n, $numbers)){
                    break;
                }
                $numbers[] = $n;
            }
            if($page > 30){
                break;
            }
            $page++;
            phpQuery::$documents = [];
        }while(1);
        return $numbers;
    }

    public function gatherLT($url)
    {
        Zend_Debug::dump($url);
        $content = file_get_contents($url);
        $content = str_replace(["jsonp_queryMoreNums(",")"], "", $content);
        $arr = json_decode($content, true);
        $numbers = [];
        foreach($arr['numArray'] as $a){
            if($a > 10000000000) {
                $numbers[] = $a;
            }
        }
        return $numbers;
    }

    public function initYidongAction()
    {
        $urlModel = new Model_Gather_Url();
        $pro = [100,551,230,591,200,771,931,851,311,371,898,270,731,451,431,250,791,240,471,951,971,210,280,531,351,290,220,991,891,871];
        foreach($pro as $p){
            $url = "http://shop.10086.cn/list/134_{$p}_{$p}_0_0_0_0.html";
            phpQuery::newDocumentHTML(file_get_contents($url), 'utf-8');

            $citys = pq("a.ac_city_choose");
            foreach($citys as $city){
                $citycode = pq($city)->attr("location");
                $city_data = [
                    'Mark'  =>  'YD',
                    'Name'  =>  pq($city)->text(),
                    'Url'   =>  "http://shop.10086.cn/list/134_{$citycode}_0_0_0_0.html?p=[page]"
                ];
                $urlModel->insert($city_data);
            }
        }
    }

    public function initLiantongAction()
    {
        $pro = [];
        $pro[] = ['p' => '11', 's' => '110', 'name' => '北京'];
        $pro[] = ['p' => '30', 's' => '305', 'name' => '安徽'];
        $pro[] = ['p' => '83', 's' => '831', 'name' => '重庆'];
        $pro[] = ['p' => '38', 's' => '380', 'name' => '福建'];
        $pro[] = ['p' => '51', 's' => '510', 'name' => '广东'];
        $pro[] = ['p' => '87', 's' => '870', 'name' => '甘肃'];
        $pro[] = ['p' => '59', 's' => '591', 'name' => '广西'];
        $pro[] = ['p' => '85', 's' => '850', 'name' => '贵州'];
        $pro[] = ['p' => '71', 's' => '710', 'name' => '湖北'];
        $pro[] = ['p' => '74', 's' => '741', 'name' => '湖南'];
        $pro[] = ['p' => '18', 's' => '188', 'name' => '河北'];
        $pro[] = ['p' => '76', 's' => '760', 'name' => '河南'];
        $pro[] = ['p' => '50', 's' => '501', 'name' => '海南'];
        $pro[] = ['p' => '97', 's' => '971', 'name' => '黑龙江'];
        $pro[] = ['p' => '34', 's' => '340', 'name' => '江苏'];
        $pro[] = ['p' => '90', 's' => '901', 'name' => '吉林'];
        $pro[] = ['p' => '75', 's' => '750', 'name' => '江西'];
        $pro[] = ['p' => '91', 's' => '910', 'name' => '辽宁'];
        $pro[] = ['p' => '10', 's' => '101', 'name' => '内蒙古'];
        $pro[] = ['p' => '88', 's' => '880', 'name' => '宁夏'];
        $pro[] = ['p' => '70', 's' => '700', 'name' => '青海'];
        $pro[] = ['p' => '17', 's' => '170', 'name' => '山东'];
        $pro[] = ['p' => '31', 's' => '310', 'name' => '上海'];
        $pro[] = ['p' => '19', 's' => '190', 'name' => '山西'];
        $pro[] = ['p' => '84', 's' => '841', 'name' => '陕西'];
        $pro[] = ['p' => '81', 's' => '810', 'name' => '四川'];
        $pro[] = ['p' => '13', 's' => '130', 'name' => '天津'];
        $pro[] = ['p' => '89', 's' => '890', 'name' => '新疆'];
        $pro[] = ['p' => '79', 's' => '790', 'name' => '西藏'];
        $pro[] = ['p' => '86', 's' => '860', 'name' => '云南'];
        $pro[] = ['p' => '36', 's' => '360', 'name' => '浙江'];
        $login_url2 = "http://num.10010.com/NumApp/chseNumList/init";
        $city_data = [];
        $urlModel = new Model_Gather_Url();
        foreach($pro as $p) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $login_url2);
            //curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_COOKIE, "mallcity={$p['p']}|{$p['s']}"); //读取cookie
            $result = curl_exec($ch);
            curl_close($ch);

            //Zend_Debug::dump($result);

            phpQuery::newDocumentHTML($result, 'utf-8');
            $citys = $tds = pq('a.cityS');

            foreach($citys as $city){
                $citycode = pq($city)->attr("value");
                $city_data = [
                    'Mark'  =>  'LT',
                    'Name'  =>  pq($city)->text(),
                    'Url'   =>  "http://num.10010.com/NumApp/NumberCenter/qryNum?callback=jsonp_queryMoreNums&provinceCode={$p['p']}&cityCode={$citycode}&monthFeeLimit=0&groupKey=19055153&searchCategory=3&net=01&amounts=410&codeTypeCode=&searchValue=&qryType=02&goodsNet=4&_=1528351772573"
                    ];
                $urlModel->insert($city_data);
            }
            phpQuery::$documents = [];
        }
        Zend_debug::dump($city_data);
    }
}