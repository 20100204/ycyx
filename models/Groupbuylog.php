<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/20
 * Time: 17:54
 */

namespace app\models;


use yii\db\ActiveRecord;

class Groupbuylog extends ActiveRecord
{

    public static function tableName()
    {
        return "groupbuy_log";
    }

}
