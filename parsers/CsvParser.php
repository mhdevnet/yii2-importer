<?php
/**
 * @link http://www.ninjasitm.com/
 * @copyright Copyright (c) 2014 NinjasITM INC
 * @license http://www.ninjasitm.com/license/
 */

namespace nitm\importer\parsers;

use \SplFileObject;
use \ArrayIterator;

/**
 * CsvParser parses a CSV file.
 */
class CsvParser extends BaseParser
{
	private $_handle;
	private $_isFile = false;
	private $shouldAddId;

	protected function prepareData($data)
	{
		if(is_string($data) && !file_exists($data)) {
			$this->data = explode("\n", $data);
		} else {
			$this->data = $data;
		}
	}

	protected function parseFields($fields) {
		$fields = array_map('strtolower', $fields);
		if($this->shouldAddId === null) {
			$this->shouldAddId = empty(array_intersect($fields, ['id', '_id'])) && empty($this->fields);
			if($this->shouldAddId)
				array_unshift($fields, '_id');
		}
		return $fields;
	}

	public function parse($rawData, $offset = 0, $limit = 150, $options=[])
	{
		if($this->shouldParse($rawData)) {
			$this->_isFile = null;
			$this->_handle = null;
			$this->shouldAddId = null;
			$this->parsedData = null;
			$this->prepareData($rawData);
			if(!count($this->fields))
				if(($firstLine = $this->read()) !== false) {
					$this->fields = $this->parseFields($firstLine);
				}

			if($this->seek($offset) || !$this->isEnd()) {
				$current = is_array($this->handle()->current()) ? $this->handle()->current() : str_getcsv($this->handle()->current(), ',', '"');
				if($this->parseFields($current) === $this->fields) {
					$this->handle()->next();
				}

				$line = $offset;
				while($line <= ($limit+$offset) && !$this->isEnd())
				{
					$data = array_filter($this->read());
					if($this->shouldAddId)
						array_unshift($data, uniqid());
					if(count($data) >= 1 && (count($data) == count($this->fields)))
						$this->parsedData[] = $data;
					else
						echo "Skipping ".json_encode($data)."\n";
					unset($data);
					$line++;
				}
			}
		}
		return $this;
	}

	public function getCsvArray()
	{
		if(is_array($this->parsedData)) {
			$this->fields = is_array($this->fields) ? $this->fields : explode(',', $this->fields);
			return array_merge([$this->fields], array_map(function ($data) {
				return array_map('utf8_encode', $data);
			}, $this->parsedData));
		}
		return null;
	}

	protected function isEnd()
	{
		if($this->_isFile)
			return $this->handle()->eof();
		return !$this->handle()->valid();
	}

	protected function next()
	{
		return $this->handle()->next();
	}

	protected function seek($to)
	{
		if($this->handle() instanceof \ArrayIterator) {
			if($this->handle()->offsetExists($to)) {
				$this->handle()->seek($to);
				return true;
			} else {
				return false;
			}
		} else {
			$this->handle()->seek($to);
			return true;
		}
	}

	protected function read()
	{
		$current = $this->handle($this->data)->current();
		$ret_val = is_array($current) ? $current : str_getcsv($current, ',', '"');
		$this->handle()->next();
		if(empty($ret_val))
			return null;
		return array_map('trim', $ret_val);
	}

	public function handle($path=null)
	{
		if(is_object($this->_handle))
			return $this->_handle;

		$path = is_null($path) ? $this->data : $path;
		$this->_isFile = is_string($path) && file_exists($path);

		if($this->_isFile) {
			$this->_handle = new SplFileObject($path, 'r');
			$this->_handle->setFlags(SplFileObject::SKIP_EMPTY);
			$this->_handle->setFlags(SplFileObject::READ_CSV);
		} else {
			$this->_handle = new ArrayIterator($this->data);
		}

		return $this->_handle;
	}

	public function close()
	{
		if(is_object($this->_handle))
			//$this->_handle->fclose();
		$this->parsedData = [];
		return true;
	}
}
