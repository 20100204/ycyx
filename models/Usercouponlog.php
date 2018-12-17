<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/24
 * Time: 12:15
 */

namespace app\models;


use yii\db\ActiveRecord;

class Usercouponlog extends ActiveRecord
{
        public static function tableName()
        {
            return "user_coupon_log";
        }
}
