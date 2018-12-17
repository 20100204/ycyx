<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/22
 * Time: 15:55
 */

namespace app\models;


use yii\db\ActiveRecord;

class Shareordergiftmoney extends ActiveRecord
{
    public static function tableName()
    {
        return "share_order_gift_money";
    }

}
