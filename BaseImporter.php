<?php

namespace nitm\importer;

use yii\helpers\ArrayHelper;
use nitm\helpers\Cache;
use nitm\importer\models\Source;

abstract class BaseImporter extends \yii\base\Model
{
	public $name;
	public $elements;
	public $class;
	public $mode = 'single';
	public $jobType = 'elements';
	public $offset = 0;
	public $limit = 100;
	public $batchSize = 10;

	protected $primaryModel;
	protected $baseModel;
	protected $job;
	protected $jobId;
	protected $chunks = 1;
	protected $_isPrepared = false;
	protected static $_fields = [];

	public function init()
	{
		parent::init();
		$this->jobId = uniqid();
		$this->name = !isset($this->name) ? $this->jobId : $this->name;
	}

	public function __destruct()
	{
		/**
		 * Delete any prepared data so as to not overrun memory or the file system with cached data
		 */
		$chunks = !$this->chunks ? 1 : $this->chunks;
		for($i = 0; $i<$this->chunks; $i++)
		{
			$this->deletePreparedData($i);
		}
	}

	public function attributes()
	{
		return static::$_fields;
	}

	/**
	 * Perform functions after each insert of a model
	 * @param mixed $data
	 * @param mixed $from
	 */
	protected function afterInserModels(&$data, &$from) {}

	/**
	 * Prepare the models by finding existing and inserting new parts, populating them, updating the local modele and inserting the new data
	 * @param mixed $from
	 */
	abstract public function prepareModels($from);

	/**
	 * Prepare a single model
	 * @param #data the model data
	 * @param string $modelClass The model class
	 * @param string $searchClass
	 * @return array
	 */
	abstract public function prepareModel($data, $modelClass, $searchClass);

	protected static function getIndexBy($for=null)
	{
		return 'id';
	}

	protected static function getFields($for=null)
	{
		return ['id'];
	}

	public function setJob($job)
	{
		$this->job = $job;
	}

	public function getJob()
	{
		return $this->job;
	}

	public function getImporter()
	{
		return \Yii::$app->getModule('nitm-importer')->getParser($this->job->type);
	}

	public function setRawData($data, $append = false)
	{
		if(!$append)
			$this->job->raw_data = $data;
		else
			$this->job->raw_data = array_merge($this->job->raw_data, $data);
	}

	public function getSource()
	{
		return Cache::cache()->get($this->jobId.'-source');
	}

	public function setSource($data)
	{
		return Cache::cache()->set($this->jobId.'-source', $data, 300);
	}

	public function getPreparedData($index)
	{
		return Cache::cache()->get($this->jobId.'-'.$index);
	}

	public function setPreparedData($index, $data)
	{
		return Cache::cache()->set($this->jobId.'-'.$index, $data, 300);
	}

	public function deletePreparedData($index)
	{
		Cache::cache()->delete($this->jobId.'-'.$index);
	}

	public static function transformField($field, $value)
	{
		return $value;
	}

	public function getParts($element)
	{
		return $element;
	}

	public function prepare($rawData)
	{
		if(!count($rawData))
			return false;

		$this->setPreparedData(0, $rawData);
		$this->job->raw_data = [];
		$this->_isPrepared = true;
		return true;
	}

	public function batchParse()
	{
		if($this->mode != 'batch' || !$this->_isPrepared)
			return;

		for($i = 0; $i<$this->chunks; $i++)
		{
			$chunk = $this->getPreparedData($i);

			if(!count($chunk) >= 1)
				continue;

			$this->setPreparedData($i, $chunk);

			if($this->jobType == 'elements')
				$this->importElements();
			else
				$this->saveModelsFromElements();

		}
		unset($raw_data, $chunk);
		/**
		* Probably want to cache the job data here;
		* Probably want to use cache the whole way 0_0;
		*/
		return true;
	}

