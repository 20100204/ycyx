<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/23
 * Time: 17:16
 */

namespace app\models;


use yii\db\ActiveRecord;

class Supplierapply extends ActiveRecord
{

        public static function tableName()
        {
            return "supplier_apply";
        }
}
