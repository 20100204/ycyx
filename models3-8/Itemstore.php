<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/2/4
 * Time: 05:33
 */

namespace app\models;


use yii\db\ActiveRecord;

class Itemstore extends ActiveRecord
{
    public static function tableName()
    {
        return "item_store";
    }
}