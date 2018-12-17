<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/22
 * Time: 17:02
 */

namespace app\models;


use yii\db\ActiveRecord;

class Userwithdrawalapply extends ActiveRecord
{
   public static function tableName()
   {
       return "user_withdrawal_apply";
   }
}
