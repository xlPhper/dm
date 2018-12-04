<?php
/**
 * Created by PhpStorm.
 * User: tim
 * Date: 2018/10/19
 * Time: 上午11:33
 */

$d = [];
for($i = 0; $i < 24; $i++){
    if($i < 10){
        $hour = "0{$i}";
    }else{
        $hour = $i;
    }
    for($j = 0; $j < 60; $j++){
        if($j < 10){
            $d[] = "{$hour}:0{$j}";
        }else{
            $d[] = "{$hour}:{$j}";
        }
    }
}

echo implode(",", $d);