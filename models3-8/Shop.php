<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/2/1
 * Time: 17:21
 */

namespace app\models;


use yii\db\ActiveRecord;

class Shop extends ActiveRecord
{

    public static function tableName()
    {
        return 'shop';
    }
}