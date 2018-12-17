<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/11
 * Time: 19:40
 */

namespace app\models;


use yii\db\ActiveRecord;

class Shopapplychecklog extends ActiveRecord
{
        public static function tableName()
        {
            return "shop_apply_check_log";
        }
}
