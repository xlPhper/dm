<?php

class Helper_Regex
{
    /**
     * 手机正则
     */
    const PHONE_REGEX = '/^1\d{10}$/';

    /**
     * 邮箱正则
     */
    const EMAIL_REGEX = '/^(\w)+(\.\w+)*@(\w)+((\.\w+)+)$/';

    /**
     * CNZZ统计代码正则
     */
    const CNZZ_REGEX = '/^<script\s+src="https:\/\/\S+.cnzz.com\/z_stat.php\?id=\d+&web_id=\d+"\s+language="JavaScript"><\/script>$/';

    /**
     * 是否是手机号
     * @param $phone
     * @return bool
     */
    public static function isPhone($phone)
    {
        return preg_match(self::PHONE_REGEX, $phone) > 0;
    }

    /**
     * 是否为邮箱
     * @param $email
     * @return bool
     */
    public static function isEmail($email)
    {
        return preg_match(self::EMAIL_REGEX, $email) > 0;
    }

    /**
     * 是否为合法的cnzz代码
     * @param $quotaCode
     * @return bool
     */
    public static function isValidCnzzCode($quotaCode)
    {
        return preg_match(self::CNZZ_REGEX, $quotaCode) > 0;
    }

    /**
     * 判断是否都是中文
     * @param $str
     * @return int
     */
    public static function isAllChinese($str)
    {
        $len = preg_match('/^[\x{4e00}-\x{9fa5}]+$/u',$str);
        if ($len)
        {
            return true;
        }
        return false;
    }
}