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
use yii\web\Controller;
use Yii;

class CategoryController extends Controller
{


    public function behaviors()
    {
        return [
            'csrf' => [
                'class' => NoCsrs::className(),
                'controller' => $this,
                'actions' => [
                    'uppic',
                    "add",
                    "edit",
                    'list'

                ]
            ]
        ];
    }


    /*二级分类*/
    public function actionTwo(){
        Yii::$app->response->format = 'json';
        $parent_id = Yii::$app->request->get('parent_id');
        $rs = Category::find()->where('parent_id='.$parent_id)->asArray()->all();
        foreach ($rs as $k => $v) {
            $rs[$k]['updated_at'] = date("Y-m-d H:i:s", $v['updated_at']);
        }
        return ['rs' => $rs];
    }
    /*status*/
    public function actionStatus()
    {
        Yii::$app->response->format = 'json';
        $get = Yii::$app->request->get();
        if (!$get['id']) {
            return ['rs' => 'false', 'msg' => '参数缺失'];
        }
        $admodel = Category::findOne($get['id']);
        $admodel->is_disabled = $get['status'];
        $admodel->updated_at = time();
        if (!$admodel->save(false)) {
            return ['rs' => 'false', 'msg' => '操作失败'];
        }
        return ['rs' => 'true'];

    }

    public function actionLists()
    {
        Yii::$app->response->format = 'json';
        $rs = Category::find()->asArray()->where('level=1')->orderBy("rank desc,updated_at desc")->all();
        foreach ($rs as $k => $v) {
            $rs[$k]['updated_at'] = date("Y-m-d H:i:s", $v['updated_at']);
        }
        $enableCheck = false;//审核权限
        $enableEdit = false;//编辑权限
        $enableAdd = false;//添加活动


        if (Yii::$app->session->get('username') != 'admin') {
            $aulist = explode('|', Yii::$app->session->get('authlist'));
            if (!in_array('editcategory', $aulist)) {
                $enableEdit = true;
            }
            if (!in_array('addcategory', $aulist)) {
                $enableAdd = true;
            }
            if (!in_array('statuscategory', $aulist)) {
                $enableCheck = true;
            }

        }
        return ['rs' => $rs,'enableCheck'=>$enableCheck,'enableEdit'=>$enableEdit,'enableAdd'=>$enableAdd];
    }

    public function actionEdit()
    {
        Yii::$app->response->format = 'json';
        $rs = ['rs' => 'true', 'data' => [], 'msg' => ''];
        if (Yii::$app->request->isPost){
           $post = Yii::$app->request->post();
            if(!$post['level']||!$post['cat_name']||!$post['id']){
                $rs['msg']="参数不全";
                $rs['rs'] = 'false';
                return $rs;
            }
            if(Category::find()->where('cat_name="'.$post['cat_name'].'" and level='.$post['level'].' and id!='.$post['id'])->one()){
                $rs['msg']="分类已经存在!";
                $rs['rs'] = 'false';
                return $rs;
            }
            $time = time();
            $categoryModel = Category::findOne($post['id']);
//            if($post['logo']){
//                $categoryModel->logo = $post['logo'];
//            }
            $categoryModel->cat_name = $post['cat_name'];
            $categoryModel->logo = $post['logo'];
            $categoryModel->parent_id = $post['parent_id'];
            $categoryModel->top_cat_id = $post['top_cat_id'];
            $categoryModel->rank = $post['rank'];
            $categoryModel->level = $post['level'];
            $categoryModel->updated_at =$time;
            if(!$categoryModel->save(false)){
                return['rs'=>'false','msg'=>"更新失败",'data'=>$post];
            }
            $post['is_disabled'] = 0;
            $post['updated_at'] =date("Y-m-d H:i:s",$time);
            $post['id'] = $categoryModel->id;
            $rs['data']= $post;
            return $rs;
        }
        return $rs;

    }

    public function actionAdd()
    {
        Yii::$app->response->format = 'json';
        $rs = ['rs' => 'true', 'data' => [], 'msg' => ''];
        if (Yii::$app->request->isPost){
            $post = Yii::$app->request->post();
            if(!$post['level']||!$post['cat_name']){
                $rs['msg']="参数不全";
                $rs['rs'] = 'false';
                return $rs;
            }
            if(Category::find()->where('cat_name="'.$post['cat_name'].'" and level='.$post['level'].' and parent_id='.@$post['parent_id'])->one()){
                $rs['msg']="分类已经存在!";
                $rs['rs'] = 'false';
                return $rs;
            }
            $time = time();
            $categoryModel = new Category();
            if($post['logo']){
                $categoryModel->logo = $post['logo'];
            }
            $categoryModel->cat_name = $post['cat_name'];
            $categoryModel->rank = $post['rank'];
            $categoryModel->parent_id = $post['parent_id'];
            $categoryModel->top_cat_id = $post['top_cat_id'];
            $categoryModel->level = $post['level'];
            $categoryModel->created_at =$time;
            $categoryModel->updated_at =$time;
            if(!$categoryModel->save(false)){
                return['rs'=>'false','msg'=>"插入失败",'data'=>$post];
            }
            $post['is_disabled'] = 0;
            $post['updated_at'] =date("Y-m-d H:i:s",$time);
            $post['id'] = $categoryModel->id;
            $rs['data']= $post;
            return $rs;
        }
        return $rs;

    }





}