	public function parse()
	{
		if(!$this->_isPrepared)
			return;
		foreach($this->getPreparedData(0) as $chunkId=>$chunk)
		{
			$chunk = $this->getAllParts($chunk);
			$this->setPreparedData($chunkId, $chunk);
		}
		unset($raw_data, $chunk);
		/**
		* Probably want to cache the job data here;
		* Probably want to use cache the whole way 0_0;
		*/
		return true;
	}

	public function saveModelsFromElements()
	{
		$ret_val = [];
		for($i = 0; $i<$this->chunks; $i++)
		{
			$preparedData = $this->getPreparedData($i);
			if(!is_array($preparedData) || !count($preparedData))
				continue;

			$preparedData = $this->prepareModels($preparedData);
			//Get the save result
			$ret_val = array_merge($ret_val, array_shift($preparedData));
			unset($preparedData);

			if(count($ret_val))
				$this->job->updateElements($ret_val);
		}
		return $ret_val;
	}

	public function importElements()
	{
		$ret_val = [];
		for($i = 0; $i<$this->chunks; $i++)
		{
			$preparedData = $this->getPreparedData($i);
			if(!count($preparedData) >= 1)
				continue;

			$this->setRawData($preparedData, true);

			foreach($preparedData as $idx=>$data)
			{
				list($title, $type, $elementData, $isNew) = $this->prepareElement($data);

				if($isNew) {
					$ret_val['result'][$idx] = [
						'success' => false,
						'title' => $title,
						'exists' => true,
						'reason' => ArrayHelper::getValue($elementData, 'reason', '')
					];
				}
				else {
					$ret_val['result'][$idx] = [
						'success' => true,
						'title' => $title,
					];
				}
				$ret_val['job'][$i][$idx] = $elementData;
				unset($elementData, $element);
			}
			$ret_val['job'] = $this->job->saveElements($ret_val['job'][$i], true);
		}
		return $ret_val;
	}

	public function formAttributes()
	{
		return [
		];
	}

	public static function transformFormAttributes($attributes)
	{
		return $attributes;
	}

	public static function transformFields($values)
	{
		foreach($values as $key=>$value)
		{
			$values[$key] = static::transformField($key, $value);
		}
		return $values;
	}

	public function batchImport($job=null)
	{
		$ret_val = [];
		$job = is_null($job) ? $this->jobType : $job;
		$this->start();
		$this->mode = 'batch';
		$this->jobType = $job;

		/**
		 * Set the memory limit to 64M for batch import operations.
		 */
		ini_set('memory_limit', '64M');
		set_time_limit(300);

		switch($job)
		{
			case 'data':
			$query = $this->job->getElementsArray();
			foreach($query->select(['raw_data', 'id'])
				->limit($this->limit)
				->offset($this->offset)
				->where(['is_imported' => false])
				->batch($this->batchSize) as $chunk)
			{
				$this->prepare($chunk);
				if(!$this->_isPrepared)
					continue;
				$ret_val = array_merge($ret_val, $this->saveModelsFromElements());
			}
			break;

			default:
			$this->importer->setData($this->source);
			while(is_array($chunk = ArrayHelper::getValue($this->importer->parse($this->source, $this->offset, $this->batchSize), 'parsedData', null)))
			{
				$this->prepare([$chunk]);
				if(!$this->_isPrepared)
					continue;

				$this->parse();
				$ret_val = array_merge($ret_val, $this->importElements());
				$this->offset += count($chunk);
			}
			break;
		}
		$this->end();
		return $ret_val;
	}

	public function import($job='elements')
	{
		$this->start();
		$ret_val = [];
		switch($job)
		{
			case 'elements':
			$this->prepare($this->importer->parse($this->source));

			if(!$this->_isPrepared)
				return;

			$this->parse();
			$ret_val = $this->importElements();
			break;

			default:
			$ret_val = $this->saveModelsFromElements();
			break;
		}
		$this->end();
		return $ret_val;
	}

