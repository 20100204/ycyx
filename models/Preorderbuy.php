<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/6
 * Time: 00:11
 */

namespace app\models;


use yii\db\ActiveRecord;

class Preorderbuy extends ActiveRecord
{
    public static function tableName()
    {
        return "preorder_buy";
    }
}
