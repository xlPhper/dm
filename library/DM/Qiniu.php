<?php
/**
 * 七牛上传帮助类
 */
use Qiniu\Auth;
use Qiniu\Storage\UploadManager;
use Qiniu\Processing\PersistentFop;

class DM_Qiniu
{

    protected static $_accessKey = '44tBz-8k-QppRkh2xoaZlm2MbQlnBLHuo1rET5eI';
    protected static $_secretKey = 'pfHdgC-puTjmAXvbLO5OTLjKCXVQr6qZ9nQPDvHx';
    protected static $_bucket = 'wxgroup';
    protected static $_host = 'wxgroup-img.duomai.com';

    /**
     * 上传其他资源附件
     * @param unknown $filePath
     * @param number $isRemote
     * @param boolean $isTemp 是否临时文件
     * @return boolean|string
     */
    public static function upload($filePath,$isRemote = 0,$suffix='')
    {

        $auth = new Auth(self::$_accessKey, self::$_secretKey);
        $token = $auth->uploadToken(self::$_bucket);
        $uploadMgr = new UploadManager();

        if(!$isRemote) {
            $key = md5(time() . $filePath);
            if ($suffix){
                $key = $key.$suffix;
            }
            list($ret, $err) = $uploadMgr->putFile($token, $key, $filePath);
        }else{
            $key = md5(time());
            if ($suffix){
                $key = $key.$suffix;
            }
            list($ret, $err) = $uploadMgr->put($token, $key, file_get_contents($filePath));
        }
        if ($err !== null) {
            if (is_object($err) && get_class($err)=="Qiniu\Http\Error"){
                $err = $err->message();
            }
            throw new Exception($err);
        } else {
            !$isRemote && unlink($filePath);
            return "http://".self::$_host."/".$key;
        }
    }

    /**
     * 上传二进制流
     */
    public static function uploadBinary($fileName, $imgBinary)
    {
        $auth = new Auth(self::$_accessKey, self::$_secretKey);
        $token = $auth->uploadToken(self::$_bucket);
        $uploadMgr = new UploadManager();

        $uploadMgr->put($token, $fileName, $imgBinary);

        return "http://" . self::$_host . "/" . $fileName;
    }

    /**
     * 上传图片 加了图片样式的压缩过的webp
     * @param $filePath
     * @return string
     * @throws Exception
     */
    public static function uploadImage($filePath, $isRemote = 0, $filename=""){

        $auth = new Auth(self::$_accessKey, self::$_secretKey);

        if(!$isRemote) {
            $key = md5(time() . $filePath);
        }else{
            $key = md5(time());
        }
        if ($filename != ""){
            $key = $filename;
        }

        $pfop = "imageView2/0/q/75|imageslim|saveas/" . \Qiniu\base64_urlSafeEncode(self::$_bucket . ":" . $key);
        $policy = array(
            'persistentOps' => $pfop,
        );
        $token = $auth->uploadToken(self::$_bucket,null, 3600, $policy);
        $uploadMgr = new UploadManager();

        if(!$isRemote) {
            list($ret, $err) = $uploadMgr->putFile($token, $key, $filePath);
        }else{
            list($ret, $err) = $uploadMgr->put($token, $key, file_get_contents($filePath));
        }

        if ($err !== null) {
            if (is_object($err) && get_class($err)=="Qiniu\Http\Error"){
                $err = $err->message();
            }
            DM_Controller::Log('info','上传七年err.'.$err);
            return '';
//            throw new Exception($err);
        } else {
            !$isRemote && unlink($filePath);
            //默认返回缩略图的地址
            return "http://".self::$_host."/".$key;
        }
    }




    /**
     * 获取七牛上传token
     * @return string
     */
    public static function getToken()
    {
        $auth = new Auth(self::$_accessKey, self::$_secretKey);
        $token = $auth->uploadToken(self::$_bucket);
        return $token;
    }

    /*
     * 批量移动重命名
     */
    public static function batchMove($keyPairs=[]){

        $auth = new Auth(self::$_accessKey, self::$_secretKey);
        $bucketManager = new \Qiniu\Storage\BucketManager($auth);

        $srcBucket = self::$_bucket;
        $destBucket = self::$_bucket;
        $ops = $bucketManager->buildBatchMove($srcBucket, $keyPairs, $destBucket, true);
        list($ret, $err) = $bucketManager->batch($ops);
        if ($err !== null) {
            throw new Exception($err);
        } else {
            return $keyPairs;
        }
    }

    /**
     * 获取私有空间真正的地址
     * @param unknown $url
     * @return string
     */
    public static function privateDownloadUrl($url){
        $auth = new Auth(self::$_accessKey, self::$_secretKey);
        return $auth->privateDownloadUrl($url);
    }


    /**
     * 请求持久化视频截图，设置回调地址，七牛持久化处理成功 会通知回调，返回的数据里面有截图名称key
     * @param $key 视频文件名
     * @param $pid 唯一id.，可根据该id查询持久化处理状态
     */
    public static function requestVideoScreenshot($key, $fops="vframe/jpg/offset/7/w/480/h/360", $notify_url=null){
        $auth = new Auth(self::$_accessKey, self::$_secretKey);
        // 初始化
        $pfop = new PersistentFop($auth, self::$_bucket, null, $notify_url);
        list($pid, $err) = $pfop->execute($key, $fops);
        if ($err !== null) {
            throw new Exception($err);
        } else {
            return $pid;
        }
    }

    /**
     * 判断文件是否存在
     * @param $key
     * @return mixed
     * @throws Exception
     */
    public static function getSource($key){
        $auth = new Auth(self::$_accessKey, self::$_secretKey);
        $bucketManager = new \Qiniu\Storage\BucketManager($auth);
        list($ret, $err) = $bucketManager->stat(self::$_bucket, $key);
        if ($err !== null) {
//            throw new Exception($err);
            return "";
        } else {
            return file_get_contents("https://".self::$_host."/".$key);
        }
    }


}