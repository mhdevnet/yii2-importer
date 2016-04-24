<?php

namespace nitm\importer\controllers;

use nitm\importer\models\Source;
use nitm\importer\models\search\Source as SourceSearch;
use nitm\importer\models\Element;
use nitm\importer\models\search\Element as ElementSearch;
use nitm\helpers\Response;
use nitm\helpers\Helper;
use yii\web\UploadedFile;
use yii\helpers\ArrayHelper;

abstract class ImportController extends \nitm\controllers\DefaultController
{
	protected $sourceSelectFields = [
		'id', 'name', 'author_id', 'created_at',
		'type', 'data_type', 'count',
		'total', 'source', 'signature',
		'completed', 'completed_by', 'completed_at',
		'remote_type', 'remote_id'
	];

	protected $_importer;

	public function behaviors()
	{
		$behaviors = [
			'access' => [
				'rules' => [
					[
						'actions' => [
							'element', 'elements', 're-parse',
							'batch', 'import-all', 'import-batch',
							'update-element'
						],
						'allow' => true,
						'roles' => ['@'],
					],
				],
			],
			'verbs' => [
				'actions' => [
					'element' => ['post'],
					'elements' => ['post'],
					're-parse' => ['post'],
					'update-element' => ['post'],
					'batch' => ['post'],
					'import-all' => ['post', 'get'],
					'import-batch' => ['post', 'get']
				],
			],
		];
		return array_merge_recursive(parent::behaviors(), $behaviors);
	}

	public function beforeAction($action)
	{
		switch($action->id)
		{
			case 'preview':
			case 'element':
			$this->enableCsrfValidation = false;
			break;
		}
		return parent::beforeAction($action);
	}


	public function init()
	{
		parent::init();
		$this->model = new Source();
	}

	protected function getImporterModule()
	{
		return \Yii::$app->getModule('nitm-importer');
	}

	protected function getImporter($type=null)
	{
		$type = is_null($type) ? $this->model->type : $type;
		return $this->importerModule->getParser($type);
	}


	public function getProcessor()
	{
		if(isset($this->_importer))
			return $this->_importer;
		$this->_importer = $this->importerModule->getProcessor($this->model->data_type ?: $this->model->remote_type);
		$this->_importer->job = $this->model;
		return $this->_importer;
	}

	public static function assets()
	{
		return [
			\nitm\importer\assets\ImportAsset::className()
		];
	}

	public function getWith()
	{
		return array_merge(parent::getWith(), [
			'reply'
		]);
	}

    public function actionIndex($className=null, $options=[])
    {
		return parent::actionIndex(SourceSearch::className(), [
			'construct' => [
				'defaults' => [
					'sort' => [
						'created_at' => SORT_DESC,
					]
				]
			]
		]);
    }

	public function actionUpdateElement($id)
	{
		$ret_val = [
			'success' => false,
			'message' => "Couldn't save element"
		];
		$this->model = Element::findOne($id);
		$this->model->decode();
		$data = ArrayHelper::getValue($_POST, $this->model->formName());
		if(isset($data['elements']))
			$data = array_pop($data['elements']);
		$processor = $this->importerModule->getProcessor($this->model->source->data_type ?: $this->model->source->remote_type);
		$this->model->raw_data = $processor->getParts(array_replace_recursive($this->model->raw_data, $data));
		$this->model->encode();
		$this->model->setScenario('update');
		if($this->model->save()) {
			$ret_val['success'] = true;
			$ret_val['message'] = "Updated element data successfully";
		} else {
			$ret_val['message'] = array_map(function ($error) {
				return implode('. ', $error);
			}, $this->model->getErrors());
		}
		$this->responseFormat = 'json';
		return $ret_val;
	}

	/**
	 * Preview import data. This occurs after an import has been created
	 * @param  [type] $id [description]
	 * @return [type]     [description]
	 */
	public function actionReParse($id, $modelClass=null, $options=[])
	{
		$this->model = $this->findModel(Source::className(), $id);
		Element::deleteAll([
			'imported_data_id' => $this->model->id
		]);
		$ret_val = $this->processSourceData();
		Response::viewOptions('view', 'preview');
		Response::viewOptions('args', [
			"model" => $this->model,
			'processor' => $this->processor,
			'attributes' => $this->processor->formAttributes(),
			"dataProvider" => new \yii\data\ActiveDataProvider([
				'query' => $this->model->getElementsArray(),
				'pagination' => [
					'defaultPageSize' => 50,
					'pageSize' => 50
				]
			])
		]);
		return $this->renderResponse($ret_val, Response::viewOptions(), \Yii::$app->request->isAjax);
	}

