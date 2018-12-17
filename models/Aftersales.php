<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/26
 * Time: 21:57
 */

namespace app\models;


use yii\db\ActiveRecord;

class Aftersales extends ActiveRecord
{
    public static function tableName()
    {
        return "aftersale_apply";
    }

}
