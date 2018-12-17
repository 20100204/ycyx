<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/25 0025
 * Time: 下午 12:16
 */

namespace app\models;


use yii\db\ActiveRecord;

class Admin extends ActiveRecord
{


    public static function tableName()
    {
       return "admin";
    }
}