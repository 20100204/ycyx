<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/22
 * Time: 17:44
 */

namespace app\models;


use yii\db\ActiveRecord;

class Userwithdrawalapply_log extends ActiveRecord
{
    public static function tableName()
    {
        return "user_withdrawal_apply_log";
    }
}
