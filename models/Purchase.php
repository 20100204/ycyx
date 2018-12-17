<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/27
 * Time: 18:30
 */

namespace app\models;


use yii\db\ActiveRecord;

class Purchase extends ActiveRecord
{
    public static function tableName()
    {
        return "purchase";
    }
}
