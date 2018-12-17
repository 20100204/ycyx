<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/17 0017
 * Time: 下午 2:25
 */

namespace app\models;

use yii\base\Model;
use Yii;

class UploadPic extends Model
{
    public $upload;

     public function rules()
    {
        return [
            [['upload'], 'file', 'skipOnEmpty' => false, 'extensions' => 'png,jpg,jpeg']
        ];
    }

    public function upload()
    {

        //if ($this->validate()) {
            $path = Yii::$app->params['uploadImg'] . $this->upload->baseName . '.' . $this->upload->extension;
            $this->imgFile->saveAs($path);
            return $path;
        //}
    }
}