<?php
/**
 * @link http://www.nitm.com/
 * @copyright Copyright (c) 2014 NITM Inc
 */

namespace nitm\importer\assets;

use yii\web\AssetBundle;

/**
 * @author Malcolm Paul admin@nitm.com
 */
class ImportAsset extends AssetBundle
{
	public $sourcePath = '@nitm/importer/assets/';
	public $css = [
	];
	public $js = [
		'js/import.js',
	];
	//public $jsOptions = ['position' => \yii\web\View::POS_READY];
	public $depends = [
		'yii\bootstrap\BootstrapAsset',
		'yii\bootstrap\BootstrapPluginAsset',
		'nitm\assets\AppAsset',
	];
}
