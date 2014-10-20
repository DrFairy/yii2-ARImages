<?php
/**
 * @link https://github.com/DrFairy/yii2-ARImages
 * @copyright Copyright (c) 2014 Alexey Belotserkovskiy
 * @license http://opensource.org/licenses/BSD-3-Clause
 */

namespace DrFairy\behaviors;

use Yii;
use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii\imagine\Image;
use yii\web\UploadedFile;
use yii\helpers\BaseUrl;
use yii\helpers\FileHelper;

/**
 * Class ARImages
 * @author Alexey Belotserkovskiy <dr2fairy@gmail.com>
 * @package drfairy\behaviors
 */
class ARImages extends Behavior
{
    const GENERATE_SUB_FOLDER_MODE = 0777;

    /**
     * @var array of elements-singletons -- arrays of images and images variants paths for initialized AR model classes
     * @see afterInit() - it is setting here
     */
    private static $pathTrees = [];

    /**
     * @var array (
     *  @element integer 'mW' max image width
     *  @element integer 'mH' max image height
     *  @element integer|bool 'w' fixed image width or false
     *  @element integer|bool 'h' fixed image height or false
     * ) - behavior's image settings preset
     */
    private static $defaultImageSettings = [
        'mW' => 1600,
        'mH' => 1600,
        'w' => false,
        'h' => false,
    ];

    /**
     * @var array (
     *  @element string 'APP_OWNER' Application with AR Model Class images.
     *      Other applications use images via symlink assets
     *
     *  @element string 'ROOT_ALIAS_NAME' Directory with  AR Model Class images and the name of alias
     *      for images path and url generation depending on application context
     *      @see afterInit()
     *
     *  @element string 'IMAGES_FOLDER' AR Model Class images directory name
     * ) - images location settings, set for every AR Model class, so may be passed to the global app settings
     */
    public $imgRoot = [
        'APP_OWNER' => 'basic',
        'ROOT_ALIAS_NAME' => 'content',
        'IMAGES_FOLDER' => 'images',
    ];

    /**
     * @var array (
     *  @element string 'saveFolder' optional image directory (otherwise it is generating by model/attribute context)
     *  @element string 'imageAttribute' model images attribute name
     *
     *  @element array 'variants' (
     *      @element string 'name' optional variant subDirectory (otherwise it is generating by variant key context)
     *      @element integer 'mW' max image variant width
     *      @element integer 'mH' max image variant height
     *      @element integer|bool 'w' fixed image variant width or false
     *      @element integer|bool 'h' fixed image variant height or false
     *  ) image variants settings
     * ) AR model class image attributes settings
     * @see afterInit() - it is mainly used here
     */
    public $imagesSettings;

    /**
     * @var array of model images attributes / variants urls in AR model object
     * @see afterFind()
     */
    public $imagesUrls;

    public function events()
    {
        return [
            ActiveRecord::EVENT_INIT => 'afterInit',
            ActiveRecord::EVENT_AFTER_FIND => 'afterFind',
            ActiveRecord::EVENT_BEFORE_VALIDATE => 'beforeValidate',
            ActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
            ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
            ActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
        ];
    }

    /**
     * @param string $name image name
     * @return string image name with hash part
     */
    private static function generateImageName($name)
    {
        return hash('md5', time() . $name);
    }

    /**
     * @param array $iS image settings
     * @param string $getPath source image path
     * @param string|bool $savePath optional result image save path (the default $getPath is used on false)
     */
    private static function processImage($iS, $getPath, $savePath = false)
    {
        $image = Image::getImagine()->open($getPath);
        $size = $image->getSize();
        $iS = array_merge(self::$defaultImageSettings, $iS);

        //Resize dimensions
        if ($iS['h'] && $iS['w']) {
            $height = $iS['h'];
            $width = $iS['h'];
        } elseif ($iS['h']) {
            $height = $iS['h'];
            $countedDim = intval($size->getWidth() * $height / $size->getHeight());
            $width = ($countedDim > $iS['mW']) ? $iS['mW'] : $countedDim;
        } elseif($iS['w']) {
            $width = $iS['w'];
            $countedDim = intval($size->getHeight() * $width / $size->getWidth());
            $height = ($countedDim > $iS['mH']) ? $iS['mH'] : $countedDim;
        } else {
            $height = $size->getHeight();
            $width = $size->getWidth();
            if ($size->getHeight() > $iS['mH'] || $size->getWidth() > $iS['mW']) {
                if (($size->getHeight() / $iS['mH']) > ($size->getWidth() / $iS['mW'])) {
                    $height = $iS['mH'];
                    $width = intval($size->getWidth() / ($size->getHeight() / $iS['mH']));
                } else {
                    $width = $iS['mW'];
                    $height = intval($size->getHeight() / ($size->getWidth() / $iS['mW']));
                }
            }
        }

        $size  = new \Imagine\Image\Box($width, $height);
        $image->resize($size)->save($savePath ? $savePath : $getPath);
    }

