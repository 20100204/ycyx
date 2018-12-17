<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/15
 * Time: 14:59
 */

namespace app\controllers;

use app\common\behavior\NoCsrs;
use app\models\Admin;
use app\models\Coupon;
use app\models\Couponcash;
use app\models\Groupbuy;
use app\models\Groupbuylog;
use app\models\Groupbuystatus;
use app\models\Order;
use app\models\Orderdetail;
use app\models\Preordersend;
use app\models\Sku;
use yii\db\Exception;
use yii\web\Controller;
use Yii;

class CouponcashController extends Controller
{
    public function behaviors()
    {
        return [
            'csrf' => [
                'class' => NoCsrs::className(),
                'controller' => $this,
                'actions' => [
                    'lists',
                    'add',
                    'check',
                    'edit'
                ]
            ]
        ];
    }
    /*编辑*/
    public function actionEdit(){
        Yii::$app->response->format = 'json';

        if(Yii::$app->request->isGet){
           $id = Yii::$app->request->get('id');
            $city = [];
            $isCity = false ;
           $rs = Couponcash::findOne($id);
           $rs->scope =$rs->scope?"市公司":"全国";
            if (Yii::$app->session->get('parent_admin_role_id') == '2') {
                $isCity = true;
                $city = [Yii::$app->session->get('role_area')];
            }else{
                $city = Admin::find()->select('admin.area,admin.city')->leftJoin('admin_role', 'admin.admin_role_id=admin_role.id')->distinct()->where('admin.status=1 and admin_role.parent_id=2 and admin.admin_role_id=10')->asArray()->all();

            }


           return ['rs'=>$rs,'isCity'=>$isCity,'city'=>$city];
        }

        if(Yii::$app->request->isPost){
             $time = time();
             $post =  Yii::$app->request->post();
             $model = Couponcash::findOne($post['coupon']['id']);
             $model->updated_at = $time;
             $model->scope = $post['coupon']['scope']=="市公司"?1:0;
             $model->scope_info = $post['coupon']['scope_info'];
             $model->title = $post['coupon']['title'];
             $model->expired = $post['coupon']['expired'];
             $model->discount_amount = $post['coupon']['discount_amount'];
            $db = Yii::$app->db;
            $transaction = $db->beginTransaction();
            try {
                $sumModel = Coupon::findOne($post['coupon']['coupon_id']);
                $sumModel->updated_at =$time;
                $sumModel->scope = $post['coupon']['scope']=="市公司"?1:0;
                $sumModel->scope_info = $post['coupon']['scope_info'];
                $sumModel->expired = $post['coupon']['expired'];
//                if($post['coupon']['discount_amount']>5){
//                    $sumModel->status =0;
//
//                }else{
//                    $sumModel->status =1;
//                }
                if(!$sumModel->save(false)){
                    throw new Exception("编辑失败".__LINE__);
                }
                if(!$model->save(false)){
                    throw new Exception("编辑失败".__LINE__);
                }
                $transaction->commit();
                return ['rs'=>'true'];
            }catch (Exception $e){
                $transaction->rollBack();
                return ['rs'=>'false','msg'=>$e->getMessage()];

            }

        }
    }
    //添加
    public function actionAdd(){
        Yii::$app->response->format = 'json';
        if(Yii::$app->request->isPost){
            $post = Yii::$app->request->post();
            if(!$post||!$post['coupon']['title']||!$post['coupon']['discount_amount']||$post['coupon']['discount_amount']<=0){
                return ['rs'=>'false','msg'=>'参数缺失'];
            }
            if(!$adminId =  Yii::$app->session->get('admin_id')){
                return ['rs'=>'false','msg'=>'参数缺失'];
            }
            $time =  time();
            $db = Yii::$app->db;
            $transaction = $db->beginTransaction();
            try {
                $model = new Coupon();
                $model->title = $post['coupon']['title'];
                $model->coupon_type = "cash";
                $model->admin_id = $adminId;
                $model->scope =  $post['coupon']['scope']=="市公司"?1:0;
                $model->scope_info =$post['coupon']['scope_info'];
                $model->expired = intval($post['coupon']['expired']);
//                if($post['coupon']['discount_amount']>=5){
//                    $model->status = 0;
//                }else{
                    $model->status = 1;
                    $model->auditor_id = $adminId;
                    $model->audited_at = $time;
              //  }
                $model->created_at = $time;
                $model->updated_at = $time;
                if(!$model->save(false)){
                    throw new Exception("添加失败".__LINE__);
                }
                $cashModel = new Couponcash();
                $cashModel->coupon_id = $model->id;
                $cashModel->title = $model->title;
                $cashModel->scope = $post['coupon']['scope']=="市公司"?1:0;
                $cashModel->scope_info =$post['coupon']['scope_info'];
                $cashModel->discount_amount = $post['coupon']['discount_amount'];
                $cashModel->admin_id = $adminId;
                $cashModel->created_at = $time;
                $cashModel->updated_at = $time;
                $cashModel->expired = intval($post['coupon']['expired']);
                if(!$cashModel->save(false)){
                    throw new Exception("添加失败".__LINE__);
                }
                $transaction->commit();
                return ['rs' => "true"];
            }catch (\Exception $e) {
                $transaction->rollBack();
                return ['rs' => "false", 'msg' => $e->getMessage()];
            }
        }

    }
    /*优惠券使用范围*/
    public function actionScope(){
        Yii::$app->response->format = 'json';
        $id = Yii::$app->request->get('id');
        $model = Couponcash::findOne($id);
        $city = Admin::find()->select('admin.area,admin.city')->leftJoin('admin_role', 'admin.admin_role_id=admin_role.id')->distinct()->where('admin.status=1 and admin_role.parent_id=2 and admin.admin_role_id=10 ')->asArray()->all();
        $rs = [];
        if($city){
            $scopeInfo = explode(",",$model->scope_info);
            foreach ($city as $k=>$v){
                foreach ($scopeInfo as $sk=>$sv){
                     if($sv==$v['area']){
                         $rs[] = $v;
                     }
                }
            }
        }


        return $rs;
    }
    /*获取分公司信息 */
    public function actionGetcompanys()
    {
        Yii::$app->response->format = 'json';
            $city = [];
            $isCity = false;
        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
              $isCity = true;
              $city = [Yii::$app->session->get('role_area')];
        }else{
              $city = Admin::find()->select('admin.area,admin.city')->leftJoin('admin_role', 'admin.admin_role_id=admin_role.id')->distinct()->where('admin.status=1 and admin_role.parent_id=2 and admin.admin_role_id=10')->asArray()->all();

        }
        return ['isCity'=>$isCity,'city'=>$city];
    }

