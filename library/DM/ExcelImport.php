<?php
/**
 * PHPEXCEL导入封装
 */
include_once dirname(__FILE__) . "/../../vendor/autoload.php";
class DM_ExcelImport
{
    public function getData()
    {
        $tmp_file = $_FILES ['file'] ['tmp_name'];
        $file_types = explode(".", $_FILES ['file'] ['name']);
        $file_type = $file_types [count($file_types) - 1];
        /*判别是不是.xls文件，判别是不是excel文件*/
        if (strtolower($file_type) != "xlsx" && strtolower($file_type) != "xls") {
            throw new Exception('不是Excel文件，重新上传');
        }
        $objReader = null;
        if (strtolower($file_type) == 'xls'){
            $objReader = \PHPExcel_IOFactory::createReader('Excel5');
        } else {
            $objReader = \PHPExcel_IOFactory::createReader('Excel2007');
        }
        if(!is_uploaded_file($tmp_file)){
            throw new Exception('上传失败');
        }
        $objPHPExcel = $objReader->load($tmp_file);
        $objWorksheet = $objPHPExcel->getActiveSheet();
        $highestRow = $objWorksheet->getHighestRow();
        $highestColumn = $objWorksheet->getHighestColumn();
        $highestColumnIndex = \PHPExcel_Cell::columnIndexFromString($highestColumn);
        $excelData = array();
        for ($row = 1; $row <= $highestRow; $row++) {
            $d = [];
            $serialComment = 0;
            for ($col = 0; $col < $highestColumnIndex; $col++) {
                $key = trim((string)$objWorksheet->getComment(chr(ord("A") + $col)."1"));
                $val = (string)$objWorksheet->getCellByColumnAndRow($col, $row)->getValue();
                if(!$key) $key = $col;
                $d[$key] = $val;
                if($val == ""){
                    $serialComment ++;
                }
            }
            if($serialComment == $highestColumnIndex){
                break;//全部列为空 不向下查找
            }
            $excelData[$row] = $d;
        }
        return $excelData;
    }
}