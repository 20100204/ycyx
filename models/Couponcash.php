<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/20
 * Time: 17:11
 */

namespace app\models;


use yii\db\ActiveRecord;

class Couponcash extends ActiveRecord
{
    public static function tableName()
    {
        return "coupon_cash";
    }

}
