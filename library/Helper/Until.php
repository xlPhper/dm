

<?php
class Helper_Until
{
    /**
     * xml转成array
     * @param $xml
     * @return array
     */
    public static function xmlToArray($xml)
    {
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $values;
    }

    /**
     * 数组是否有指定字段
     */
    public static function hasReferFields($arr, array $fields)
    {
        foreach ($fields as $field) {
            if (!isset($arr[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * 执行时间是否合法 2018-09-11 12(随机) 或 2018-09-11 12:00(定时)
     */
    public static function getExecTimeType($execTime)
    {
        $execTime = explode(' ', $execTime);
        if (count($execTime) != 2) {
            return false;
        }

        $ymd = $execTime[0];
        $hms = $execTime[1];

        if (false === strtotime($ymd)) {
            return false;
        }

        $hmsArr = explode(':', $hms);
        $hmsArrNum = count($hmsArr);
        if ($hmsArrNum == 1) {
            if ($hmsArr[0] >= 0 && $hmsArr[0] <= 23) {
                return 'RAND';
            } else {
                return false;
            }
        } elseif ($hmsArrNum == 2) {
            if ($hmsArr[0] >= 0 && $hmsArr[0] <= 23 && $hmsArr[1] >= 0 && $hmsArr[1] <= 59) {
                return 'REFER';
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 获取唯一时间
     * 逻辑: 如果不在数组中, 则直接返回时间, 并将时间放入数组; 如果在数组中, 则递归获取
     */
    public static function getUniqueTime($time, &$msgCreateTimes)
    {
        if (in_array($time, $msgCreateTimes)) {
            $time += 1;
            return self::getUniqueTime($time, $msgCreateTimes);
        } else {
            $msgCreateTimes[] = $time;
            return $time;
        }
    }


    /**
     * 替换聊天消息内容
     */
    public static function replaceMsgContent($content, $weixin, $friendAccount)
    {
        if (strpos($content, '#用户昵称#') !== false) {
            // select * from weixin_friends where WeixinID=(select WeixinID from weixins where Weixin='wxid_vccuh3t6xjp622') and Account='wxid_evw18nbq12hc22'
            $wxModel = new Model_Weixin();
            $wxfModel = new Model_Weixin_Friend();
            $s = $wxModel->fromSlaveDB()->select()
                ->from($wxModel->getTableName(), 'WeixinID')
                ->where('Weixin = ?', $weixin)->limit(1);
            $ss = $wxfModel->fromSlaveDB()->select()
                ->from($wxfModel->getTableName(), 'NickName')
                ->where('WeixinID = ?', $s)
                ->where('Account = ?', $friendAccount);
            $r = $wxfModel->fetchRow($ss);
            $friendNick = $r ? $r['NickName'] : '朋友';
            $wxModel->restoreOriginalAdapter();
        } else {
            $friendNick = '朋友';
        }

        return strtr($content, [
            '#日期#' => date('Y-m-d'),
            '#用户昵称#' => $friendNick
        ]);
    }

    /**
     * @param $startTime 2018-10-10 00:00:00
     * @param $endTime 2018-12-10 12:00:00
     * @return false|string
     * 返回两个时间之间随机一个时间点
     */
    public static function getRandTime($startTime, $endTime){
        $randTime = '0000-00-00 00:00:00';
        $start = strtotime($startTime);
        $end = strtotime($endTime);
        if($start && $end && $start <= $end){
            $randTime = date('Y-m-d H:i:s', $start + mt_rand(0, $end-$start));
        }
        return $randTime;
    }

}