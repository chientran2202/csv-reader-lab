<?php

namespace Leontec\CsvReaderLab;

class CSVReader {
    private $csvfile;
    private $from_encoding ='', $to_encoding = '';
    private $isEncoding = false;
    private $headerMapping = [];
    private $isSkipReadHeader = false;
    private $totalRows = 0;
    const DATA_ARRAY = 0;
    const DATA_STRING = 1;

    public function __construct($csvfile)
    {
        $this->csvfile = $csvfile;
    }
    public static function make($csvfile): self
    {
        return new self($csvfile);
    }

    public function encoding($from_encoding = 'SJIS', $to_encoding = 'UTF-8'): self
    {
        $this->from_encoding = $from_encoding;
        $this->to_encoding = $to_encoding;
        if ($from_encoding && $to_encoding) {
            $this->isEncoding = true;
        }
        return $this;
    }
    public function headerMapping($arr): self
    {
        $this->headerMapping = $arr;
        return $this;
    }
    public function skipReadHeader($is_skip = false): self
    {
        $this->isSkipReadHeader = $is_skip;
        return $this;
    }
    public function getTotalRows(){
        return $this->totalRows;
    }
    public function read($readType = self::DATA_STRING, callable $callback = null){
        if (!$callback | ($readType != self::DATA_STRING && $readType != self::DATA_ARRAY)) return;

        $csvContent = fopen($this->csvfile, 'r');
        if (!$csvContent) return;

        $index = 0;
        //handle header csv
        $arrHeaderCsv = fgetcsv($csvContent);
        if(!$this->isSkipReadHeader){
            if($this->isEncoding)
                $arrHeaderCsv = mb_convert_encoding($arrHeaderCsv, $this->to_encoding, $this->from_encoding);

            $result = $callback($index++, $arrHeaderCsv);
            if(isset($result) && !$result) return;
        }

        $arrKey = [];
        if($this->headerMapping){
            foreach ($arrHeaderCsv As $key => $value){
                $arrKey[] = $this->headerMapping[$value] ?? $value;
            }
        }

        $result = [];
        while (($dataRow = fgetcsv($csvContent)) !== false) {
            if (array(null) === $dataRow) continue;
            if ($this->isEncoding)
                $dataRow = mb_convert_encoding($dataRow, $this->to_encoding, $this->from_encoding);

            if ($arrKey && count($arrKey) == count($dataRow))
                $dataRow = array_combine($arrKey, $dataRow);

            $callbackResult = $callback($index++, $dataRow);
            if ($callbackResult === false) break;
            elseif ($callbackResult === true) continue;
            else {
                $result[] = $callbackResult;
            }
        }

        $this->totalRows = $index;
        return $result;
    }
}