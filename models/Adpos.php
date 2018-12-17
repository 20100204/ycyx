<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/5
 * Time: 18:10
 */

namespace app\models;


use yii\db\ActiveRecord;

class Adpos extends ActiveRecord
{
    public static function tableName()
    {
        return "ad_postion";
    }
}
