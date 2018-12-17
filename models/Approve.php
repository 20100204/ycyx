<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/27
 * Time: 16:47
 */

namespace app\models;


use yii\db\ActiveRecord;

class Approve extends ActiveRecord
{

    public static function tableName()
    {
        return "approve";
    }
}
