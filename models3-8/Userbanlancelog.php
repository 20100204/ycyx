<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/2/14
 * Time: 21:50
 */

namespace app\models;


use yii\db\ActiveRecord;

class Userbanlancelog extends ActiveRecord
{

    public static function tableName()
    {
        return "user_balance_log";
    }
}