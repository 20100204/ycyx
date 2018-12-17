<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/25
 * Time: 16:55
 */

namespace app\models;


use yii\db\ActiveRecord;

class Preorder extends ActiveRecord
{
    public static function tableName()
    {
        return  "promotion_preorder";
    }

}