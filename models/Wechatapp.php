<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/27
 * Time: 08:23
 */

namespace app\models;


use yii\db\ActiveRecord;

class Wechatapp extends ActiveRecord
{
    public static function tableName()
    {
        return "user_rel_wechat_app";
    }
}
