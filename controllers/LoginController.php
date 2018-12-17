<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/15 0015
 * Time: 下午 5:34
 */

namespace app\controllers;
use app\common\behavior\NoCsrs;
use app\models\Access;
use app\models\Admin;
use app\models\Role;
use Yii;
use yii\web\Controller;
use yii\filters\Cors;
class LoginController extends BaseController
{

    public function behaviors()
    {
        return [
            'csrf' => [
                'class' => NoCsrs::className(),
                'controller' => $this,
                'actions' => [
                    'log',
                    'logout'
                ]
            ],

        ];
    }



    public function actionGetadminid(){
       Yii::$app->response->format = 'json';
       // return ['rs'=>'false'];
      if(!Yii::$app->session->get('admin_id')){
          return ['rs'=>'false'];
      }
    }
    public function actionOut(){

        Yii::$app->response->format = 'json';
        Yii::$app->session->removeAll();
        return ['rs'=>'true'];
    }

    public function actionLog()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        if(!$post['userName']||!$post['password']){
            return ['rs'=>'false','msg'=>'用户名或密码为空'];
        }

          if(!$userModel= Admin::find()->where('username="'.$post['userName'].'"')->one()){
            return ['rs'=>'false','msg'=>'用户名错误'];
        }

        $password = hash('sha256', trim($post['password']) . Yii::$app->params['passwordkey'], false);

        if($password!=$userModel->password){
            return ['rs'=>'false','msg'=>'密码错误'];
        }
        if($userModel->status!=1){
            return ['rs'=>'false','msg'=>'账户已停用'];
        }

        //设置session信息；
        Yii::$app->session->set('admin_id',$userModel->id);
        Yii::$app->session->set('passwd',$post['password']);
        Yii::$app->session->set('username',$userModel->username);
        //角色类型：

        Yii::$app->session->set('role_id',$userModel->admin_role_id);
        $parentroleId = Role::find()->select('parent_id')->where('id='.$userModel->admin_role_id)->one();
        Yii::$app->session->set('parent_admin_role_id',$parentroleId->parent_id);
        Yii::$app->session->set('role_area',$userModel->area);
        //不能admin时获取权限列表
        $auth ="";
        if($post['userName']!='admin'){
           $auth = Access::find()->select('path')->asArray()->where('role_id='.$userModel->admin_role_id)->one();
            if($auth){
                $auth=$auth['path'];
            }

        }
       Yii::$app->session->set('authlist',$auth);
        /*if($parentroleId->parent_id==1){
            $url='settlement';
        }else{
            $url='activity_index';
        }*/
        return ['rs'=>'true','auth'=>@$auth];


        //return json_encode(['rs' => true], JSON_UNESCAPED_UNICODE);
    }

    public function actionUp()
    {
        $this->enableCsrfValidation = false;
        return 'https://ss0.bdstatic.com/70cFuHSh_Q1YnxGkpoWK1HF6hhy/it/u=444336547,2488503555&fm=15&gp=0.jpg';
        return json_encode(['url' => 'https://ss0.bdstatic.com/70cFuHSh_Q1YnxGkpoWK1HF6hhy/it/u=444336547,2488503555&fm=15&gp=0.jpg','status'=>'finished','percentage'=>100], JSON_UNESCAPED_UNICODE);
         $rs = Yii::$app->request->post();
       //  return json_encode($_FILES, JSON_UNESCAPED_UNICODE);
        return json_encode('ssssssss',JSON_UNESCAPED_UNICODE);
    }

    public function actionEdit(){
        header("Access-Control-Allow-Origin:*");
        header('Access-Control-Allow-Headers: X-Requested-With,X_Requested_With');
        header("Access-Control-Allow-Methods:*");
        return json_encode(['rs' => true], JSON_UNESCAPED_UNICODE);
    }
}