	/**
	 * View import data. This occurs after an import has been created
	 * @param  [type] $id [description]
	 * @return [type]     [description]
	 */
	public function actionView($id, $modelClass=null, $options=[])
	{
		if(!$this->isResponseFormatSpecified)
			$this->responseFormat = 'html';
		$this->model = $this->findModel(Source::className(), $id);
		$formOptions = [
			'action' => '/import/update/'.$this->model->getId(),
			'type' => \kartik\widgets\ActiveForm::TYPE_VERTICAL,
			'formConfig' => [
				'showLabels' => false,
			],
			'enableAjaxValidation' => true,
			'enableClientValidation' => true,
			'validateOnSubmit' => true,
			'options' => [
				'enctype' => 'multipart/form-data',
				'role' => "updateImport",
				'id' => 'import-elements-form-'.$this->model->id
			]
		];
		return parent::actionView($id, null, array_merge([
			'view' => 'preview',
			'model' => $this->model,
			'args' => [
				'form' => include(\Yii::getAlias("@nitm/importer/views/layouts/form/header.php")),
				'formOptions' => $formOptions,
				'processor' => $this->processor,
				'attributes' => $this->processor->formAttributes(),
				"dataProvider" => new \yii\data\ActiveDataProvider([
					'query' => $this->model->getElementsArray(),
					'pagination' => [
						'defaultPageSize' => 50,
						'pageSize' => 50
					]
				])
			]
		], $options));
	}

	public function actionImportBatch($id)
	{
		$this->model = $this->findModel(Source::className(), $id, [], [
			'select' => $this->sourceSelectFields
		]);

		$this->processor->limit = $this->importerModule->limit;
		$this->processor->batchSize = $this->importerModule->batchSize;

		return $this->actionImportAll($id, true);
	}

	public function actionImportAll($id, $modelFound=false)
	{
		$ret_val = [
			'count' => 0,
			'processed' => 0,
			'exists' => 0,
			'percent' => 0,
			'message' => "Didn't improt anything :-("
		];
		if(!$modelFound) {
			$this->model = $this->findModel(Source::className(), $id, [], [
				'select' => $this->sourceSelectFields
			]);
			$this->processor->limit = 1000;
			$this->processor->batchSize = $this->importerModule->batchSize;
		}

		$this->processor->job = $this->model;
		$this->model = null;
		if($this->processor->job instanceof Source)
		{
			$result = $this->processor->batchImport('data');
			$imported = [];
			foreach($result as $idx=>$jobElement)
			{
				if(ArrayHelper::getValue($jobElement, 'success', false))
				{
					$imported[] = ArrayHelper::getValue($jobElement, 'id', $idx);
					$ret_val['processed']++;
				}
				else if(ArrayHelper::getValue($jobElement, 'exists', false)) {
					$imported[] = ArrayHelper::getValue($jobElement, 'id', $idx);
					$ret_val['exists']++;
					$ret_val['processed']++;
				}

				$ret_val['count']++;
			}
			if(count($imported))
				Element::updateAll(['is_imported' => true], ['id' => $imported]);

			$ret_val['message'] = "Imported <b>".$ret_val['processed']."</b> out of <b>".$ret_val['count']."</b> elements!";
			$ret_val['percent'] = $this->processor->getJob()->percentComplete();
			if($ret_val['exists'])
				$ret_val['message'] .= " <b>".$ret_val['exists']."</b> out of <b>".$ret_val['count']."</b> the entires already exist!";
			if($ret_val['exists'])
				$ret_val['class'] = 'info';
			else if($ret_val['processed'] == 0)
				$ret_val['class'] = 'error';
			else if($ret_val['processed'] < $ret_val['count'])
				$ret_val['class'] = 'warning';
			else
				$ret_val['class'] = 'success';

		}
		$this->setResponseFormat('json');
		return $ret_val;
	}