/*活动下架*/
    public function actionDown()
    {
        Yii::$app->response->format = 'json';
        $id = Yii::$app->request->get('id');
        $status = Yii::$app->request->get('status');
        $reason = Yii::$app->request->get('reason');
        $status = intval($status);
        if(!in_array($status,[2,4])||!$reason){
            return ['rs'=>'false','msg'=>"数据缺失".__LINE__];
        }
        $db = Yii::$app->db;
        $adminId = Yii::$app->session->get('admin_id');
        if(Yii::$app->session->get('parent_admin_role_id') == '2'){
            $area = Yii::$app->session->get('role_area');
            $statusModel = Groupbuystatus::find()->where('groupon_id='.$id.' and area="'.$area.'"')->one();
        }else{
            $statusModel = Groupbuystatus::findOne($id);
        }
        if(!$statusModel||!$adminId){
            return ['rs'=>'false','msg'=>"数据缺失".__LINE__];
        }
        $tiaojian = $this->check($statusModel,$status);
        if(!$tiaojian['rs']){
            return ['rs'=>'false','msg'=>$tiaojian['msg']];
        }
        $time = time();
        $transcation = $db->beginTransaction();
        try{
           // $statusModel->c_status = $statusModel->status;
            $statusModel->status = $status;
            $statusModel->updated_at = $time;
            $statusModel->admin_id = $adminId;
            $statusModel->oper_time = $time;
            $statusModel->reason = $reason;
            if(!$statusModel->save(false)) {
                throw new Exception("操作失败".__LINE__);
            }
            $logModel = new Groupbuylog();
            $logModel->group_id = $statusModel->groupon_id;
            $logModel->admin_id = $adminId;
            $logModel->create_at = $time;
            $logModel->check_id = $statusModel->id;
            $note = "把该活动状态改为:".$statusModel->status;
            $logModel->note = $note;
            if(!$logModel->save(false)){
                throw new Exception("操作失败".__LINE__);
            }
            $transcation->commit();
            return ['rs'=>'true'];
        }catch (Exception $e){
            $msg = $e->getMessage();
            $transcation->rollBack();
            return ['rs'=>'false','msg'=>$msg];
        }

    }
//审核
public function actionCheck(){
    Yii::$app->response->format = 'json';
    $post = Yii::$app->request->post();
    $adminId = Yii::$app->session->get("admin_id");
    if(!$post||!$post['coupon_id']||!$post['val']||!$adminId){
        return ['rs'=>'false','msg'=>'参数缺失'];
    }
    $model = Coupon::findOne($post['coupon_id']);
    if($post['val']=="通过"){
       $model->status = 1;
    }else{
       $model->status = 2;
       $model->reject_reason = $post['reason'];
    }
    $time = time();
    $model->auditor_id = $adminId;
    $model->audited_at = $time;
    $model->updated_at = $time;
    if(!$model->save(false)){
        return ['rs'=>'false','msg'=>"审核失败".__LINE__];
    }
    return ['rs'=>'true'];



}


