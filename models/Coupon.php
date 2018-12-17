<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/20
 * Time: 17:02
 */

namespace app\models;


use yii\db\ActiveRecord;

class Coupon extends ActiveRecord
{
     public static function tableName()
     {
         return "coupon";
     }
}
