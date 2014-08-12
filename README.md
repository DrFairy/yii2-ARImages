AR Images
===========

**AR Images** is a Behavior for the [Yii2 framework](http://www.yiiframework.com/) that manages images stored in ActiveRecord Models image attributes.

You can set an arbitrary number of image variants (with different sizes and proportions) for an arbitrary number of AR image attributes.

ARImages behavior works both in base and advanced yii application template and uses some yii extensions, for example Imagine.

Note: This behavior doesn't overwrite or broke native yii2 AR attributes validation (whether attribute, file, or image type validation), so you may use it freely regardless of behavior context.

INSTALLATION
-------------

The preferred way to install **AR Images** is through [Composer](https://getcomposer.org/). Either add the following to the require section of your `composer.json` file:

```
	"DrFairy/yii2-ARImages": "*"
```

Or run:

```
	$ php composer.phar require DrFairy/yii2-ARImages "*"
```

Manual installation: [downloading the source in ZIP-format](https://github.com/DrFairy/yii2-ARImages/archive/master.zip).

SETTINGS
--------

### Internal ###

To setup ARImages behavior

* First you set your main application ID to `APP_OWNER` constant in the ARImages class. Main one means the application in which you save image data of your AR Models. It's set in `config/main.php` (or in `config/web.php` in case of using base Yii2 app template):

```
	...
	return [
        'id' => 'basic', //or something like 'app-backend'
        ...
    ];
```

- Set `ROOT_ALIAS_NAME` constant which is both content directory name in a web root of the main application and the name of an alias to the same directory in a filesystem.
- Set `IMAGES_FOLDER` name.

### External ###

* Set content directory alias for every application (if use advanced yii application template) in something like `<project Path>/<application ID>/config/aliases.php`. Or do it once in common config.

```
	<? Yii::setAlias('<ROOT_ALIAS_NAME>', '<content directory route>'); ?>
```

* (no need of this in case of base yii application template) Set the asset for content directory link in every application, except the main one (it my be set for main too, but not used neither while saving images, nor showing images fool path).  You may set this once in common config.

```
	<?php
	namespace common\assets;

	use yii\web\AssetBundle;

	class <ROOT_ALIAS_NAME>Asset extends AssetBundle
    {
    	public $sourcePath = '@<ROOT_ALIAS_NAME>';
    }
```

* (no need of this in case of base yii application template) Check if the assets are set to links, not to copying files! (for example in `<project Path>/common/config/main.php` )

```
	<?php
    return [
        'vendorPath' => dirname(dirname(__DIR__)) . '/vendor',
        'components' => [
            ...
    		'assetManager' => [
    			'linkAssets' => true,
    		],
    		...
        ],
    ];
```

USAGE
------

* Set up the AR Class. Declare the **AR Images** behavior in the ActiveRecord class. The following example describes a case of two image attributes in AR model class, `'<attribute1 name>'` and `'<attribute2 name>'` . The first one have three variants. Management and displaying of this example are shown below.

The code should look something like this:

```
    use drfairy\yii2-ar-images\ARImages

    class <model> extends \yii\db\ActiveRecord {
    	...
    	public function behaviors(){
    		return [
    			[
    			    'class' => ARImages,
    				'imagesSettings' => [
                        [
                            'imageAttribute' => [string] '<attribute1 name>',
                            'variants' => [
                                [
                                    'h' => [int] <value>,
                                    'w' => [int] <value>,
                                ],
                                [
                                    [optional] 'name' => [string] [<variant2 name>] 'medium',
                                    'h' => [int] <value>,
                                    'w' => [int] <value>,
                                ],
                                [
                                    'h' => [int] <value>,
                                    'w' => [int] <value>,
                                ],
                            ],
                        ],

                        [
                            'imageAttribute' => '<attribute2 name>',
                            [optional] 'saveFolder' => <save folder1>
                            'variants' => [
                                [
                                    'h' => [int] <value>,
                                    'w' => [int] <value>,
                                ],
                            ],
                        ],
                    ]
    			],
     			...		// other behaviors
    		];
    	}
    	...
    }
```

* Create/Update. You may use native Yii2 ActiveForm and ActiveField to make the client part of file CU operations of CRUD. The client (View) part is the only one you set in this case. For the above example just add two standard file inputs in a View in the ActiveForm body:

```
	<?= $form->field($<model>, '<attribute1 name>')->fileInput() ?>
	...
	<?= $form->field($<model>, '<attribute2 name>')->fileInput() ?>
```

* Display. For example above, using native Yii2 View and Html features:

```
	<? if ($<model>-><attribute1 name>){ ?>
    	<?= Html::img($model->imagesUrls['<attribute1 name>']) .
    		Html::img($model->imagesUrls['<attribute1 name>Medium']) .
    		Html::img($model->imagesUrls['<attribute1 name>2'])?>
    <? } ?>

    <? if ($<model>-><attribute2 name>){ ?>
        	<?= Html::img($model->imagesUrls['<attribute2 name>'])?>
    <? } ?>
```

## Naming conventions by examples ##

If behavior and it's environment setup succeeded, the `<path to images>` would look like this:

* `<project Path>/<application ID>/web/<ROOT_ALIAS_NAME>/<IMAGES_FOLDER>` - in a filesystem
* `/<ROOT_ALIAS_NAME>/<IMAGES_FOLDER>` - from the web in the main application
* `/assets/<ROOT_ALIAS_NAME link name hash>/<IMAGES_FOLDER>` - from the web in other applications of your Yii2 project

In context of AR class, attaching behavior and images (variations):

* `<path to images>/<AR behavior owner class name without namespaces>/<AR image attribute name>/<image variant><img saved hash name >.<file extension>` - default
-- `<image variant>` is `''` if it's the first variant of ARImage attribute with no name set
-- `<image variant>` is `'<number>/'` if it's the `#<number>` variant of ARImage attribute with no name set
-- `<image variant>` is `'<name>/'` if it's the variant of ARImage attribute with name `<name>`
* `<path to images>/<saveFolder>/<image variant><img saved hash name >.<file extension> - if <saveFolder>` is set for AR image attribute in attached behavior; `<image variant>` is identical to previous case

Displaying images in a View for the main example are shown above, so let's use another example of AR images variants url in View part:

* `$model->imagesUrls['logoBig']` - is available if you have set variant name 'big' to AR model image attribute 'logo' in attached ARImages behavior. Optional variant setting 'saveFolder' doesn't change anything here and cases below.
* `$model->imagesUrls['logo']` - is available as the first image variant url of AR model image attribute 'logo', declared in attached behavior settings. BUT IT'S ONLY AVAILABLE if this first attribute image variant has no name.
* `$model->imagesUrls['logo2']` - is available as the third image variant url of AR model image attribute 'logo', declared in attached behavior settings. BUT IT'S ONLY AVAILABLE if there is three declared variants, and the third one has no name, else it’s the first name convention case.

## Don't forget ##

* To add to you ActiveForm with image attributes file inputs in a View:

```
	['options' => ['enctype'=>'multipart/form-data']]
```