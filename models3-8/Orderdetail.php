<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/29
 * Time: 17:45
 */

namespace app\models;


use yii\db\ActiveRecord;

class Orderdetail extends ActiveRecord
{
   public static function tableName()
   {
       return 'order_detail';
   }
}