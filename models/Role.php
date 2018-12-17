<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/30
 * Time: 20:13
 */

namespace app\models;


use yii\db\ActiveRecord;

class Role extends ActiveRecord
{
        public static function tableName()
        {
            return 'admin_role';
        }
}