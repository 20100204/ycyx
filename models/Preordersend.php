<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/9
 * Time: 3:59
 */

namespace app\models;


use yii\db\ActiveRecord;

class Preordersend extends ActiveRecord
{
        public static function tableName()
        {
            return "preorder_send";
        }
}