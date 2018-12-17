<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/13
 * Time: 15:23
 */

namespace app\controllers;

use app\common\behavior\NoCsrs;
use app\models\Admin;
use app\models\Supplier;
use app\models\Supplierapply;
use yii\web\Controller;
use Yii;

class SupplierController extends Controller
{

    public function behaviors()
    {
        return [
            'csrf' => [
                'class' => NoCsrs::className(),
                'controller' => $this,
                'actions' => [
                    'lists',
                    "add",
                    "edit",
                    "repasswd",
                    "applylist"
                ]
            ]
        ];
    }

    /*查看销售区域*/
    public function actionSalerange(){
        Yii::$app->response->format = 'json';
        $id = Yii::$app->request->get('id');
        if(!$id){
            return [];
        }
        $model = Supplierapply::findOne($id);
        if($area =$model->area){
             $area = explode(',',$area);
              $area = implode('","',$area);
             $rs = Admin::find()->select('company_name')->where('area in ("'.$area.'")')->distinct()->asArray()->all();
            if($rs){

                return implode('|',array_column($rs,'company_name'));
            }
            return $rs;
        } else{
            return [];
        }

    }

    /*供应商申请审核*/
    public function actionApplycheck()
    {
        Yii::$app->response->format = 'json';
        $id = Yii::$app->request->get('id');
        $status = Yii::$app->request->get('status');
        if(!$id||!$status){
            return ['rs'=>'false','msg'=>'参数缺失'.__LINE__];
        }
        $model = Supplierapply::findOne($id);
        $adminId = Yii::$app->session->get('admin_id');
        if(!$model||!$adminId){
            return ['rs'=>'false','msg'=>'非法数据'.__LINE__];
        }

        $model->status= $status;
        $model->admin_id = $adminId;
        if(!$model->save(false)){
            return ['rs'=>'false','msg'=>'审核失败'];
        }

        return ['rs'=>'true'];


    }

    /*供应商申请列表*/
    public function actionApplylist()
    {
        Yii::$app->response->format = 'json';
        $where = [];
        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            $where[] = '(area like "%' . Yii::$app->session->get('role_area') . '%" or area="")';
        }
        $query = Supplierapply::find();
        $query = $where ? $query = $query->where(implode(' and ', $where)) : $query;
        $rs = $query->asArray()->all();
        $enableCheck = false;
        if (Yii::$app->session->get('username') != 'admin') {
            $aulist = explode('|', Yii::$app->session->get('authlist'));

            if (!in_array('supplierapplycheck', $aulist)) {
                $enableCheck = true;
            }
        }

