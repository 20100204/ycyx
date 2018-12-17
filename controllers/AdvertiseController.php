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
use yii\web\Controller;
use Yii;

class AdvertiseController extends Controller
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
                    "editsave",
                    'list'

                ]
            ]
        ];
    }
    /*status*/
    public function actionStatus(){
        Yii::$app->response->format = 'json';
        $get = Yii::$app->request->get();
        if(!$get['id']||!$get['status']){
            return ['rs'=>'false','msg'=>'参数缺失'];
        }
        $admodel = Ad::findOne($get['id']);
        $admodel->status = $get['status'];
        $admodel->updated_at =  time();
        if(!$admodel->save(false)){
            return ['rs'=>'false','msg'=>'操作失败'];
        }
        return ['rs'=>'true'];

    }

    public function actionList()
    {
        Yii::$app->response->format = 'json';
        $where = [];
        $curPage = 1;
        $city = [];
        $isCity = false;
        if (Yii::$app->request->isPost) {
            $search = Yii::$app->request->post();
            foreach ($search as $k => $v) {
                if ($k == 'curpage') {
                    $curPage = $v;
                    continue;
                }

                    if($k=='area'){
                            if($v){
                                $where[]= ' (ad.area like "%'. $v.'%" or isnull(ad.area)) ';

                            }else{
                                $where[]= ' isnull(ad.area) ';

                            }

                    }elseif ($v){
                        $where[] = 'ad.'.$k . ' like "%' . trim($v) . '%"';
                    }


            }
        }
        if (Yii::$app->session->get('parent_admin_role_id') == '2') {

            $where[]= ' (ad.area like "%'. Yii::$app->session->get("role_area").'%" or isnull(ad.area)) ';
            $isCity = true;
        }
        if(!$isCity){
            $query = new \yii\db\Query();
            $city = $query->select('a.city,a.area')->from('admin as a')->leftJoin('admin_role as b', 'a.admin_role_id=b.id')->distinct()->where('b.parent_id=2 and a.status=1')->all();
            array_unshift($city,['city'=>'全国','area'=>""]);
        }
        $where = $where ? implode(' and ', $where) : [];
        $pageSize = Yii::$app->params['pagesize'];
        $query = new \yii\db\Query();
        $curPage = $curPage ? $curPage : 1;
        $offset = $pageSize * ($curPage - 1);
        $query = new \yii\db\Query();
        $query = $query->select('ad.*,b.title as pos')
            ->from('ad')
            ->leftJoin('ad_postion as b', 'ad.pos_id=b.id');
        if ($where) {
            $query = $query->where($where);
        }
        $count = $query->count();
        $rs = $query->offset($offset)->limit($pageSize)->orderBy('ad.updated_at desc')->all();
        foreach ($rs as $k => $v) {
            $rs[$k]['updated_at'] = date("Y-m-d H:i:s", $v['updated_at']);
            $content = json_decode($v['content'],true);
            if($content['type']=='pic'){
                $rs[$k]['type'] = '纯图片';
            }elseif ($content['type']=='preorder'){
                $rs[$k]['type'] = '预售';
            }elseif ($content['type']=='coupon'){
                $rs[$k]['type'] = '团购';
            }elseif ($content['type']=='cat_id'){
                $rs[$k]['type'] = '分类';
            }
            $rs[$k]['content'] = $content['pic'];
            if(isset($content['value'])){
                $rs[$k]['value'] = $content['value'];
            }else{
                $rs[$k]['value'] = '';
            }
            if($v['area']){
                 $area = explode(',',$v['area']);
                 $area = Admin::find()->where(['area'=>$area])->asArray()->distinct()->select('city')->all();
                 $area = array_column($area,'city');
                 $rs[$k]['area'] = implode(',',$area);
            }else{
                $rs[$k]['area']="全国";
            }
        }
        $enableCheck = false;//审核权限
        $enableEdit = false;//编辑权限
        $enableAdd = false;//添加活动


        if (Yii::$app->session->get('username') != 'admin') {
            $aulist = explode('|', Yii::$app->session->get('authlist'));
            if (!in_array('advertiseedit', $aulist)) {
                $enableEdit = true;
            }
            if (!in_array('advertiseadd', $aulist)) {
                $enableAdd = true;
            }
            if (!in_array('advertisemanagercheck', $aulist)) {
                $enableCheck = true;
            }

        }
        return ['rs' => $rs, 'totalPage' => $count, 'pageSize' => $pageSize, 'search' => @$search, 'currpage' => $curPage,'auth'=>[],'city'=>$city,'isCity'=>$isCity,'add'=>$enableAdd,'edit'=>$enableEdit,'check'=>$enableCheck];
    }

    public function actionAdd(){
        Yii::$app->response->format = 'json';
        if(Yii::$app->request->isGet){
            $isCity = false;
            $city = [];
            if (Yii::$app->session->get('parent_admin_role_id') == '2') {
                $isCity = true;
                $city[]= Yii::$app->session->get("role_area");
            }else{
                $query = new \yii\db\Query();
                $city = $query->select('a.city,a.area')->from('admin as a')->leftJoin('admin_role as b', 'a.admin_role_id=b.id')->distinct()->where('b.parent_id=2 and a.status=1')->all();
               // array_unshift($city,['city'=>'全国','area'=>"全国"]);
            }
            $location =Adpos::find()->asArray()->where('id>=1')->all();
            $types =[ ['title'=>'纯图片','id'=>'pic'],['title'=>'团购','id'=>'coupon'],['title'=>'预售','id'=>'preorder'],['title'=>'分类','id'=>'cat_id']];
            return ['location'=>$location,'isCity'=>$isCity,'city'=>$city,'types'=>$types];
        }
        if(Yii::$app->request->isPost){
          $post = Yii::$app->request->post();
          $adModel = new Ad();
          if($post['ad']['area']){
              if (Yii::$app->session->get('parent_admin_role_id') != '2') {
                  $post['ad']['area'] =implode(',',$post['ad']['area']);
              }
          }else{
              $post['ad']['area']=null;
          }

          if(!$post['ad']['pos_id']||!$post['ad']['content']||!$post['ad']['type']){
              return ['rs'=>'false','msg'=>'数据错误'];
          }
          if($post['ad']['type']!='pic'){
              if($post['ad']['value']<0){
                  return ['rs'=>'false','msg'=>'数据错误'];
              }
              $post['ad']['content'] = json_encode(['type'=>$post['ad']['type'],'pic'=>$post['ad']['content'],'value'=>$post['ad']['value']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
          }else{
              $post['ad']['content'] = json_encode(['type'=>$post['ad']['type'],'pic'=>$post['ad']['content']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
          }
           if(!$adModel->load($post,'ad',false)){
               return ['rs'=>'false','msg'=>'数据错误'];
           }
          // return $adModel;
           $adModel->admin_id = Yii::$app->session->get('admin_id');
           $adModel->created_at = time();
           $adModel->status = 2;
           $adModel->updated_at = time();
           if($adModel->save(false)){
               return ['rs'=>'true'];
           }
            return ['rs'=>'false','msg'=>'数据错误'];
        }
    }
    /*编辑*/
    public function actionEdit(){
        Yii::$app->response->format = 'json';
        $adId = Yii::$app->request->get('id');
        $rs = Ad::find()->asArray()->where('id=' . $adId)->one();
        if($rs['area']){
            $rs['area'] = explode(',',$rs['area']);
        }
        $content = json_decode($rs['content'],true);
        $rs['content'] = $content['pic'];
        $rs['type'] = $content['type'];
        if(isset($content['value'])){
            $rs['value'] = $content['value'];
        }else{
            $rs['value'] = 0;
        }
       // return $rs;
        $location =Adpos::find()->asArray()->where('id>=1')->all();
        $isCity = false;
        $city = [];
        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            $isCity = true;
            $city[]= Yii::$app->session->get("role_area");
        }else{
            $query = new \yii\db\Query();
            $city = $query->select('a.city,a.area')->from('admin as a')->leftJoin('admin_role as b', 'a.admin_role_id=b.id')->distinct()->where('b.parent_id=2 and a.status=1')->all();
           // array_unshift($city,['city'=>'全国','area'=>""]);

        }
        $types =[ ['title'=>'纯图片','id'=>'pic'],['title'=>'团购','id'=>'coupon'],['title'=>'预售','id'=>'preorder'],['title'=>'分类','id'=>'cat_id']];
        return ['ad'=>$rs,'location'=>$location,'isCity'=>$isCity,'city'=>$city,'types'=>$types];
    }
    /*编辑保存*/
    public function actionEditsave(){
        Yii::$app->response->format = 'json';
       $post = Yii::$app->request->post();
         $adModel = Ad::findOne($post['ad']['id']);
        $adModel->updated_at=time();
        if($post['ad']['area']){
            $post['ad']['area'] =implode(',',$post['ad']['area']);
        }else{
            $post['ad']['area']= null;
        }
        if(!$post['ad']['pos_id']||!$post['ad']['content']||!$post['ad']['type']){
            return ['rs'=>'false','msg'=>'数据错误'];
        }
        if($post['ad']['type']!='pic'){
            if($post['ad']['value']<0){
                return ['rs'=>'false','msg'=>'数据错误'];
            }
            $post['ad']['content'] = json_encode(['type'=>$post['ad']['type'],'pic'=>$post['ad']['content'],'value'=>$post['ad']['value']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }else{
            $post['ad']['content'] = json_encode(['type'=>$post['ad']['type'],'pic'=>$post['ad']['content']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }

        if($adModel->load($post,'ad',false)&&$adModel->save(false)){
           // return $adModel;
            return ['rs'=>'true'];
        }
        return ['rs'=>'false','msg'=>'更新失败'];
    }

}
