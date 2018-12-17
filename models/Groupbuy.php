<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/15
 * Time: 14:58
 */

namespace app\models;


use yii\db\ActiveRecord;

class Groupbuy extends ActiveRecord
{
    public static function tableName()
    {
        return "promotion_groupon";
    }

}
