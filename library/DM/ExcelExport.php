<?php
/**
 * PHPEXCEL导出封装
 */
include_once dirname(__FILE__) . "/../../vendor/autoload.php";
class DM_ExcelExport
{
    private $fileName = "";
    private $templateName = "";
    private $templateExplosion = "xls";
    private $fileInfo = array();
    private $firstColumn = 'A';
    private $firstRow = 2;
    private $activeSheetIndex = 0; //第一个活动表
    private $data = array();
    private $mergeCells = array();

    /**
     * 输出文件名 不带后缀
     * @param $fileName string
     */
    public function setOutputFileName($fileName) {
        if(!empty($fileName)){
            $this->fileName = $fileName.'.'.$this->templateExplosion;
        }
        return $this;
    }
    /**
     * 不设置 默认为模板名称+时间
     * @return string
     */
    private function getOutputFileName(){
        if(empty($this->fileName)){
            $this->setOutputFileName($this->templateName.date("YmdHis"));
        }
        return $this->fileName;
    }
    /**
     * 设置模板文件和类型 自动取模板名称  后缀必须与$fileType对应
     * @param $file string
     * @param $fileType string 导出类型
     * @return $this
     * @throws Exception
     */
    public function setTemplateFile($file, $fileType = 'Excel5') {
        if(!file_exists($file)) {
            throw new Exception('模板文件不存在');
        }
        $this->fileInfo = array(
            'file' => $file,
            'fileType' => $fileType
        );
        $info = pathinfo($file);
        $this->templateName  =  basename($file,'.'.$info['extension']);
        $this->templateExplosion = $info['extension'];
        return $this;
    }

    /**
     * 设置需要导出的数据
     * @param $data array 二维
     * @return $this
     */
    public function setData($data) {
        if(is_array($data)) {
            $this->data = $data;
        }
        return $this;
    }

    /**
     * 设置开始单元格, 类默认的属性值是A
     * @param $column string 列名 A-Z
     * @return $this
     */
    public function setFirstColumn($column) {
        $columnValue = ord(ucfirst($column));
        if($columnValue >= 65 && $columnValue <= 90) {
            $this->firstColumn = $column;
        }
        return $this;
    }

    /**
     * 设置起始行
     * @param $row int 默认 2
     * @return $this
     */
    public function setFirstRow($row) {
        if(intval($row) > 0){
            $this->firstRow = $row;
        }
        return $this;
    }

    /**
     * 设置活动页，类默认的属性值是0，就是第一页
     * @param $index int
     * @return $this
     */
    public function setActiveSheetIndex($index) {
        if( intval($index) > 0){
            $this->activeSheetIndex = $index;
        }
        return $this;
    }

    /**
     * 合并单元格
     * @param $x    行的开始位置
     * @param $y    列的开始位置
     * @param $gap  合并间隔
     * @param $data 标题
     */
    public function setMergeCells($x,$y,$gap,$data)
    {
        $n = chr(ord('A')+$x);
        foreach ($data as $t){
            $a = chr(ord($n));
            $b = chr(ord($a)+$gap);
            $this->mergeCells[] = [
                'head' => $a.$y,
                'value' => $a.$y.':'.$b.$y,
                'title' => $t['Title']
            ];
            $n = chr(ord($b)+1);
        }
        return $this;
    }


    /**
     * 导出
     * @throws PHPExcel_Reader_Exception
     */
    public function export() {
        //初始化PHPEXCEL引擎
        $PHPExcel = PHPExcel_IOFactory::createReader($this->fileInfo['fileType'])
            ->load($this->fileInfo['file']);
        $PHPExcel->setActiveSheetIndex($this->activeSheetIndex);

        if ($this->mergeCells){
            foreach ($this->mergeCells as $cells){
                $PHPExcel->getActiveSheet()->getStyle($cells['head'])->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                $PHPExcel->getActiveSheet()->setCellValue($cells['head'], $cells['title']);
                $PHPExcel->getActiveSheet()->mergeCells($cells['value']);
            }
        }

        //渲染数据
        $activeSheet  = $PHPExcel->getActiveSheet();
        //通过批注 首列必须有批注
        if((string)$activeSheet->getComment($this->firstColumn . ($this->firstRow - 1))) {
            $this->renderFromComment($activeSheet, $this->data);
        } else {
            $this->renderFromSortData($activeSheet, $this->data);
        }

        //文件输出
        $this->output($PHPExcel);
    }