    /**
     * Init an element in self::$pathTrees array with images and images variants paths of an AR model class (singleton)
     * @return array link to the array of AR model class images and images variants paths
     */
    private function &pathTree()
    {
        $t =& self::$pathTrees[get_class($this->owner)];
        if($t) return $t;

        $imagesFolder = DIRECTORY_SEPARATOR . ($this->imgRoot['IMAGES_FOLDER'] ?
                $this->imgRoot['IMAGES_FOLDER'] . DIRECTORY_SEPARATOR : '');

        $t['saveRoot'] = Yii::getAlias('@'. $this->imgRoot['ROOT_ALIAS_NAME']) . $imagesFolder;
        FileHelper::createDirectory($t['saveRoot'], self::GENERATE_SUB_FOLDER_MODE);

        $t['showRoot'] = (Yii::$app->id == $this->imgRoot['APP_OWNER'] ?
                BaseUrl::base(true) . DIRECTORY_SEPARATOR . $this->imgRoot['ROOT_ALIAS_NAME'] :
                Yii::$app->assetManager->getPublishedUrl(Yii::getAlias('@' . $this->imgRoot['ROOT_ALIAS_NAME'])))
            . $imagesFolder;

        $modelFolder = get_class($this->owner);
        $modelFolder = substr($modelFolder, strripos($modelFolder, '\\') + 1) ;
        $modelFolder = strtolower(substr($modelFolder, 0, 1)) . substr($modelFolder, 1);

        foreach ($this->imagesSettings as $attribute => $iS) {
            $img =& $t['pathTree'][$attribute];
            $img['attr'] = $iS['imageAttribute'];
            $img['dir'] = ((isset($iS['saveFolder']) && $iS['saveFolder']) ?
                    $iS['saveFolder'] : $modelFolder . DIRECTORY_SEPARATOR . $iS['imageAttribute'])
                . DIRECTORY_SEPARATOR;

            foreach ($iS['variants'] as $variant => $vS) {
                $keyName = (isset($vS['name']) && $vS['name']) ? $vS['name'] : $variant;
                $vS['dir'] = $keyName ? $keyName . DIRECTORY_SEPARATOR : '';
                $vS['keyName'] = $keyName ?
                    strtoupper(substr($keyName, 0, 1)) . substr($keyName, 1) : '';

                $img['variants'][$variant] = $vS;
            }
        }

        return $t;
    }

    /**
     * Init images and images variants paths of the AR model class
     */
    public function afterInit()
    {
        $this->pathTree();
    }

    /**
     * Init images and images variants urls for AR model object
     */
    public function afterFind()
    {
        $t = $this->pathTree();

        foreach ($t['pathTree'] as $attribute) {
            $showPath = $t['showRoot'] . $attribute['dir'];
            foreach ($attribute['variants'] as $variant) {
                $this->imagesUrls[strtolower($attribute['attr']) . $variant['keyName']] = $this->owner->{$attribute['attr']} ?
                    $showPath . $variant['dir'] . $this->owner->{$attribute['attr']} : null;
            }
        }
    }

    /**
     * Insert uploaded images names to the corresponding AR Model object attributes before AR Model object validation
     */
    public function beforeValidate()
    {
        foreach ($this->imagesSettings as $imageSettings) {
            $this->owner->{$imageSettings['imageAttribute']} =
                UploadedFile::getInstance($this->owner, $imageSettings['imageAttribute']);
        }
    }

    /**
     * @param bool $deleteModel true whether model is deleting - delete images only in this case
     */
    private function manageImages($deleteModel = false)
    {
        $t = $this->pathTree();

        foreach ($t['pathTree'] as $attribute) {
            $attr = $attribute['attr'];
            $savePath = $t['saveRoot'] . $attribute['dir'];
            if (!$file = $this->owner->$attr) {
                $this->owner->$attr = $this->owner->getOldAttribute($attr);
                continue;
            }

            $fileName = self::generateImageName($file->baseName) . '.' . $file->extension;
            $oldFileName = $this->owner->getOldAttribute($attr);
            $savedFromRequestTmp = null;

            foreach ($attribute['variants'] as $variant) {
                $variantSavePath = $savePath . $variant['dir'];
                if ($oldFileName && file_exists($variantSavePath . $oldFileName)) {
                    unlink($variantSavePath . $oldFileName);
                }

                if ($deleteModel) {
                    continue;
                }

                FileHelper::createDirectory($variantSavePath, self::GENERATE_SUB_FOLDER_MODE);

                if (!$savedFromRequestTmp) {
                    $savedFromRequestTmp = $t['saveRoot'] . $fileName;
                    $file->saveAs($savedFromRequestTmp);
                }

                self::processImage($variant, $savedFromRequestTmp, $variantSavePath . $fileName);
            }

            unlink($savedFromRequestTmp);

            $this->owner->$attr = $fileName;
        }
    }

    public function beforeDelete()
    {
        $this->manageImages(true);
    }

    public function beforeSave()
    {
        $this->manageImages();
    }
}
