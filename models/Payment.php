<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/2
 * Time: 19:40
 */

namespace app\models;


use yii\db\ActiveRecord;

class Payment extends  ActiveRecord
{
    public static function tableName()
    {
        return  "payment";
    }
}
