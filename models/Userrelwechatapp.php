<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/31
 * Time: 06:26
 */

namespace app\models;


use yii\db\ActiveRecord;

class Userrelwechatapp extends ActiveRecord
{
    public static function tableName()
    {
        return "user_rel_wechat_app";
    }
}
