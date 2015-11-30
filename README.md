NITM Yii2 Importer Module
============
NITM Importer module allows you to import data from various sources, such as Factual and more.

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist nitm/yii2-importer "*"
```

or add

```
"nitm/yii2-importer": "*"
```

to the require section of your `composer.json` file.


Usage
-----

Once the extension is installed, simply use it in your code by adding the following to your modules section :

```php
<?= 
	'nitm' => [
		'class' => "nitm\importer\Module",
		'parsers' => [
		    [
		        'class' => 'Parser Class'
		        ...
		    ],
		    ...
		],
		'types' => [
		    "The types supported by the importers",
		    ...
		],
		'sources' => [
		    "The sources supported by the importers",
		    ...
		]
	]; 
?>```

** Extending Parsers
=======
yii2-importer
=================