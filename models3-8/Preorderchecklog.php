<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/2/7
 * Time: 15:56
 */

namespace app\models;


use yii\db\ActiveRecord;

class Preorderchecklog extends ActiveRecord
{

        public static function tableName()
        {
            return 'preorder_check_log';
        }
}