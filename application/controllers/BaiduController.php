<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/12
 * Time: 10:00
 */

//require_once APPLICATION_PATH . '/../library/Baidu/ApiOcr.php';

class BaiduController extends DM_Controller
{
    const APP_ID = '11296539';
    const API_KEY = 'v8mUIuguUaNK2NaZo4OY1P1e';
    const SECRET_KEY = '2fe56e23a6633218820486e3e20c67f9';

    public function audioAction()
    {
        if(isset($_FILES)){
            $upload_path = APPLICATION_PATH . "/data/upload/";
            $filename = uniqid("audio");
            $filepath = $upload_path . $filename;
            //接受文件上传
            move_uploaded_file($_FILES["audio"]["tmp_name"],$filepath);
            //将mp3音频转换
            $pcm_file = $filepath.".pcm";
            $cmd = "ffmpeg -y  -i {$filepath}  -acodec pcm_s16le -f s16le -ac 1 -ar 16000 {$pcm_file}";
            exec($cmd);

            $client = new AipSpeech(self::APP_ID, self::API_KEY, self::SECRET_KEY);

            $data = $client->asr(file_get_contents($pcm_file), 'pcm', 16000, array(
                'dev_pid' => 1737,  //英语
            ));
            unlink($filepath);
            unlink($pcm_file);
            if(isset($data['err_no'])){
                if($data['err_no'] == 0){
                    $this->showJson(1, $data['result']);
                }else{
                    $this->showJson(0, $data['err_no'].":".$data['err_msg']);
                }
            }else{
                $this->showJson(0, "无效的返回");
            }
        }
    }

    public function textAction()
    {
        $model = new Model_Baidu();
        $groupModel = new Model_Group_Tmps();
        $groupData = $groupModel->getAll();

        foreach($groupData as $groupDatum) {
            if(!empty($groupDatum['Title'])){
                continue;
            }
            $data = $model->text($groupDatum['Url']);
            $update = [
                'Title' =>  $data['words_result'][0]['words'] ?? ""
            ];
            $where = "QrID = '{$groupDatum['QrID']}'";
            $groupModel->update($update, $where);
        }
    }
}