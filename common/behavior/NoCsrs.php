<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/17 0017
 * Time: 下午 2:19
 */

namespace app\common\behavior;
use yii\web\Controller;
use Yii;
use yii\base\Behavior;

class NoCsrs extends Behavior
{
    public $actions = [];
    public $controller;
    public function events()
    {
        return [Controller::EVENT_BEFORE_ACTION => 'beforeAction'];
    }
    public function beforeAction($event)
    {

        $action = $event->action->id;
        if(in_array($action, $this->actions)){
            $this->controller->enableCsrfValidation = false;
        }

        if(Yii::$app->request->headers['ycyp']=='b157235195ea0b121c4455cdc34e7089'){
            return ['rs'=>'false','url'=>'login'];
        }
    }
}
