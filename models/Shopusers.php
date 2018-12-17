<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/28
 * Time: 02:17
 */

namespace app\models;


use yii\db\ActiveRecord;

class Shopusers extends ActiveRecord
{
    public static function tableName()
    {
        return "shop_users";
    }
}
