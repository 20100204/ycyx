<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/22
 * Time: 17:43
 */

namespace app\models;


use yii\db\ActiveRecord;

class PromotionLabel extends  ActiveRecord
{
    public static function tableName()
    {
        return "promotion_label";
    }
}
