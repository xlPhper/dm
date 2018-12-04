<?php
/**
 * Created by PhpStorm.
 * User: jakins
 * Date: 2018/10/10
 * Time: 15:24
 * 派单统计表
 */
class Model_Distribution extends DM_Model
{
    public static $table_name = "distribution";
    protected $_name = "distribution";
    protected $_primary = "DistributionID";

    //平台
    static $_platform = array(
        '1' => '公众号',
        '2' => '服务号',
        '3' => '微博',
        '4' => '抖音'
    );
    
    const DISTRIBUTION_PLATFORM_GZH = 1;
    const DISTRIBUTION_PLATFORM_SEVICE = 2;
    const DISTRIBUTION_PLATFORM_WEIBO = 3;
    const DISTRIBUTION_PLATFORM_DOUYIN = 4;
}