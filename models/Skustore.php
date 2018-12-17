<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/2/4
 * Time: 05:34
 */

namespace app\models;


use yii\db\ActiveRecord;

class Skustore extends ActiveRecord
{
    public static function tableName()
    {
        return "item_sku_store";
    }
}