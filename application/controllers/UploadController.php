<?php
/**
 * Created by PhpStorm.
 * User: Ekko
 * Date: 2018/7/24
 * Ekko: 17:58
 */

class UploadController extends DM_Controller
{

    public function getTokenAction(){
        $token = DM_Qiniu::getToken();
        $this->showJson(1, "获取成功", $token);
    }

    public function uploadAction(){
        try {
            if (!isset($_FILES['file'])) {
                $upload_max_filesize = ini_get('upload_max_filesize');
                $this->showJson(0, "没有找到上传文件或者上传的文件超过了php.ini中upload_max_filesize选项限制的值:" . $upload_max_filesize, $_FILES);
            }
            if ($_FILES['file']['error'] != UPLOAD_ERR_OK){
                switch ($_FILES['file']['error']){
                    case UPLOAD_ERR_INI_SIZE:
                        $this->showJson(0, "上传的文件超过了 php.ini 中 upload_max_filesize选项限制的值");
                        break;
                    case UPLOAD_ERR_FORM_SIZE:
                        $this->showJson(0, "上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值");
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $this->showJson(0, "没有文件被上传");
                        break;
                }
            }

            //判断上传文件
            $file_data = getimagesize($_FILES['file']['tmp_name']);
            if(is_array($file_data) && strpos($file_data['mime'],'image') !== false)
            {
                $imageUrl = DM_Qiniu::uploadImage($_FILES['file']['tmp_name']);
            }else{
                $file = basename ($_FILES['file']['name']);
                $suffix = substr($file, strrpos($file, '.'));
                $imageUrl = DM_Qiniu::upload($_FILES['file']['tmp_name'],0,$suffix);
            }
            $this->showJson(1, "上传成功", $imageUrl, array("FileType" => isset($file_data['mime'])?$file_data['mime']:$_FILES['file']['type']));
        } catch (Exception $e) {
            $this->showJson(0, "upload抛出异常：". $e->getMessage());
        }
    }
}