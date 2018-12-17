<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/24
 * Time: 15:23
 */

namespace app\models;


use yii\db\ActiveRecord;

class Promotioncoupon extends ActiveRecord
{
    public static function tableName()
    {
        return "promotion_coupon";
    }

}
