<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/19 0019
 * Time: 上午 7:21
 */

namespace app\models;


use yii\db\ActiveRecord;

class Sku extends ActiveRecord
{

public static function tableName()
{
    return "item_sku";
}
}