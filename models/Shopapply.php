<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/11
 * Time: 19:36
 */

namespace app\models;


use yii\db\ActiveRecord;

class Shopapply extends ActiveRecord
{
    public static function tableName()
    {
        return "shop_apply";
    }
}
