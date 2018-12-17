<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/2/10
 * Time: 13:49
 */

namespace app\models;


use yii\db\ActiveRecord;

class Shoporderprofitrate extends ActiveRecord
{
    public static function tableName()
    {
        return "shop_order_profit_rate";
    }

}