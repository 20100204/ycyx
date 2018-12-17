<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/30
 * Time: 20:13
 */

namespace app\controllers;

use app\common\behavior\NoCsrs;
use app\models\Access;
use app\models\AccessSummary;
use app\models\Role;
use yii\web\Controller;
use Yii;

class RoleController extends Controller
{

    public function behaviors()
    {
        return [
            'csrf' => [
                'class' => NoCsrs::className(),
                'controller' => $this,
                'actions' => [
                    'list',
                    "add",
                    'edit',
                    'save',
                    'access',
                    'saveaccess'

                ]
            ]
        ];
    }

    /*权限排序*/
    private function sort($access,$checked)
    {

        foreach ($access as $k => $v) {

            if($checked){
                foreach ($checked as $ck=>$cv){
                    if($ck==$v['path']){
                        $access[$k]['accesss']=$cv;
                    }
                }

            }

            foreach ($access as $kk => $vv) {
                if ($vv['parent_path'] == $v['path'] && $vv['level'] == 2) {
                    $access[$k]['childrens'][] = $vv;
                }
            }
        }
        foreach ($access as $n => $nv) {
            if ($nv['level'] == 2) {
                unset($access[$n]);
            }
        }
       // sort($access);
        return $access;
    }

    /* 权限管理*/
    public function actionAccess()
    {
        Yii::$app->response->format = 'json';
        $roleId = Yii::$app->request->get('id');
        $rs = Access::find()->where('role_id=' . $roleId)->one();
        if($rs){
            $rs = unserialize($rs->accesss);

        }
        $all = AccessSummary::find()->asArray()->where('id>=1')->orderBy('id')->all();
        return  $all = $this->sort($all,$rs);

    }


    /*设置权限*/
    public function actionSaveaccess(){
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        $access = $post['access'];
        $roleId = $post['role_id'];
        $auth=[];
        $alias=[];

        foreach ($access['all'] as $k=>$v){
            if(@$v['accesss']){
                $auth[$v['path']]=$v['accesss'];
                foreach ($v['childrens'] as $kk=>$vv){
                    foreach ($v['accesss'] as $vvv){
                        //return $vvv;
                        if($vvv==$vv['desc']){
                            $alias[]=$vv['path'];
                        }
                    }
                }
            }
        }

        $auth=$auth?serialize($auth):'';
        $alias=$alias?implode('|',$alias):'';
        if(!$accessModel =Access::find()->where('role_id='.$roleId)->one()){
            $accessModel = new Access();
        }
        $accessModel->role_id=$roleId;
        $accessModel->accesss=$auth;
        $accessModel->path = $alias;
        if(!$accessModel->save('false')){
            return ['rs'=>'false'];
        }
        return ['rs'=>'true'];
    }

    public function actionList()
    {
        Yii::$app->response->format = 'json';
         $parentId = Yii::$app->request->get('id');
          $parentId =$parentId?$parentId:0;
        return Role::find()->where('parent_id='.$parentId)->orderBy('updated_at desc')->all();
    }

    public function actionAdd()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        $model = new Role();
        $model->load($post, 'role', false);
        $model->created_at = date("Y-m-d H:i:s");
        $model->updated_at = date("Y-m-d H:i:s");
        if ($model->save(false)) {
            return ['rs' => "true"];
        } else {
            return ['rs' => "false", 'msg' => '添加失败'];
        }
    }

    public function actionEdit()
    {

    }

    public function actionSave()
    {

    }
}
