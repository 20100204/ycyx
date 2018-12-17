<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/2/22
 * Time: 21:19
 */

namespace app\models;


use yii\db\ActiveRecord;

class AccessSummary extends ActiveRecord
{
    public static function tableName()
    {
        return "access_sumary";
    }
}
