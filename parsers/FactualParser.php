<?php
/**
 * @link http://www.ninjasitm.com/
 * @copyright Copyright (c) 2014 NinjasITM INC
 * @license http://www.ninjasitm.com/license/
 */

namespace nitm\importer\parsers;

use Yii;
use yii\base\InvalidConfigException;
//use Factual\Factual;
//use Factual\FactualQuery;

/**
 * FactualParser parses data using the Factual API.
 */
class FactualParser extends JsonParser
{
	private $_authKey;
	private $_authSecret;
	protected $_queryLimit = 50;
	private $_maxLimit = 250;
	private $_query;
	private $_handle;

	public function setAuthKey($value) {
		$this->_authKey = $value;
	}

	public function setAuthSecret($value) {
		$this->_authSecret = $value;
	}

	public function setQueryLimit($limit)
	{
		$this->_queryLimit = (int)$limit;
	}

	public function getQueryLimit()
	{
		return $this->_queryLimit;
	}

	public function parse($data=[], $offset=0, $limit=150)
	{
		//$this->setData(json_decode('{"type":"places-us","fields":{"category_ids":["312"], "region":"NY", "country":"us"}}', true));
		$this->handle();
		/**
		 * Get up to $this->_maxLimit entries
		 */
		if($this->getOffset() > $this->_maxLimit)
			return null;
		if(($this->getOffset() + $this->getLimit()) >= $this->_maxLimit)
			/**
			 * If the offset minus the maxLimit is still greater than the maxLimit
			 * then return an empty value
			 */
			if($this->getOffset() - $this->_maxLimit > 0)
				return null;
			else
				$this->setLimit($this->_maxLimit - $this->getOffset());
		/**
		 * Made it this far. Now need to pull factual data and parse it;
		 */
		$this->parsedData = [];
		while(($this->getOffset() < $this->_maxLimit) && ((($data = $this->read()) != false)))
		{
			if(!isset($this->fields))
				$this->fields = array_keys(current($data));
			$data = array_filter($data);
			if(count($data) >= 1)
				$this->parsedData = array_merge($this->parsedData, $data);

			unset($data);
			$this->seek($this->getOffset());
		}
		return $this;
	}

	public function handle($path=null)
	{
		if(is_object($this->_handle))
			return $this->_handle;

		$this->_handle = new \Factual($this->_authKey, $this->_authSecret);
		return $this->_handle;
	}

	protected function seek()
	{
		$this->setOffset($this->getOffset() + $this->getLimit());
		return $this->getQuery()->offset($this->getOffset());
	}

	protected function read()
	{
		return $this->handle()->fetch($this->getData('type'), $this->getQuery())->getData();
	}

	protected function getQuery()
	{
		if(!$this->_query instanceof \FactualQuery) {
			$this->_query = new \FactualQuery();
			$this->_query->limit($this->getQueryLimit());
			$this->setAt();
			$this->setWithin();
			$this->setFilters();
			$this->setFields();
			$this->setOnly();
			$this->setSelect();
			$this->setSearch();
			$this->setSort();
			$this->setQueryOffset();
		}
		return $this->_query;
	}

	public function setData($data)
	{
		if(is_string($data))
			parent::setData(json_decode($data, true));
		if(is_array($data))
			parent::setData($data);
	}

	protected function setSort()
	{
		if($this->getData('sort.asc')) {
			$this->_query->sortAsc($this->getData('sort.asc'));
		}
		if($this->getData('sort.desc')) {
			$this->_query->sortDesc($this->getData('sort.desc'));
		}
	}

	protected function setSearch()
	{
		if($this->getData('search')) {
			$this->_query->only($this->getData('search'));
		}
	}

	protected function setQueryOffset()
	{
		if($this->getData('offset')) {
			$this->_query->offset($this->getData('offset'));
		}
	}

	protected function setOnly()
	{
		if($this->getData('only')) {
			$this->_query->only($this->getData('only'));
		}
	}

	protected function setSelect()
	{
		if($this->getData('select')) {
			$this->_query->only($this->getData('select'));
		}
	}

	protected function setFields()
	{
		if(is_array($this->getData('fields'))) {
			foreach($this->getData('fields') as $field=>$options)
			{
				if(is_array($options)) {
					foreach($options as $type=>$value) {
						if(is_int($type))
							$in[] = $value;
						else {
							$type = is_int($type) ? 'in' : (method_exists($this->_query, $type) ? $type : 'in');
							$this->_query->field($field)->$type($value);
						}
					}
					if(isset($in))
						$this->_query->field($field)->in($in);
				}
				else
					$this->_query->field($field)->equal($options);
			}
		}
	}

	protected function setFilters()
	{
		if(is_array($this->getData('filter'))) {
			$filter = new \FilterGroup;
			foreach($this->getData('filter') as $op=>$options)
			{
				$filter->add(new \FieldFilter($op, key(current($options)), current($options)));
			}
		}
	}

	protected function setWithin()
	{
		if($this->getData('within')) {
			$class = '\Factual'.ucfirst($this->getData('within.type'));
			if(class_exists($class))
				$this->_query->within(call_user_func_array([$class, '__construct'], $this->getData('within.params')));
		}
	}

	protected function setAt()
	{
		if($this->getData('at')) {
			$this->_query->within(call_user_func_array([\FactualPoint, '__construct'], $this->getData('at')));
		}
	}
}

?>
