<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/19 0019
 * Time: 上午 1:54
 */

namespace app\models;


use yii\base\Model;
use yii\db\ActiveRecord;

class Item extends ActiveRecord
{

   /* public $title;
    public $barcode;
    public $cat_id;
    public $brand_id;
    public $pic;
    public $pics;
    public $unit;
    public $description;
    public $created_at;
    public $updated_at;*/

    public static function tableName()
    {
       return "item";
    }



}