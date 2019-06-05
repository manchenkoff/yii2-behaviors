<?php
/**
 * Created by Artyom Manchenkov
 * artyom@manchenkoff.me
 * manchenkoff.me © 2019
 */

namespace manchenkov\yii\behaviors;

use Yii;
use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii\helpers\FileHelper;
use yii\helpers\StringHelper;
use yii\web\UploadedFile;

/**
 * Automatic file upload behavior for ActiveRecord
 * @package Manchenkov\Yii\Behaviors
 *
 * Example:
 * ```
 *
 * public function rules()
 * {
 *    return [
 *       [['title', 'image'], 'required'],
 *       ['title', 'string'],
 *       ['image', 'file', 'skipOnEmpty' => true, 'extensions' => ['jpg']],
 *    ];
 * }
 *
 * public function behaviors()
 * {
 *     return [
 *        [
 *          'class' => FileUploadBehavior::class,
 *          'storagePath' => '@storage',
 *          'uploadPath' => '/uploads/images',
 *          'attributes' => ['image'],
 *          'callback' => function (string $filename) {...},
 *        ],
 *     ];
 * }
 * ```
 */
class FileUploadBehavior extends Behavior
{
    /**
     * @var string A path or an alias to base storage directory (local path)
     */
    public $storagePath;

    /**
     * @var string A path to uploads directory (web accessible)
     */
    public $uploadPath;

    /**
     * @var array List of attributes to process uploading
     */
    public $attributes;

    /**
     * @var callable Callback function to work with image after upload `function (string $imagePath) {...}`
     */
    public $callback;

    /**
     * Binding behavior methods to ActiveRecord's events
     * @return array
     */
    public function events()
    {
        return [
            ActiveRecord::EVENT_BEFORE_VALIDATE => 'beforeValidate',

            ActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
            ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',

            ActiveRecord::EVENT_AFTER_DELETE => 'afterDelete',
        ];
    }

    /**
     * Loads files before ActiveRecord validation process
     */
    public function beforeValidate()
    {
        /** @var ActiveRecord $model */
        $model = $this->owner;

        foreach ($this->attributes as $attr) {
            $file = UploadedFile::getInstance($model, $attr);

            if ($file) {
                $model->{$attr} = $file;
            }
        }
    }

    /**
     * Saves uploaded files and set new ActiveRecord URLs
     */
    public function beforeSave()
    {
        /** @var ActiveRecord $model */
        $model = $this->owner;

        foreach ($this->attributes as $attr) {
            /** @var UploadedFile $file */
            $file = $model->{$attr};

            // process uploaded file or keep existing string value
            if ($file instanceof UploadedFile) {
                $oldFile = $model->oldAttributes[$attr] ?? null;
                $newFile = $this->store($file);

                if ($newFile) {
                    $model->{$attr} = $newFile;

                    if ($oldFile && $newFile != $oldFile) {
                        $this->removeFile($oldFile);
                    }

                    // use callback function if exists
                    if (!is_null($this->callback)) {
                        call_user_func($this->callback, $newFile);
                    }
                } else {
                    $model->addError($attr, Yii::t('yii', 'File upload failed.'));
                }
            }
        }
    }

    /**
     * Stores new uploaded file to the storage
     *
     * @param UploadedFile $file
     *
     * @return null|string
     */
    protected function store(UploadedFile $file)
    {
        $filePath = $this->buildUploadPath(
            $this->hashedFilename($file)
        );

        $destination = $this->buildStoragePath($filePath);

        try {
            if ($this->saveFile($file, $destination)) {
                return $filePath;
            } else {
                return null;
            }
        } catch (\Exception $exception) {
            return null;
        }
    }

    /**
     * Appends relative upload path to the given file name
     *
     * @param string $filename
     *
     * @return string
     */
    protected function buildUploadPath(string $filename)
    {
        $uploadPath = "/" . trim($this->uploadPath, '/ ') . "/";

        return $uploadPath . $filename;
    }

    /**
     * Returns a md5-hashed file name with extension
     *
     * @param UploadedFile $file
     *
     * @return string
     */
    private function hashedFilename(UploadedFile $file)
    {
        $hash = md5_file($file->tempName);

        return "{$hash}.{$file->extension}";
    }

    /**
     * Appends absolute storage path to the given string
     *
     * @param string $uploadPath
     *
     * @return bool|string
     */
    protected function buildStoragePath(string $uploadPath)
    {
        $uploadPath = StringHelper::startsWith($uploadPath, '/')
            ? $uploadPath
            : '/' . $uploadPath;

        return Yii::getAlias($this->storagePath . $uploadPath);
    }

    /**
     * Process file saving with directory creation if necessary
     *
     * @param UploadedFile $file
     * @param string $destination
     *
     * @return bool
     * @throws \yii\base\Exception
     */
    protected function saveFile(UploadedFile $file, string $destination)
    {
        $directory = dirname($destination);

        if (!is_dir($directory)) {
            FileHelper::createDirectory($directory);
        }

        return $file->saveAs($destination);
    }

    /**
     * Removes a file from a storage
     *
     * @param string $uploadedPath
     *
     * @return bool
     */
    protected function removeFile(string $uploadedPath)
    {
        $filePath = $this->buildStoragePath($uploadedPath);

        return @unlink($filePath);
    }

    /**
     * Cleans dependent files after delete record from a database
     */
    public function afterDelete()
    {
        $this->clean();
    }

    /**
     * Removes all of the record files in a storage
     */
    protected function clean()
    {
        foreach ($this->attributes as $attr) {
            if ($this->owner->{$attr}) {
                $this->removeFile($this->owner->{$attr});
            }
        }
    }
}