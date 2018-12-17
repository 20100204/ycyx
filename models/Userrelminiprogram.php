<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/11
 * Time: 12:11
 */

namespace app\models;


use yii\db\ActiveRecord;

class Userrelminiprogram extends ActiveRecord
{

    public static function tableName()
    {
        return "user_rel_miniprogram";
    }
}
