<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/24
 * Time: 11:52
 */

namespace app\models;


use yii\db\ActiveRecord;

class Couponsendlog extends  ActiveRecord

{
        public static function tableName()
        {
            return "coupon_send_log";
        }
}