	public function start($mode='batch')
	{
		if(!isset($this->job))
			$this->job = new Source([
				'name' => $this->name,
				'type' => 'csv',
				'data_type' => 'BaseImporter',
				'source' => 'array',
				'pages' => $this->pages,
			]);

		if(!$this->job->validate())
			return false;

		if($this->job->isNewRecord)
			$this->job->save();
		else
			$this->job->decode();

		switch($mode)
		{
			case 'single':
			case 'batch':
			$this->mode = $mode;
			break;

			default:
			throw new \yii\base\ErrorException("Unsupported import mode: ".$mode);
			break;
		}
		return count($this->source) >= 1;
	}

	public function end()
	{
		$raw_data = [];
		for($i = 0; $i<$this->chunks; $i++)
		{
			$preparedData = $this->getPreparedData($i);
			if(!count($preparedData) >= 1)
				continue;
			$this->deletePreparedData($i);
		}
		$this->job->raw_data = [];

		 Source::updateAll([
			'count' => $this->job->getElements()->where(['is_imported' => true])->count(),
			'total' => $this->job->getElements()->count(),
		], [
			'id' => $this->job->id
		]);

		$this->job->refresh();

		$this->_isPrepared = false;
	}

	protected function findModel($class, $condition, $key, $queryOptions=[], $many=false)
	{
		if(!is_subclass_of($class, \nitm\search\BaseSearch::className()))
			$class = (new $class)->searchClass;
		$search = new $class(array_merge([
			'inclusiveSearch' => true,
			'booleanSearch' => true,
			'queryOptions' => $queryOptions
		], ArrayHelper::remove($condition, 'construct', [])));
		$key = $key === false ? $search->isWhat().md5(json_encode($key)) : null;
		if(!($key === false) && Cache::exists($key))
			return Cache::getCachedModel(null, $key, $class, $condition);
		else {
			$query = $search->search($condition)->query;
			if(ArrayHelper::getValue($queryOptions, 'asArray', false) === true)
				$existing = $many ? $query->asArray()->all() : $query->asArray()->one();
			else
				$existing = $many ? $query->all() : $query->one();

			if(is_a($existing, $class)) {
				$cacheFunc = $many ? 'setCachedModelArray' : 'setCachedModel';
				Cache::setCachedModel($key, $existing);
				return $existing;
			} else {
				if(empty($existing))
					$existing = $condition;
				$class = $search->primaryModelClass;
				$existing = $many ? $existing : [$existing];
				$ret_val = array_map(function ($attributes) use($class, $queryOptions) {
					return (ArrayHelper::getValue($queryOptions, 'asArray', false) === true) ? $attributes : new $class($attributes);
				}, $existing);
				return $many ? $ret_val : array_pop($ret_val);
			}
		}
	}

	protected static function saveModel($className, $attributes, $title=null)
	{
		$model = new $className(['scenario' => 'create']);
		$model->setScenario('create');
		$model->setAttributes($attributes);
		if($model->hasAttribute('slug'))
			$model->setAttribute('slug', \yii\helpers\Inflector::slug($title));
		$model->save();
		if(\Yii::$app->db->lastInsertID)
			$model->id = \Yii::$app->db->lastInsertID;
		return $model;
	}

	protected static function normalize($value)
	{
		return trim(ucwords(strtolower($value)));
	}

	protected static function normalizeToArray($value)
	{
		if(is_array($value))
			return $value;
		$splitter = strpos($value, '|') != false ? '|' : ',';
		$ret_val = array_filter(array_map([static::className(), 'normalize'], explode($splitter, $value)));
		return array_unique($ret_val);
	}

	public function save()
	{
		$this->job->setScenario('create');
		return $this->job->save();
	}

	protected function extractParts($from)
	{
		if(ArrayHelper::isAssociative($from))
			return $from;
		else {
			return array_combine($this->importer->fields, $from);
		}
	}
}
