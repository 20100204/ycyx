<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/2/22
 * Time: 18:25
 */

namespace app\models;


use yii\db\ActiveRecord;

class Access extends ActiveRecord
{
    public static function tableName()
    {
        return 'access';
    }
}
