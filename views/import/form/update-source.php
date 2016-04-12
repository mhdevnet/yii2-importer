<?php

use yii\helpers\Html;
use yii\bootstrap\Tabs;
use kartik\widgets\ActiveForm;
use dosamigos\fileupload\FileUploadUI;
use dosamigos\fileupload\FileUpload;
use nitm\helpers\Icon;

switch($model->type) {
	case 'factual':
	case 'json':
	case 'csv':
	$mode = 'json';
	$model->raw_data = json_encode(json_decode($model->raw_data, true), JSON_PRETTY_PRINT);
	break;

	default:
	$mode = 'text';
	break;
}

?>
<h2>Raw Params</h2>
<div class="row">
	<div class="col-sm-12 text-right">
	<?= \nitm\widgets\modal\Modal::widget([
			'size' => 'x-large',
			'toggleButton' => [
				'tag' => 'a',
				'data-method' => 'post',
				'label' => "Re-Parse Source Params ".Icon::forAction('view'),
				'href' => \Yii::$app->urlManager->createUrl(['/import/re-parse/'.$model->id, '__format' => 'modal']),
				'title' => Yii::t('yii', 'Preview '),
				'class' => 'btn btn-warning',
				'role' => 'dynamicAction updateAction disabledOnClose',
				'data-confirm' => 'WARNING!! This will delete the current imported/unimported elements for this source. Are you sure 100% you want to refetch the source data for '.$model->title().'? If unsure you may simply create a new import and add extra data that way.'
			],
			'contentOptions' => [
				"class" => "modal-full"
			],
			'dialogOptions' => [
				"class" => "modal-full"
			]
		]);
	?>
	</div>
	<div class="col-sm-12">
		<br>
	<?=
		$form->field($model, 'raw_data')->widget(\trntv\aceeditor\AceEditor::className(), [
			'mode' => $mode,
			'theme' => 'twilight'
		]);
	?>
	</div>
</div>
