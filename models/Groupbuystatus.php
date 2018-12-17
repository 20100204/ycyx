<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/15
 * Time: 15:06
 */

namespace app\models;


use yii\db\ActiveRecord;

class Groupbuystatus extends ActiveRecord
{
    public static function tableName()
    {
        return "promotion_groupon_status";
    }

}
