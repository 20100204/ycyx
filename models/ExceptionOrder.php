<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/16
 * Time: 19:35
 */

namespace app\models;


use yii\db\ActiveRecord;

class ExceptionOrder extends ActiveRecord
{
    public static function tableName()
    {
        return "exception_order";
    }
}
