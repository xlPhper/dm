<?php

class Model_Ad extends DM_Model
{
    public static $table_name = "ads";
    protected $_name = "ads";
    protected $_primary = "AdID";

    const TYPE_BDHOT = 'BDHOT';
    const TYPE_GZH = 'GZH';
    const TYPE_URL = 'URL';
    const TYPE_MINA = 'MINA';

    /**
     * 获取下一次执行时间 和 类型
     */
    public static function getNextRunTime($startDate, $endDate, array $execTime = [])
    {
        $nextRunTime = '0000-00-00 00:00:00';
        $netRunType = '';

        $d1 = strtotime($startDate);
        $d2 = strtotime($endDate);
        $diffDays = round(($d2-$d1)/3600/24);
        for ($i = 0; $i < $diffDays + 1; $i++) {
            $day = date('Y-m-d', strtotime($startDate) + 86400 * $i);
            foreach ($execTime as $et) {
                // 具体到分
                if (false !== strpos($et, ':')) {
                    $netRunType = 'REFER';
                    $et = $day . ' ' . $et . ':00';
                } else {
                    $netRunType = 'RAND';
                    $et = $day . ' ' . $et . ':00:00';
                }
                if (strtotime($et) > time()) {
                    $nextRunTime = $et;
                    break 1;
                }
            }
            if ($nextRunTime != '0000-00-00 00:00:00') {
                break;
            }
        }

        return [$nextRunTime, $netRunType];
    }

}