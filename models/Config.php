<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/29
 * Time: 22:01
 */

namespace app\models;


use yii\db\ActiveRecord;

class Config extends ActiveRecord
{
    public static function tableName()
    {
        return "config";
    }
}