	protected function actionElements($id)
	{
		$elementIds = \Yii::$app->request($post);
		$this->model = $this->findModel(Source::className(), $id, []);
		$this->model->setFlag('source-where', ['ids' => $elementIds]);

		$this->processor->limit = $this->importerModule->limit;
		$this->processor->batchSize = $this->importerModule->batchSize;
		$this->processor->offset = $this->model->getElementsArray()
			->where([
				'is_imported' => true
			])->count();

		return $this->actionImportAll($id);
	}

	public function actionCreate($modelClass=null, $viewOptions=[])
	{
		/*$this->setResponseFormat('json');
		$ret_val = [
			'action' => 'create',
			'id' => 13,
			'success' => true,
			'url' => \Yii::$app->urlManager->createUrl(['/import/view/114']),
			'form' => [
				'action' => \Yii::$app->urlManager->createUrl(['/import/form/update/'.$this->model->getId()])
			]
		];
		return $ret_val;*/
		$ret_val = parent::actionCreate();
		if(isset($ret_val['success']) && $ret_val['success']) {
			$ret_val = array_merge($ret_val, $this->processSourceData());
			$ret_val['form'] = [
				'action' => \Yii::$app->urlManager->createUrl(['/import/form/update/'.$this->model->getId()])
			];
			$this->processor;
		}
		return $this->renderResponse($ret_val, null, \Yii::$app->request->isAjax);
	}

	public function actionElement($type, $id=null)
	{
		$ret_val = ['id' => $id, 'success' => false];
		switch($type)
		{
			case 'element':
			$model = $this->findModel(Element::className(), $id, ['source']);
			break;

			default:
			$model = array_shift(Element::findFromRaw($type, $id));
			break;
		}
		if($model instanceof Element)
		{
			$this->model = $model->source;
			$this->processor->setJob($model->source);
			$this->processor->prepare([$model]);
			$ret_val = array_values($this->processor->import('data'))[0];
			if($ret_val['success'] || @$ret_val['exists'])
			{
				$model->setScenario('import');
				$model->is_imported = true;
				$model->save();
				Source::updateAllCounters(['count' => 1], ['id' => $this->model->getId()]);
				$ret_val['class'] = 'success';
				$ret_val['icon'] = \nitm\helpers\Icon::show('thumbs-up', ['class' => 'text-success']);
			}
		}
		$this->setResponseFormat('json');
		return $this->renderResponse($ret_val, Response::viewOptions(), \Yii::$app->request->isAjax);
	}

	public function actionForm($type, $id=null, $options=[], $returnData=false)
	{
		switch($type)
		{
			case 'update':
			$this->model = $this->findModel($this->model->className(), $id, ['author']);
			$id = null;
			break;
		}
		$options['formOptions'] = [
			'options' => [
				'id' => 'source-import-form'
			]
		];
		$data = parent::actionForm($type, $id, $options, true);
		$data['args']['dataProvider'] = new \yii\data\ActiveDataProvider([
			'query' => $data['args']['model']->getElementsArray(),
			'pagination' => [
				'defaultPageSize' => 50,
				'pageSize' => 50
			]
		]);
		$data['args']['processor'] = $this->processor;
		Response::viewOptions(null, $data);
		return $this->renderResponse([], Response::viewOptions(), \Yii::$app->request->isAjax);
	}

	/**
	 * Preview import data. This occurs after an import has been created
	 * @param  [type] $id [description]
	 * @return [type]     [description]
	 */
	protected function processSourceData()
	{
		$ret_val = [
			'success' => true
		];
		if(!$this->model)
			return [
				'success' => false,
				'message' => "The import with the id: $id doesn't exist"
			];

		if(!$this->importerModule->isSupported($this->model->type))
			throw new \yii\base\ErrorException("Unsupported type: ".$this->model->type);

		if($this->processor) {
	        $this->processor->job->setScenario('preview');
			$this->processor->job->decode();
			$ret_val['data'] = ArrayHelper::getValue($this->processor->job->raw_data, $this->processor->job->source, $this->processor->job->raw_data);
			$this->processor->job->setParams();
			if(empty($this->processor->job->params))
				throw new \yii\web\BadRequestHttpException("No parameters specified for the source. Please speficy the api, file, json or CSV data first. ");
			$this->processor->setSource($this->processor->job->params);
			$data['success'] = $this->processor->start('batch');
			if($data['success']) {
				$this->processor->batchImport('elements');
				$ret_val['url'] = \Yii::$app->urlManager->createUrl(['/import/view/'.$this->processor->job->getId()]);
			}
		}

		return $ret_val;
	}
}
