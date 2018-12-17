<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/19
 * Time: 18:08
 */

namespace app\models;


use yii\db\ActiveRecord;

class Userrelshop extends ActiveRecord
{
    public static function tableName()
    {
        return "user_rel_shop";
    }

}
