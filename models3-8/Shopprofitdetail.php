<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/2/14
 * Time: 21:20
 */

namespace app\models;


use yii\db\ActiveRecord;

class Shopprofitdetail extends  ActiveRecord
{
        public static function tableName()
        {
            return "shop_order_detail_profit_rate";
        }
}