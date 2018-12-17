<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/19
 * Time: 16:15
 */

namespace app\models;


use yii\db\ActiveRecord;

class Usercoupon extends ActiveRecord
{
        public static function tableName()
        {
            return "user_coupon";
        }
}
