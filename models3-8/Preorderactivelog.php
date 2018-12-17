<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/2/7
 * Time: 11:16
 */

namespace app\models;


use yii\db\ActiveRecord;

class Preorderactivelog extends ActiveRecord
{
    public static function tableName()
    {
        return  'preorder_active_log';
    }

}