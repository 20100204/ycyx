<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/26
 * Time: 13:50
 */

namespace app\controllers;


use yii\web\Controller;

class BaseController extends Controller
{
        public function __construct($id, Module $module, array $config = [])
        {

            $this->id = $id;
            $this->module = $module;
            parent::__construct($config);
        //    parent::__construct($id, $module, $config);
        }
        public function init()
        {
            parent::init(); // TODO: Change the autogenerated stub
            return "aaaaaaa";
        }

}
