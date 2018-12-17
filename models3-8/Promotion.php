<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/26
 * Time: 18:10
 */

namespace app\models;


use yii\db\ActiveRecord;

class Promotion extends ActiveRecord
{
   public static function tableName()
   {
       return "promotion";
   }
}