<?php

class Helper_Timer
{
    /**
     * 获取下一次执行时间
     */
    public static function getNextRunTime($startDate, $endDate, $options = [])
    {
        $nextRunTime = '0000-00-00 00:00:00';
        $netRunType = '';

        $d1 = strtotime($startDate);
        $d2 = strtotime($endDate);
        $diffDays = round(($d2-$d1)/3600/24);
        for ($i = 0; $i < $diffDays + 1; $i++) {
            $day = date('Y-m-d', strtotime($startDate) + 86400 * $i);

            if ($options['RunType'] == 'REFER') {
                $execTime = explode(',', $options['Timer']);
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
            } else {
                $execTime = explode(',', $options['Timer']);
                foreach ($execTime as $et) {
                    list($startHourMin, $endHourMin) = explode('-', $et);
                    // 间隔时间
                    $scopeSeconds = floor($options['Freq']['PerMin'] * 60 / $options['Freq']['Num']);
                    $totalNum = floor((strtotime($day . ' ' . $endHourMin)  - strtotime($day . ' ' . $startHourMin)) / $scopeSeconds);
                    for ($j = 0; $j < $totalNum; $j++) {
                        $etUnix = strtotime($day . ' ' . $startHourMin . ':00') + $j * $scopeSeconds;
                        if ($etUnix > time()) {
                            $et = date('Y-m-d H:i:s', $etUnix);
                            $nextRunTime = $et;
                            $netRunType = 'REFER';
                            break 2;
                        }
                    }
                }
                if ($nextRunTime != '0000-00-00 00:00:00') {
                    break;
                }
            }

        }

        return [$nextRunTime, $netRunType];
    }

    /**
     * 生成执行时间表
     */
    public static function generateRunTimes($startDate, $endDate, array $options)
    {
        $d1 = strtotime($startDate);
        $d2 = strtotime($endDate);
        $diffDays = round(($d2-$d1)/3600/24);
        $runTimes = [];
        for ($i = 0; $i < $diffDays + 1; $i++) {
            $day = date('Y-m-d', strtotime($startDate) + 86400 * $i);
            if ($options['RunType'] == 'REFER') {
                $timers = explode(',', $options['Timer']);
                foreach ($timers as $timer) {
                    if (false !== strpos($timer, ':')) {
                        $runTimes[] = $day . ' ' . $timer . ':00';
                    } else {
                        $runTimes[] = $day . ' ' . $timer . ':' . str_pad(mt_rand(0,60), 2, 0, STR_PAD_LEFT) . ':' . str_pad(mt_rand(0,60), 2, 0, STR_PAD_LEFT);
                    }
                }
            } else {
                $timers = explode(',', $options['Timer']);
                foreach ($timers as $timer) {
                    list($startHourMin, $endHourMin) = explode('-', $timer);
                    // 间隔时间
                    $scopeSeconds = floor($options['Freq']['PerMin'] * 60 / $options['Freq']['Num']);
                    $totalNum = floor((strtotime($day . ' ' . $endHourMin)  - strtotime($day . ' ' . $startHourMin)) / $scopeSeconds);
                    for ($j = 0; $j < $totalNum; $j++) {
                        $etUnix = strtotime($day . ' ' . $startHourMin . ':00') + $j * $scopeSeconds;
                        $runTimes[] = date('Y-m-d H:i:s', $etUnix);
                    }
                }
            }
        }

        return $runTimes;
    }

    /**
     * 前端显示设置
     */
    public static function getWebShowOptions()
    {
        // 指定时间, 整数为1小时内随机
        $options = [
            'RunType' => 'REFER',
            'Timer' => '14,15:10'
        ];

        // 指定 时间段/频率/次数
        $options = [
            'RunType' => 'SCOPE',
            'Timer' => '9:30-10:30,14:30-18:30',
            'Freq' => [
                'PerMin' => 5,
                'Num' => 2
            ]
        ];

        return $options;
    }

    /**
     * 验证时间合法的设置
     */
    public static function getValidOptions(array $options)
    {
        if (!isset($options['RunType']) || !isset($options['Timer']) || !in_array($options['RunType'], ['REFER', 'SCOPE'])) {
            return false;
        }
        if ($options['RunType'] == 'SCOPE' && (!isset($options['Freq']) || !isset($options['Freq']['PerMin']) || !isset($options['Freq']['Num']))) {
            return false;
        }
        $day = date('Y-m-d');
        if ($options['RunType'] == 'SCOPE') {
            $timers = explode(',', $options['Timer']);
            $tmpExecTimes = [];
            foreach ($timers as $timer) {
                $timerArr = explode('-', $timer);
                $timerArrCount = count($timerArr);
                if ($timerArrCount == 2) {
                    if ($timerArr[1] <= $timerArr[0]) {
                        return false;
                    }
                    if (false === strtotime($day . ' ' . $timerArr[0]) || false === strtotime($day . ' ' . $timerArr[1])) {
                        return false;
                    }
                    $tmpExecTimes[(int)$timerArr[0]] = $timer;
                } else {
                    return false;
                }
            }
            ksort($tmpExecTimes);
            $tmpOptions = [
                'RunType' => 'SCOPE',
                'Timer' => implode(',', $tmpExecTimes),
                'Freq' => [
                    'PerMin' => $options['Freq']['PerMin'],
                    'Num' => $options['Freq']['Num']
                ]
            ];
        } else {
            $timers = explode(',', $options['Timer']);
            $tmpExecTimes = [];
            foreach ($timers as $timer) {
                $et = trim($timer);
                $hmsArr = explode(':', $et);
                $hmsArrNum = count($hmsArr);
                if ($hmsArrNum == 1) {
                    if ($hmsArr[0] >= 0 && $hmsArr[0] <= 23) {
                        $tmpExecTimes[$hmsArr[0]] = $hmsArr[0];
                    } else {
                        return false;
                    }
                } elseif ($hmsArrNum == 2) {
                    if ($hmsArr[0] >= 0 && $hmsArr[0] <= 23 && $hmsArr[1] >= 0 && $hmsArr[1] <= 59) {
                        $tmpExecTimes[$hmsArr[0] . '.' . $hmsArr[1]] = $et;
                    } else {
                        return false;
                    }
                } else {
                    return false;
                }
            }
            ksort($tmpExecTimes);
            $tmpOptions = [
                'RunType' => 'REFER',
                'Timer' => implode(',', $tmpExecTimes)
            ];
        }

        return $tmpOptions;
    }
}