//列表
    public function actionLists()
    {
        Yii::$app->response->format = 'json';
        $where = [];
        $city = [];
        $isCity = false;
        $curPage = 1;
        $pageSize = Yii::$app->params['pagesize'];
        //$pageSize = 3;

        $rs = [];
        $offset = $pageSize * ($curPage - 1);
        $query = new \yii\db\Query();
        $query = $query->select("a.*,b.username,c.status,c.reject_reason")
                 ->from("coupon_cash as a")
                 ->leftJoin("admin as b",'a.admin_id=b.id')
                 ->leftJoin("coupon as c",'a.coupon_id=c.id');

        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            $area = Yii::$app->session->get('role_area');
            $where[] = '((a.scope_info like "%' .$area. '%") or a.scope=0 )';
            $isCity = true;
         }  else {
                $city = Admin::find()->select('admin.area,admin.city')->leftJoin('admin_role', 'admin.admin_role_id=admin_role.id')->distinct()->where('admin.status=1 and admin_role.parent_id=2 and admin.admin_role_id=10')->asArray()->all();
                array_unshift($city, ['city' => '全国', 'area' => "全国"]);
            }
        if (Yii::$app->request->isPost) {
            //搜索
             $post = Yii::$app->request->post();
             if(trim($post['title'])){
                 $where[] = 'a.title like "%'.trim($post['title']).'%"';
             }
            if(trim($post['discount_amount'])){
                $where[] = 'a.discount_amount= '.trim($post['discount_amount']).'';
            }
            if(trim($post['city'])){
                 if($post['city']!='全国'){
                     $where[] ='a.scope_info like "%' .$post['city']. '%"';
                 }
            }
            if(trim($post['page'])){
                $curPage = trim($post['page']);
            }
        }
        $query = $where ?$query->where(implode(' and ', $where)) : $query;
        $offset = $pageSize*($curPage-1);
        $totalPage = $query->count();
        $rs = $query->limit($pageSize)->offset($offset)->orderby('a.id desc')->all();
        if ($rs) {
            foreach ($rs as $k => $v) {
                $rs[$k]['created_at'] = date("Y-m-d H:i:s", $v['created_at']);
               if($v['status']==1){
                   $rs[$k]['status']="已启用";
               }elseif ($v['status']==2){
                   $rs[$k]['status']="已禁用";
               }else{
                   $rs[$k]['status']="待审核";
               }

               if($v['scope']){
                   $rs[$k]['scope'] ="市公司";

               }else{
                   $rs[$k]['scope'] ="全国";
               }

            }

        }

        $enableAdd= false;
        $enableEdit= false;
        $enableCheck= false;

        if (Yii::$app->session->get('username') != 'admin') {
            $aulist = explode('|', Yii::$app->session->get('authlist'));
            if (!in_array('couponcashadd', $aulist)) {
                $enableAdd = true;
            }
            if (!in_array('couponcashedit', $aulist)) {
                $enableEdit = true;
            }
            if (!in_array('couponcashcheck', $aulist)) {
                $enableCheck = true;
            }
        }
        return ['isCity' => $isCity, 'currPage' => $curPage, 'pageSize' => $pageSize, 'totalPage' => $totalPage, 'rs' => $rs, 'city' => $city,'enableAdd'=>$enableAdd,'enableEdit'=>$enableEdit,'enableCheck'=>$enableCheck];


    }


    //search
    private function search($search, $where)
    {
        foreach ($search as $k => $v) {
            if ($k == 'curpage') {
                continue;
            }
//            if ($k == 'curstatus') {
//                    $where[] = ' supplier.supplier_type=' . $v;
//                    continue;
//            }
            if (trim($search[$k])) {
                if ($k == 'description') {
                    $where[] = 'promotion_groupon.description like "%' . trim($v) . '%"';
                }
                if ($k == 'sku_title') {
                    $where[] = 'promotion_groupon.sku_title like "%' . trim($v) . '%"';
                }
                if ($k == 'supplier_name') {
                    $where[] = ' supplier.supplier_name like "%' . trim($v) . '%"';
                }
//                if ($k == 'city') {
//                    if ($v == '全国' || !$v) {
//                        continue;
//                    }
//                    $citys = explode(',', $v);
//                    $where[] = ' supplier.city ="' . $citys[1] . '"';
//                }

            }
        }
        return $where;
    }

}
