<?php

use yii\helpers\Html;
use yii\bootstrap\Tabs;
use kartik\widgets\ActiveForm;
use kartik\file\FileInput;
use nitm\helpers\Icon;

?>
<div>
<?=
	Html::tag('label', "Importing From: ", [
		'class' => 'control-label'
	])."&nbsp;".Html::tag('span', 'file', [
		'id' => 'import-location',
		'role' => 'sourceName',
		'class' => 'text-info strong'
	]);
?>
</div>
<div class="col-md-12 col-lg-12">
    <span role="fileUploadMessge">
    </span>
</div>
<?=
	Tabs::widget([
		'options' => [
			'id' => 'importer-location'.uniqid(),
			'role' => 'selectLocation'
		],
		'encodeLabels' => false,
		'items' => [
			[
				'active' => $model->type == 'file',
				'label' => 'Import From File',
				'content' => Html::tag('div',
					"<br>".$form->field($model, 'raw_data[file]')->widget(FileInput::className(), [
						'options' => [
							'accept' => 'text/csv',
							'role' => 'dataSource'
						],
					]), [
					'id' => 'import-from-file',
					'class' => 'col-md-12 col-lg-12'
				]),
				'options' => [
					'id' => 'import-from-file-container',
				],
				'headerOptions' => [
					'id' => 'import-from-file-tab'
				],
				'linkOptions' => [
					'id' => 'import-from-file-link',
					'role' => 'importSource',
					'data-source' => 'file'
				]
			],
			[
				'active' => $model->type == 'text',
				'label' => 'Import From Text',
				'content' => Html::tag('div', "<br>".$form->field($model, 'raw_data[text]')->textarea([
					'placeholder' => "Paste raw data in the format you chose above",
					'id' => 'source-raw_data_text',
					'role' => 'dataSource'
				])->label("Text"), [
					'id' => 'import-from-csv',
					'class' => 'col-md-12 col-lg-12'
				]),
				'options' => [
					'id' => 'import-from-csv-container',
				],
				'headerOptions' => [
					'id' => 'import-from-csv-tab'
				],
				'linkOptions' => [
					'id' => 'import-from-csv-link',
					'role' => 'importSource',
					'data-source' => 'text'
				]
			],
			[
				'active' => $model->type == 'url',
				'label' => 'Import From URL',
				'content' => Html::tag('div', "<br>".$form->field($model, 'raw_data[url]')->textarea([
					'placeholder' => "Paste url to acquire data from",
					'id' => 'source-raw_data_url',
					'role' => 'dataSource'
				])->label("Url"), [
					'id' => 'import-from-url',
					'class' => 'col-md-12 col-lg-12'
				]),
				'options' => [
					'id' => 'import-from-url-container',
				],
				'headerOptions' => [
					'id' => 'import-from-url-tab'
				],
				'linkOptions' => [
					'id' => 'import-from-url-link',
					'role' => 'importSource',
					'data-source' => 'url'
				]
			],
			[
				'active' => $model->type == 'api',
				'label' => 'Import From API',
				'content' => Html::tag('div', "<br>".$form->field($model, 'raw_data[api]')->textarea([
					'placeholder' => "Enter options for the API",
					'id' => 'source-raw_data_api',
					'role' => 'dataSource'
				])->label("Options"), [
					'id' => 'import-from-api',
					'class' => 'col-md-12 col-lg-12'
				]),
				'options' => [
					'id' => 'import-from-api-container',
				],
				'headerOptions' => [
					'id' => 'import-from-api-tab'
				],
				'linkOptions' => [
					'id' => 'import-from-api-link',
					'role' => 'importSource',
					'data-source' => 'api'
				]
			],
		]
	]);
?>
<div role="previewImport" class="col-lg-12 col-md-12 col-sm-12">
</div>

<?php if(\Yii::$app->request->isAjax): ?>
<script type="text/javascript">
$nitm.onModuleLoad('import', function(module) {
	module.initDefaults();
});
</script>
<?php endif; ?>
