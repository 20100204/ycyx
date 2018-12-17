<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/13
 * Time: 15:20
 */

namespace app\models;


use yii\db\ActiveRecord;

class Supplier extends ActiveRecord
{
   public static function tableName()
   {
       return "supplier";
   }


}
