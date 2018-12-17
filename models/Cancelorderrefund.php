<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/27
 * Time: 15:08
 */

namespace app\models;


use yii\db\ActiveRecord;

class Cancelorderrefund extends ActiveRecord
{
    public static function tableName()
    {
        return "order_cancel_refund";
    }
}
