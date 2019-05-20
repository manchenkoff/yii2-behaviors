<?php
/**
 * Created by Artyom Manchenkov
 * artyom@manchenkoff.me
 * manchenkoff.me © 2019
 */

namespace Manchenkov\Yii\Behaviors;

use yii\base\Behavior;
use yii\db\ActiveRecord;

/**
 * Class JsonBehavior for automatic encode/decode JSON data from table columns
 * @package Manchenkov\Yii\Behaviors
 */
class JsonBehavior extends Behavior
{
    /**
     * @var array Model attributes with JSON type
     */
    public $attributes = [];

    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_FIND => 'decodeData',
            ActiveRecord::EVENT_AFTER_UPDATE => 'decodeData',

            ActiveRecord::EVENT_BEFORE_INSERT => 'encodeData',
            ActiveRecord::EVENT_BEFORE_UPDATE => 'encodeData',
        ];
    }

    /**
     * Decode JSON value into array
     */
    public function decodeData()
    {
        $model = $this->owner;

        foreach ($this->attributes as $attribute) {
            if (isset($model->{$attribute})) {
                $model->{$attribute} = json_decode($model->{$attribute}, true);
            }
        }
    }

    /**
     * Encode item array to JSON value before insertion
     */
    public function encodeData()
    {
        $model = $this->owner;

        foreach ($this->attributes as $attribute) {
            if (isset($model->{$attribute})) {
                $model->{$attribute} = json_encode($model->{$attribute}, JSON_PRETTY_PRINT);
            }
        }
    }
}