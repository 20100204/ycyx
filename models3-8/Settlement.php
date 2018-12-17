<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/2/9
 * Time: 14:25
 */

namespace app\models;


use yii\db\ActiveRecord;

class Settlement extends  ActiveRecord
{

    public static function tableName()
    {
        return "shop_settlement";
    }
}