<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/5
 * Time: 16:56
 */

namespace app\models;


use yii\db\ActiveRecord;

class Warehouse extends ActiveRecord
{

    public static function tableName()
    {
        return "warehouse";
    }
}
