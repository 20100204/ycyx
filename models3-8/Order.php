<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/29
 * Time: 20:48
 */

namespace app\models;


use yii\db\ActiveRecord;

class Order extends  ActiveRecord
{
    public static function tableName()
    {
        return "order";
    }

}