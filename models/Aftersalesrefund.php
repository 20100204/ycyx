<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/26
 * Time: 22:27
 */

namespace app\models;


use yii\db\ActiveRecord;

class Aftersalesrefund extends ActiveRecord
{
    public static function tableName()
    {
        return  "aftersale_refund";
    }
}
