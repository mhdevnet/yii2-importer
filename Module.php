<?php

namespace nitm\importer;

use nitm\helpers\Session;
use nitm\models\DB;
use nitm\helpers\ArrayHelper;
use nitm\components\Logger;
use nitm\importer\Importer;
use nitm\components\Dispatcher;

class Module extends \yii\base\Module
{
	/**
	 * @string the module id
	 */
	public $id = 'nitm-importer';

	public $controllerNamespace = 'nitm\importer\controllers';

	//constant data
	const SOURCE_CSV = 'csv';
	const SOURCE_JSON = 'json';

	//public data
	public $currentUser;
	public $batchSize = 10;
	public $offset = 0;
	public $limit = 100;

	private $_types;
	private $_parsers;
	private $_sources;
	private $_parser;
	private $_processor;

	public function init()
	{
		parent::init();
		/**
		 * Aliases for nitm module
		 */
		\Yii::setAlias($this->id, realpath(__DIR__));

		$this->currentUser = (\Yii::$app->hasProperty('user') && \Yii::$app->user->getId()) ? \Yii::$app->user->getIdentity() : new \nitm\models\User(['username' => (php_sapi_name() == 'cli' ? 'console' : 'web')]);

		if(!isset($this->_parsers))
			$this->setParsers();
		if(!isset($this->_sources))
			$this->setSources();
		if(!isset($this->_types))
			$this->setTypes();
	}

	/**
	 * Generate routes for the module
	 * @method getUrls
	 * @param  string  $id The id of the module
	 * @return array     	The routes
	 */
	public function getUrls($id = 'nitm')
	{
		$parameters = [];
		$routeHelper = new \nitm\helpers\Routes([
			'moduleId' => $id,
			'map' => [
				'type' => '<controller>/<action>/<type>',
				'action-only' => '<controller>/<action>',
				'none' => '<controller>'
			],
			'controllers' => []
		]);
		$routes = $routeHelper->create($parameters);
		return $routes;
	}

	public function getParser($type)
	{
		if(isset($this->_parser[$type]))
			return $this->_parser[$type];

		$options = ArrayHelper::getValue($this->getParsers(), $type, []);
		unset($options['name']);
		if(!isset($options['class']) || !class_exists($options['class']))
			throw new \yii\base\UnknownClassException("Couldn't find parser for '$type'");

		$this->_parser[$type] = \Yii::createObject($options);
		return $this->_parser[$type];
	}

	public function getProcessor($type)
	{
		if(isset($this->_processor[$type]))
			return $this->_processor[$type];

		$options = ArrayHelper::getValue($this->getTypes(), $type, []);
		unset($options['name'], $options['class']);
		$options['class'] = $options['processorClass'];
		unset($options['processorClass']);
		if(!isset($options['class']) || !class_exists($options['class']))
			throw new \yii\base\UnknownClassException("Couldn't find processor for '$type'");

		$this->_processor[$type] = \Yii::createObject($options);
		return $this->_processor[$type];
	}

	/**
	 * Import data base on
	 * @param array $string|array $data
	 * @param string $type The type of the data
	 */
	public function import($data, $type='csv')
	{
		$ret_val = false;
		switch($type)
		{
			case in_array($type, $this->_types):
			$ret_val = $this->getParser($type)->import($data);
			break;

			default:
			break;
		}
	}

	public function setTypes($types=[])
	{
		$this->_types = $types;
	}

	public function isSupported($parser)
	{
		return isset($this->_parsers[$parser]);
	}

	public function setParsers($parsers=[])
	{
		$this->_parsers = array_merge($parsers, [
			'csv' => [
				'name' => 'CSV',
				'class' => \nitm\importer\parsers\CsvParser::className(),
			],
			'json' => [
				'name' => 'Json',
				'class' => \nitm\importer\parsers\JsonParser::className(),
			]
		]);
	}

	public function setSources($sources=[])
	{
		$this->_sources = array_merge($sources, [
			'file' => 'File',
			'text' => 'Text'
		]);
	}

	public function getTypes($what=null)
	{
		return $this->extractValues($this->_types, $what);
	}

	public function getProcessors($what=null)
	{
		return $this->extractValues($this->_types, $what);
	}

	public function getSources($what=null)
	{
		return $this->extractValues($this->_sources, $what);
	}

	public function getParsers($what=null)
	{
		return $this->extractValues($this->_parsers, $what);
	}

	protected function extractValues($from, $what)
	{
		switch($what)
		{
			case 'class':
			return array_map(function ($value) {
				return $value['class'];
			}, $from);
			break;

			case 'name':
			return array_map(function ($value) {
				return $value['name'];
			}, $from);
			break;

			default:
			return $from;
			break;
		}
	}
}
