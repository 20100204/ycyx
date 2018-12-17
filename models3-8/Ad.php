<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/5
 * Time: 17:25
 */

namespace app\models;


use yii\db\ActiveRecord;

class Ad extends ActiveRecord
{
    public static function tableName()
    {
        return "ad";
    }
}
