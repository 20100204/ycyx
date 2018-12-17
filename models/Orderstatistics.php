<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/16
 * Time: 15:26
 */

namespace app\models;


use yii\db\ActiveRecord;

class Orderstatistics extends ActiveRecord
{

    public static function tableName()
    {
        return "order_statistics";
    }
}
