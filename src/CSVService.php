<?php

namespace Leontec\CsvReaderLab;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class CSVService {
    public static function csvToArray($fileName = '', $header = [], $onlyNeedHeader = [], $mustEncoding = true, $delimiter = ',')
	{
		$onlyNeedHeader = count($onlyNeedHeader) ? $onlyNeedHeader : $header;

		if (!file_exists($fileName) || !is_readable($fileName))
			return false;

		$data = array();
		if (($handle = fopen($fileName, 'r')) !== false) {
			while (($row = fgetcsv($handle, null, $delimiter)) !== false) {
				$data[] = self::genData($header, $row, $onlyNeedHeader, $mustEncoding);
			}
			fclose($handle);
		}
		return $data;
	}

	public static function csvToArrayNoHeader($fileName = '', $mustEncoding = true, $delimiter = ',')
	{
		if (!file_exists($fileName) || !is_readable($fileName))
			return false;

		$data = array();
		if (($handle = fopen($fileName, 'r')) !== false) {
			return fgetcsv($handle, null, $delimiter);
			while (($row = fgetcsv($handle, null, $delimiter)) !== false) {
				$data[] = $row;
			}
			fclose($handle);
		}
		$data = self::reGenData($data, $mustEncoding);
		return $data;
	}

	public static function reGenData($data, $mustEncoding)
	{
		$genData = [];
		foreach ($data as $key => $value) {
			if ($key > 0) {
				$tempData = [];
				foreach ($value as $k => $vl) {
					$tempData[$data[0][$k]] = $mustEncoding ? self::convertCharacterToUtf8($vl) : $vl;
				}
				$genData[] = $tempData;
			}
		}
		return $genData;
	}

	public static function genData($header, $row, $onlyNeedHeader, $mustEncoding)
	{
		$rowData = [];
		foreach ($onlyNeedHeader as $key => $value) {
			$s = array_search($value, $header);
			if ($s >= 0) {
				$rowData[$value] = isset($row[$s]) ? ($mustEncoding ? self::convertCharacterToUtf8($row[$s]) : $row[$s]) : null;
			} else $rowData[$value] = null;
		}
		return $rowData;
	}

	public static function convertCharacterToUtf8($string)
	{
		$encoding = mb_detect_encoding($string, 'UTF-8, SJIS', true); //'UTF-8, SJIS, ISO-8859-1, WINDOWS-1252, WINDOWS-1251'
		$stringSJISWIN = mb_convert_encoding($string, "UTF-8", "SJIS-win");

		if ($encoding === false) return $stringSJISWIN;
		else if ($encoding != 'UTF-8') {
			try {
				$stringSJIS = iconv($encoding, 'UTF-8//IGNORE', $string);
			} catch (\Throwable $th) {
				$stringSJIS = self::convertToUTF8($encoding);
			}

			if ( //bắt lỗi các trường hợp của sjis-win replace bằng sjis
				strlen($stringSJIS) < strlen($stringSJISWIN)
			)
				return $stringSJIS;
			return $stringSJISWIN;
		}
		return $string; // or $stringSJIS
	}

	public static function convertToUTF8($text)
	{
		$encoding = mb_detect_encoding($text, mb_detect_order(), false);
		if ($encoding == "UTF-8") $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
		return iconv(mb_detect_encoding($text, mb_detect_order(), false), "UTF-8//IGNORE", $text);
	}

	public static function convertCharacterToSjis($string, $encoding = 'SJIS')
	{
		//"SJIS-win"
		if (!is_array($string))
			$string = mb_convert_encoding($string, $encoding, "UTF-8");
		else {
			$data = [];
			foreach ($string as $value) {
				$data[] = mb_convert_encoding("\"$value\"", $encoding, "UTF-8");
			}
			$string = $data;
		}
		return $string;
	}
	
	public static function convertCharacterToSjisWin($string, $encoding = 'SJIS-win')
	{
		//"SJIS-win"
		if (!is_array($string))
			$string = mb_convert_encoding($string, $encoding, "UTF-8");
		else {
			$data = [];
			foreach ($string as $value) {
				$data[] = mb_convert_encoding("\"$value\"", $encoding, "UTF-8");
			}
			$string = $data;
		}
		$string_re = str_replace("\n", "\r\n", $string);
		return $string_re;
	}

	public static function arrayToCSV($datas, $fileName = '', $encoding = 'SJIS', $delimiter = ',', $header = [], $storageFolder = '')
	{
		$csvFile = tmpfile();
		$csvPath = stream_get_meta_data($csvFile)['uri'];

		$file = fopen($csvPath, 'w');
		fputcsv($file, self::convertCharacterToSjis(array_keys($header), $encoding), $delimiter, chr(0));

		foreach ($datas as $data) {
			$dataField = [];
			foreach ($header as $key => $value) {
				$fields = explode('.', $value);
				if (count($fields) == 1) $dataField[] = $data->$value ?: '';
				else {
					$model = $fields[0];
					$field = $fields[1];
					$dataField[] = $data->$model ? $data->$model->$field : '';
				}
			}
			fputcsv($file, self::convertCharacterToSjis($dataField), $delimiter, chr(0));
		}
		fclose($file);

		$fileSaved = Storage::putFileAs($storageFolder, $csvPath, $fileName);
		return [
			'status' => true,
			'path' => $fileSaved, //storage_path($fileSaved),
			'folder' => $storageFolder
		];
	}

	public static function removeHeaderFile($fileName)
	{
		if (File::exists($fileName)) {
			$file = file_get_contents($fileName);
			$arr = explode("\r\n", $file);
			if (isset($arr[0])) unset($arr[0]);
			$string = implode("\r\n", $arr); //'\n\r'
			file_put_contents($fileName, $string);
			return true;
		}
		return false;
	}

	public static function addHeaderFile($fileName, $headers, $delimiter = ',', $isConvertCharacter = false)
	{
		$header = implode($delimiter, $headers);
		if ($isConvertCharacter) $header = self::convertCharacterToSjis($header);
		if (File::exists($fileName)) {
			file_put_contents($fileName, $header . "\r\n" . file_get_contents(iconv('UTF-8', 'big-5//TRANSLIT', $fileName)));
			return true;
		}
		return false;
	}

	public static function changeDelimiter($fileName, $fromDelimiter = "\t", $toDelimiter = ',')
	{
		if (File::exists($fileName)) {
			file_put_contents($fileName, str_replace($fromDelimiter, $toDelimiter, file_get_contents($fileName)));
			return true;
		}
		return false;
	}

	public static function exportFileFormat($fileName, $headers, $fromDelimiter = "\t", $toDelimiter = ',')
	{
		if (File::exists($fileName)) {
			$file = file_get_contents($fileName);
			$arr = explode(PHP_EOL, $file);
			//change header
			if (isset($arr[0])) $arr[0] = implode($toDelimiter, $headers);
			$string = implode(PHP_EOL, $arr);
			file_put_contents($fileName, self::convertCharacterToSjis(str_replace($fromDelimiter, $toDelimiter, $string)));
			return true;
		}
		return false;
	}

    public static function exportRawToCsv($fileName, $datas)
	{
        $csvData = implode(PHP_EOL, $datas);
        Storage::append($fileName, self::convertCharacterToSjis($csvData));
	}
	
	public static function exportRawToCsvWin($fileName, $datas)
	{
        $csvData = implode(PHP_EOL, $datas);
        Storage::append($fileName, self::convertCharacterToSjisWin($csvData));
	}
}