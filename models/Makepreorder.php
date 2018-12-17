<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/27
 * Time: 17:25
 */

namespace app\models;


use yii\db\ActiveRecord;

class Makepreorder extends  ActiveRecord
{
    public static function tableName()
    {
        return "make_preorder";
    }
}
