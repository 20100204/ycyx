<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/9/13
 * Time: 14:23
 */

namespace app\models;

use yii\db\ActiveRecord;

class Category extends ActiveRecord
{


    public static function tableName()
    {
        return "category";
    }

}
