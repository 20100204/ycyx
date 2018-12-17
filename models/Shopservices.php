<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/3
 * Time: 18:00
 */

namespace app\models;


use yii\db\ActiveRecord;

class Shopservices extends ActiveRecord
{
    public static function tableName()
    {
        return "shop_services";
    }
}
