<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/21
 * Time: 16:30
 */

namespace app\models;


use yii\db\ActiveRecord;

class Coupondiscount extends ActiveRecord
{
        public static function tableName()
        {
            return "coupon_discount";
        }

}
