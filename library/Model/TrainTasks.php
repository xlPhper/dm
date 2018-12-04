<?php
/**
 * Created by PhpStorm.
 * User: jakins
 * Date: 2018/11/13
 * Time: 10:48
 * 养号任务表
 */
class Model_TrainTasks extends DM_Model
{
    public static $table_name = "train_tasks";
    protected $_name = "train_tasks";
    protected $_primary = "TrainTaskID";

    const STATUS_ON = 1; //开启
    const STATUS_STOP = 2; //暂停

    public static function checkConfigData($configName, $data){
        switch ($configName){
            case 'AddFriendConfig':{
                //加好友
                $addFriendConfig = json_decode(trim($data), true);
                if(json_last_error() !== JSON_ERROR_NONE){
                    return [0, '加好友配置格式解析有误'];
                }
                if(!isset($addFriendConfig['Enable'])){
                    return [0, '加好友配置格式有误'];
                }
                if($addFriendConfig['Enable'] && (empty($addFriendConfig['DayNum']) || empty($addFriendConfig['TotalNum']))){
                    return [0, '加好友配置数据不能为空'];
                }
                break;
            }
            case 'ChatConfig':{
                //好友聊天
                $chatConfig = json_decode(trim($data), true);
                if(json_last_error() !== JSON_ERROR_NONE){
                    return [0, '好友聊天配置格式解析有误'];
                }
                if(!isset($chatConfig['Enable'])){
                    return [0, '好友聊天配置格式有误'];
                }
                if($chatConfig['Enable']){
                    if(empty($chatConfig['Time'])){
                        return [0, '好友聊天时间段数据有误'];
                    }
                    foreach ($chatConfig['Time'] as $chatTime){
                        if(empty($chatTime['Start']) || empty($chatTime['End'])){
                            return [0, '好友聊天时间段配置有误'];
                        }
                        if(!strtotime(date('Y-m-d '.$chatTime['Start'].':00')) || !strtotime(date('Y-m-d '.$chatTime['End'].':00')) || $chatTime['Start'] > $chatTime['End']){
                            return [0, '好友聊天时间有误,'.$chatTime['Start'].'-'.$chatTime['End']];
                        }
                    }
                }
                break;
            }
            case 'SendAlbumConfig':{
                //发朋友圈
                $sendAlbumConfig = json_decode(trim($data), true);
                if(json_last_error() !== JSON_ERROR_NONE){
                    return [0, '发朋友圈配置格式解析有误'];
                }
                if(!isset($sendAlbumConfig['Enable'])){
                    return [0, '发朋友圈配置格式有误'];
                }
                if($sendAlbumConfig['Enable']){
                    if(empty($sendAlbumConfig['MateTagIDs']) || !isset($sendAlbumConfig['Start']) || !isset($sendAlbumConfig['End']) || empty($sendAlbumConfig['DayNum'])){
                        return [0, '发朋友圈配置数据不能为空'];
                    }
                    if(!strtotime(date('Y-m-d '.$sendAlbumConfig['Start'].':00')) || !strtotime(date('Y-m-d '.$sendAlbumConfig['End'].':00')) || $sendAlbumConfig['Start'] > $sendAlbumConfig['End']){
                        return [0, '发朋友圈时间段有误,'.$sendAlbumConfig['Start'].'-'.$sendAlbumConfig['End']];
                    }
                }
                break;
            }
            case 'AlbumInteractConfig':{
                //朋友圈互动
                $albumInteractConfig = json_decode(trim($data), true);
                if(json_last_error() !== JSON_ERROR_NONE){
                    return [0, '朋友圈互动配置格式解析有误'];
                }
                if(!isset($albumInteractConfig['Enable'])){
                    return [0, '朋友圈互动配置格式有误'];
                }
                if($albumInteractConfig['Enable']){
                    if(!isset($albumInteractConfig['Start']) || !isset($albumInteractConfig['End']) || empty($albumInteractConfig['DayNum']) || empty($albumInteractConfig['LikeNum'])){
                        return [0, '朋友圈互动配置数据不能为空'];
                    }
                    if(!strtotime(date('Y-m-d '.$albumInteractConfig['Start'].':00')) || !strtotime(date('Y-m-d '.$albumInteractConfig['End'].':00')) || $albumInteractConfig['Start'] > $albumInteractConfig['End']){
                        return [0, '朋友圈互动时间段有误,'.$albumInteractConfig['Start'].'-'.$albumInteractConfig['End']];
                    }
                }
                break;
            }
        }
        return [1, ''];
    }
}