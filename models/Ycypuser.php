<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/2/1
 * Time: 17:20
 */

namespace app\models;


use yii\db\ActiveRecord;

class Ycypuser extends ActiveRecord
{

    public static function tableName()
    {
        return 'user';
    }

}