        return ['enableCheck' => $enableCheck, 'rs' => $rs,];


    }


    public function actionEdit()
    {
        Yii::$app->response->format = 'json';
        if (Yii::$app->request->isGet) {
            $id = Yii::$app->request->get('id');
            $citys = [];
            $rs = Supplier::findOne($id);
            $isCity = (Yii::$app->session->get('parent_admin_role_id') == 2) ? true : false;
            if (!$isCity) {
                $citys = Admin::find()->where('status=1 and company_name!="总部"')->select('area,company_name')->distinct()->asArray()->all();
            }
            $rs->settllment_type = $rs->settllment_type == 1 ? "月结" : "现结";
            $rs->supplier_type = $rs->supplier_type == 1 ? "自营" : "他营";
            return ['rs' => $rs, 'isCity' => $isCity, 'citys' => $citys];
        }
        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();
            $model = Supplier::findOne($post['supplier']['id']);
            $model->load($post, 'supplier', false);
            $model->settllment_type = $model->settllment_type == "月结" ? 1 : 2;
            $model->supplier_type = $model->supplier_type == "自营" ? 1 : 0;
            $model->updated_at = time();
            if (!$model->save(false)) {
                return ['rs' => 'false', 'msg' => '更新失败'];
            }
            return ['rs' => 'true'];

        }

    }

    public function actionRepasswd()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        if (!$post || !$post['id'] || !$post['repasswd'] || !$post['password']) {
            return ['rs' => 'false', 'msg' => '参数缺失，无法修改' . __LINE__];
        }
        if ($post['repasswd'] != $post['password']) {
            return ['rs' => 'false', 'msg' => '两次输入的密码不一致' . __LINE__];
        }
        $model = Supplier::findOne($post['id']);
        $model->password = hash('sha256', $post['password'] . Yii::$app->params['passwordkey'], false);
        $model->updated_at = time();
        if (!$model->save(false)) {
            return ['rs' => 'false', 'msg' => '修改密码失败' . __LINE__];
        }
        return ['rs' => 'true'];
    }

    public function actionAdd()
    {
        Yii::$app->response->format = 'json';
        if (Yii::$app->request->isGet) {
            $isCity = (Yii::$app->session->get('parent_admin_role_id') == 2) ? true : false;
            $area = Yii::$app->session->get('role_area');
            $citys = [];
            if ($isCity) {
                $citys[] = Admin::find()->where('area="' . $area . '"')->select('area,company_name')->asArray()->one();
            } else {
                $citys = Admin::find()->where('status=1 and company_name!="总部"')->select('area,company_name')->distinct()->asArray()->all();
            }
            return ['isCity' => $isCity, 'citys' => $citys];
        }
        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();
            $supplier = new Supplier();
            $supplier->load($post, 'supplier', false);
            if (!$supplier->mobile || !$supplier->county || !$supplier->area) {
                return ['rs' => 'false', 'msg' => '参数缺失，无法添加' . __LINE__];
            }
            //建议登录名是否存在；检验手机号是否被别的供应商使用
            if (Supplier::find()->where('username="' . $supplier->username . '"')->one()) {
                return ['rs' => 'false', 'msg' => '指定的登录名已经存在'];
            }
            if (Supplier::find()->where('mobile="' . $supplier->mobile . '"')->one()) {
                return ['rs' => 'false', 'msg' => '指定的手机号已经存在'];
            }
            $supplier->admin_id = Yii::$app->session->get('admin_id');
            if (!$supplier->admin_id) {
                return ['rs' => 'false', 'msg' => '参数缺失，无法添加' . __LINE__];
            }
            $supplier->status = 1;
            $time = time();
            $supplier->created_at = $time;
            $supplier->updated_at = $time;
            $supplier->password = hash('sha256', $supplier->password . Yii::$app->params['passwordkey'], false);
            //   return $supplier;
            if (!$supplier->save(false)) {
                return ['rs' => 'false', 'msg' => '插入失败，请联系管理员'];
            }
            return ['rs' => 'true'];

        }


    }

    public function actionLists()
    {
        Yii::$app->response->format = 'json';
        $where = [];
        $isCity = false;

        $curPage = 1;
        $pageSize = Yii::$app->params['pagesize'];
        // $pageSize = 1;
        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            $where[] = 'supplier.area="' . Yii::$app->session->get('role_area') . '"';
            $city = [];
            $isCity = true;
        } else {
            $city = Admin::find()->select('area, company_name as label')->leftJoin('admin_role', 'admin.admin_role_id=admin_role.id')->distinct()->where(' admin_role.parent_id=2 ')->asArray()->all();
            array_unshift($city, ['label' => '全国', 'value' => 0]);
        }
        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();
            $curPage = $post['curPage'];
            $where = $this->search($post, $where);
        }
        $offset = $pageSize * ($curPage - 1);
        $query = Supplier::find()->leftJoin("admin", 'supplier.area=admin.area')->select("supplier.*,admin.username as admin_name ,admin.city as bcity");

        $query = $where ? $query = $query->where(implode(' and ', $where)) : $query;
        $totalPage = $query->count();
        $rs = $query->asArray()->limit($pageSize)->offset($offset)->all();
        if ($rs) {
            foreach ($rs as $k => $v) {
                $rs[$k]['created_at'] = date("Y-m-d H:i:s", $v['created_at']);
                $rs[$k]['updated_at'] = date("Y-m-d H:i:s", $v['updated_at']);
                $rs[$k]['supplier_type'] = $v['supplier_type'] ? "自营" : "他营";
                $rs[$k]['settllment_type'] = $v['settllment_type'] == 1 ? "月结" : "现结";
                $rs[$k]['status'] = $v['status'] == 1 ? "通过" : ($v['status'] == 2 ? "禁用" : "未审核");
            }
        }
        $enableAdd = false;
        $enableEdit = false;
        $enableRepass = false;

        if (Yii::$app->session->get('username') != 'admin') {
            $aulist = explode('|', Yii::$app->session->get('authlist'));

            if (!in_array('supplieradd', $aulist)) {
                $enableAdd = true;
            }
            if (!in_array('supplierrepass', $aulist)) {
                $enableRepass = true;
            }
            if (!in_array('supplieredit', $aulist)) {
                $enableEdit = true;
            }

        }
        $supplier_type = [['label' => '全部', 'value' => '3'], ['label' => '自营', 'value' => '1'], ['label' => '他营', 'value' => '0']];
        return ['isCity' => $isCity, 'enableAdd' => $enableAdd, 'enableRepass' => $enableRepass, 'enableEdit' => $enableEdit, 'curPage' => $curPage, $pageSize => $pageSize, 'totalPage' => $totalPage, 'rs' => $rs, 'city' => $city, 'supplier_type' => $supplier_type];

    }

    private function search($search, $where)
    {
        foreach ($search as $k => $v) {
            if ($k == 'curpage') {
                continue;
            }
            if ($k == 'supplier_type') {
                if ($v == 3) {
                    continue;
                } elseif ($v === '0') {
                    $where[] = ' supplier.supplier_type=0';
                } elseif ($v == 1) {
                    $where[] = ' supplier.supplier_type=1';
                }


                continue;

            }
            if (trim($search[$k])) {
                if ($k == 'username') {
                    $where[] = ' supplier.username like "%' . trim($v) . '%"';
                }
                if ($k == 'supplier_name') {
                    $where[] = ' supplier.supplier_name like "%' . trim($v) . '%"';
                }
                if ($k == 'mobile') {
                    $where[] = ' supplier.mobile like "%' . trim($v) . '%"';
                }
                if ($k == 'city') {
                    if ($v == '全国' || !$v) {
                        continue;
                    }

                    $where[] = ' supplier.area ="' . $v . '"';
                }

            }
        }
        return $where;
    }

}