    /**
     * @param $activeSheet PHPExcel_Worksheet
     * @param $data array 仅二维 key=>value
[
    ["key"=>"val","key2"=>"val2"],
    ["key"=>"val","key2"=>"val2"]
]
     */
    private function renderFromComment($activeSheet, $data) {
        foreach ($data as $row => $item){
            $serialComment = 0;
            for($column = 0; ;$column++) {
                $arrKey = (string)$activeSheet->getComment(chr(ord($this->firstColumn) + $column) .
                    ($this->firstRow - 1));
                if(!empty($arrKey)) {
                    $serialComment = 0;
                    $fillData = isset($item[$arrKey]) ? $item[$arrKey] : '';
                    if(is_string($fillData)) {
                        $fillData = preg_replace('/[\xF0-\xF7].../s', '[表情]', $fillData);
                        $fillData = preg_replace('/^=/s', "'=", $fillData);
                    }
                    $activeSheet->setCellValue(chr(ord($this->firstColumn) + $column) .
                        ($row + $this->firstRow), $fillData);
                } else {
                    $serialComment++;
                }
                if($serialComment == 4) {
                    break;//连续批注为空 不向下查找
                }
            }
        }
    }
    /**
     * @param $activeSheet PHPExcel_Worksheet
     * @param $data array 仅二维 key=>value或者value
     * 支持最后一个是二维数组的嵌套
     *
 [
     ["key"=>"val","key2"=>"val2","last"=>[
        ["key3"=>"val3","key4"=>"val4"]
     ],
     ["key"=>"val","key2"=>"val2","last"=>[
        ["key3"=>"val3","key4"=>"val4"]
     ]
 ]
     */
    private function renderFromSortData($activeSheet, $data) {
        foreach ($data as $row => $items){
            $column = 0;
            static $LastArrayAddRow = 0;//最后一个是数组增加的行数
            $itemsLength = count($items);
            foreach ($items as $k => $v){
                if(!is_array($v)) {
                    $v = preg_replace('/[\xF0-\xF7].../s', '[表情]', $v);
                    $v = is_numeric($v)&&strlen($v)>=15?$v." ":$v;
                    $activeSheet->setCellValue(chr(ord($this->firstColumn) + $column) .
                        ($row + $this->firstRow + $LastArrayAddRow), $v);
                }
                //中间数组跳过 仅支持最后一个是数组
                if($column == ($itemsLength-1) && is_array($v)) {
                    $items2 = $v;
                    $row2 = 0;
                    $count = count($v);
                    foreach ($items2 as $k2=>$v2) {
                        $column2 = 0;
                        foreach ($v2 as $kk=>$vv) {
                            $vv = preg_replace('/[\xF0-\xF7].../s', '[表情]', $vv);
                            $vv = preg_replace('/^=/s', "'=", $vv);
                            $activeSheet->setCellValue(chr(ord($this->firstColumn) + $column + $column2) .
                                ($row + $this->firstRow + $row2 + $LastArrayAddRow), $vv);
                            $column2++;
                        }
                        $row2++;
                    }
                    $LastArrayAddRow = $LastArrayAddRow+$count-1;
                }
                $column++;
            }
        }
    }

    /**
     * 输出数据
     * @param  PHPExcel $phpExcel
     * @throws PHPExcel_Reader_Exception
     */
    private function output($PHPExcel) {
        $objWriter = PHPExcel_IOFactory::createWriter($PHPExcel, $this->fileInfo['fileType']);
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control:must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type: application/vnd.ms-excel;");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");
        header("Content-Disposition:attachment;filename=".$this->getOutputFileName());
        header("Content-Transfer-Encoding:binary");
        $objWriter->save("php://output");
    }
}