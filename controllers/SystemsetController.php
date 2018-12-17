<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/5
 * Time: 17:22
 */

namespace app\controllers;


use app\common\behavior\NoCsrs;
use app\models\Ad;
use app\models\Admin;
use app\models\Adpos;
use app\models\Category;
use app\models\Config;
use yii\web\Controller;
use Yii;

class SystemsetController extends Controller
{


    public function behaviors()
    {
        return [
            'csrf' => [
                'class' => NoCsrs::className(),
                'controller' => $this,
                'actions' => [
                    'sethomedeliver',
                    "add",
                    "edit",
                    'list'

                ]
            ]
        ];
    }

    /*设置分公司运费*/
    public function actionSethomedeliver()
    {
        Yii::$app->response->format = 'json';
         $post = Yii::$app->request->post();
        if (!$post || $post['amount'] <= 0||$post['price']<0) {
            return ['rs' => 'false', 'msg' => "参数错误"];
        }
        $model = Config::find()->where("cfg_name='home_delivery_amount'")->one();
        $rs = json_decode($model->content, true);
        $rs[$post['area']] = ['amount'=>$post['amount'],'price'=>$post['price'],'subsidy'=>$post['subsidy']];
        $model->content = json_encode($rs);
        $model->updated_at = time();
        if (!$model->save(false)) {
            return ['rs' => 'false', 'msg' => "设置失败"];
        }
        return ['rs' => 'true'];

    }

    public function actionHomedeliver()
    {
        Yii::$app->response->format = 'json';
        $configModel = Config::find()->where("cfg_name='home_delivery_amount'")->one();
        $rs=[];
        if($configModel->content){
             $rs = json_decode($configModel->content, true);
        }
       // $rs = null;
        //取出所有正在使用游经理的分公司
        $citys = Admin::find()->select("area,city")->where("status=1 and admin_role_id=9")->asArray()->all();

        foreach ($citys as $ck => $cv) {
            $citys[$ck]['content']="";
            $citys[$ck]['price']=0;
            $citys[$ck]['amount']=0;
            $citys[$ck]['subsidy']=false;
            if($rs){
                foreach ($rs as $rk => $rv) {

                    if ($rk==$cv['area']){

                        $citys[$ck]['content']="满".$rv['amount']."元免运费".$rv['price']."元";
                        $citys[$ck]['amount']=$rv['amount'];
                        $citys[$ck]['price'] = $rv['price'];
                        $citys[$ck]['subsidy'] = $rv['subsidy'];
                        continue;
                    }
                }
            }

        }

        return ['rs' => $citys];
    }




}
