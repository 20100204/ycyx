<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/31
 * Time: 14:35
 */

namespace app\controllers;

use app\common\behavior\NoCsrs;
use app\models\Admin;
use app\models\Shop;
use app\models\Shopapply;
use app\models\Shopapplychecklog;
use app\models\Shopservices;
use app\models\Shopusers;
use app\models\User;
use app\models\Useraddr;
use app\models\Ycypuser;
use yii\db\Exception;
use yii\web\Controller;
use Yii;

class ShoperController extends Controller
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
                    'editsave',
                    'addshoper',
                    'addserver',
                    'delserver',
                    'agentapply',
                    'agentcheck'
                ]
            ]
        ];
    }

    /*代理审核*/
    public function actionAgentcheck(){
        Yii::$app->response->format = 'json';
        $ids = Yii::$app->request->post('ids');
        $type= Yii::$app->request->post('type');
        if(!$ids||!$type){
            return ['rs'=>'false','msg'=>'参数缺失，审核失败'];
        }

        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();
        try {
             if(!Shopapply::updateAll(['status'=>$type,'updated_at'=>time()],['id'=>$ids])){
                 throw new Exception("审核失败".__LINE__);
             }
             foreach ($ids as $k=>$v){
                 $shopapplychecklogModel = new Shopapplychecklog();
                 $shopapplychecklogModel->admin_id = Yii::$app->session->get('admin_id');
                 $shopapplychecklogModel->apply_id = $v;
                 $shopapplychecklogModel->created_at = time();
                 $shopapplychecklogModel->status = $type;
                 if(!$shopapplychecklogModel->save(false)){
                     throw new Exception("审核失败".__LINE__);
                 }

             }
             $transaction->commit();
            return ['rs'=>'true'];
        }catch (Exception $e){
            return ['rs'=>'false','msg'=>$e->getMessage()];
        }

    }

    /*申请代理*/
    public function actionAgentapply(){
        Yii::$app->response->format = 'json';
        $curPage = 1;
        $where []="a.created_at>=1539705600" ;
        $search = Yii::$app->request->post();
        if ($search) {
            foreach ($search as $k => $v) {
                if ($k == 'curpage') {
                    $curPage = $v;
                    continue;
                }

                if (trim($v)) {
                    if($k=='city'){
                        $where[] = 'a.area="' .   $v . '"';
                        continue;
                    }
                    $where[] = ' a.'.$k . ' like "%' . trim($v) . '%"';
                }
            }

        }

        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            //如果是市公司用户who_uploader in()市公司只能看到本市的;
             $where[] = 'a.area="'.Yii::$app->session->get('role_area').'"';
            $citys = Admin::find()->select('admin.city,area') ->where('area="'.Yii::$app->session->get('role_area').'"')->distinct()->asArray()->all();
        }else{
            $citys = Admin::find()->select('admin.area,admin.city')->leftJoin('admin_role', 'admin.admin_role_id=admin_role.id')->distinct()->where('admin.status=1 and admin_role.parent_id=2 ')->asArray()->all();
            array_unshift($citys, ['city' => '全国', 'area' => '']);
        }
        if ($where) {
            $where = implode(' and ', $where);
        }
        $pageSize = Yii::$app->params['pagesize'];
        $offset = $pageSize * ($curPage - 1);
        $query = new \yii\db\Query();
        $query->select('a.*,b.city as city')
              ->from('shop_apply as a')
              ->leftJoin('admin as b','a.area=b.area');
        if ($where) {
            $query = $query->where($where);
        }
        $rs = $query->offset($offset)->limit($pageSize)->distinct()->orderBy('a.id desc')->all();
        if($rs){
            foreach ($rs as $k=>$v){
                if($v['pics']){
                    $rs[$k]['pics']= explode('|',$v['pics']);
                }else{
                    $rs[$k]['pics'] = [];
                }
                @$rs[$k]['created_at'] = date("Y-m-d H:i:s",$v['created_at']);
                if($v['status']==1||$v['status']==2){
                    @$rs[$k]['_disabled'] = true;
                    @$rs[$k]['_checked'] = false;

                }

            }

        }


        $count = $query->count();
        $enableCheck = false;//审核权限
        //  return Yii::$app->session->get('username');
        if (Yii::$app->session->get('username') != 'admin') {
            $aulist = explode('|', Yii::$app->session->get('authlist'));
            if (!in_array('shopagentcheck', $aulist)) {
                $enableCheck = true;
            }
        }
        return ['rs' => $rs, 'totalPage' => $count, 'pageSize' => $pageSize, 'currpage' => $curPage, 'auth' => ['enableCheck' => $enableCheck,],'citys'=>$citys];


    }


    /* 获得该店铺所有的社区服务*/
    public function actionGetserver()
    {
        Yii::$app->response->format = 'json';
        $shop_id = Yii::$app->request->get('shop_id');
        $service = [];
        $checked = [];
        $service = Shopservices::find()->where('shop_id=' . $shop_id)->asArray()->all();
        if ($service) {
            $checked = array_column($service, 'service_name');
        }
        return ['list' => $service, 'checked' => $checked];

    }

    /*删除社区服务*/
    public function actionDelserver()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        $seviceName = $post['all'];
        $shopId = $post['shop_id'];
       /* if(!$seviceName){
            return ['rs'=>'false','msg'=>'信息不全，无法删除'.__LINE__];
        }*/
        if(!$shopId){
            return ['rs'=>'false','msg'=>'信息不全，无法删除'.__LINE__];
        }

        if($seviceName){
            if(Shopservices::deleteAll(['and','shop_id='.$shopId,['not in','service_name',$seviceName]])){
                return ['rs'=>'true'];
            }else{
                return ['rs'=>'false','msg'=>'删除失败'.__LINE__];
            }
        }else{

            if(Shopservices::deleteAll(['shop_id'=>$shopId])){
                return ['rs'=>'true'];
            }else{
                return ['rs'=>'false','msg'=>'删除失败'.__LINE__];
            }
        }


    }

    //添加社区服务
    public function actionAddserver()
    {
        Yii::$app->response->format = 'json';
          $post = Yii::$app->request->post();
        if (!$post['service_name']) {
            return ['rs' => 'false', 'msg' => '服务名称不全，无法添加'];
        }
        if (!$post['shop_id']) {
            return ['rs' => 'false', 'msg' => '社区信息不全，无法添加'];
        }
        if (!$post['name']) {
            return ['rs' => 'false', 'msg' => '联系人信息不全，无法添加'];
        }
        if (!$post['phone']) {
            return ['rs' => 'false', 'msg' => '联系电话信息不全，无法添加'];
        }
      /*  if (!$post['address']) {
            return ['rs' => 'false', 'msg' => '服务地址信息不全，无法添加'];
        }*/

        if (Shopservices::find()->where('shop_id=' . $post['shop_id'] . ' and service_name="' . $post['service_name'] . '"')->one()) {
            return ['rs' => 'false', 'msg' => '相同服务信息该社区已经添加，无法添加'];
        }
        $shopserviceModel = new Shopservices();
        $shopserviceModel->shop_id = $post['shop_id'];
        $shopserviceModel->name = $post['name'];
        $shopserviceModel->service_name = $post['service_name'];
        $shopserviceModel->phone = $post['phone'];
        $shopserviceModel->address = isset($post['address'])?$post['address']:'';
        $shopserviceModel->address = isset($post['description'])?$post['description']:'';
        if ($shopserviceModel->save(false)) {
            return ['rs' => 'true'];
        }
        return ['rs' => 'false', 'msg' => '添加失败'];

    }


    //根据userid删除多账户
    public function actionDelusers()
    {
        Yii::$app->response->format = 'json';
        $userid = Yii::$app->request->get('user_id');
        if (!$userid) {
            return ['rs' => 'false'];
        }
        $user = Shopusers::find()->where('user_id=' . $userid)->one();
        if ($user->delete()) {
            return ['rs' => 'true'];
        }
        return ['rs' => 'false'];
    }

    /* 根据shopid获得多个店铺账号*/
    public function actionGetmulitusers()
    {
        Yii::$app->response->format = 'json';
        $shopId = Yii::$app->request->get('shop_id');
        if (!$shopId) {
            return [];
        }
        $query = new \yii\db\Query();
        return $query->select('a.user_id,b.nickname,b.headicon,c.mobile')
            ->from('shop_users as a')
            ->leftJoin('user_rel_wechat_app as b', 'a.user_id=b.user_id')
            ->leftJoin('user as c', 'a.user_id=c.id')
            ->where('a.shop_id=' . $shopId)
            ->all();

    }

    /*添加多账号*/
    public function actionAddshoper()
    {
        Yii::$app->response->format = 'json';
        //添加时手机号不能是店东已经存在，如果是客户则修改客户类型是店东
        $post = Yii::$app->request->post();
        if (!$post['shop_id']) {
            return ['rs' => 'false', 'msg' => '数据缺失，无法添加'];
        }
        if (!$post['mobile']) {
            return ['rs' => 'false', 'msg' => '数据缺失，无法添加'];
        }
        if (Shop::find()->where('mobile=' . $post['mobile'])->one()) {
            return ['rs' => 'false', 'msg' => '该手机号已经是店东'];
        }
        if (!$userInfo = Ycypuser::find()->where('mobile=' . $post['mobile'])->one()) {
            return ['rs' => 'false', 'msg' => '该手机号还不是平台的用户'];
        }

        if (Shopusers::find()->where('user_id=' . $userInfo->id)->one()) {
            return ['rs' => 'false', 'msg' => '该手机号已经绑定到社区了'];
        }

        $shopUsersModel = new Shopusers();
        $shopUsersModel->shop_id = $post['shop_id'];
        $shopUsersModel->user_id = $userInfo->id;
        $shopUsersModel->created_at = time();
        $shopUsersModel->updated_at = time();
        if (!$shopUsersModel->save(false)) {
            return ['rs' => 'false', 'msg' => '绑定失败'];
        }
        $query = new \yii\db\Query();
        $datas = $query->select('a.user_id,b.nickname,b.headicon,c.mobile')
            ->from('shop_users as a')
            ->leftJoin('user_rel_wechat_app as b', 'a.user_id=b.user_id')
            ->leftJoin('user as c', 'a.user_id=c.id')
            ->where('a.user_id=' . $userInfo->id)
            ->one();
        return ['rs' => 'true', 'datas' => $datas];
    }

    /*修改密码*/

    public function actionRepasswd()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        $adminModel = Ycypuser::findOne($post['id']);
        $adminModel->password = hash('sha256', $post['passwd'] . Yii::$app->params['passwordkey'], false);
        $adminModel->updated_at = time();
        if (!$adminModel->save(false)) {
            return ['rs' => 'false'];
        }
        return ['rs' => 'true'];
    }

    public function actionList()
    {
        Yii::$app->response->format = 'json';
        $curPage = 1;
        $where = [];
        $search = Yii::$app->request->post();
        if ($search) {
            foreach ($search as $k => $v) {
                if ($k == 'curpage') {
                    $curPage = $v;
                    continue;
                }

                if (trim($v)) {

                    if($k=='city'){
                        if($v=='全国'){
                            continue;
                        }
                        $val = explode(',',$v)[1];
                        $where[] = $k .'="' .   $val . '"';
                        continue;
                    }

                    $where[] = $k . ' like "%' . trim($v) . '%"';
                }
            }

        }
        //  return $where;
        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            //如果是市公司用户who_uploader in()市公司只能看到本市的;
            $who_uploaderIds = Admin::find()->where('area="' . Yii::$app->session->get('role_area') . '"')->asArray()->select('id')->all();
            $who_uploaderIds = array_column($who_uploaderIds, 'id');
            $where[] = 'admin_id in (' . implode(',', $who_uploaderIds) . ')';
            $citys = Admin::find()->select('admin.company_name,area') ->where('area="'.Yii::$app->session->get('role_area').'"')->distinct()->asArray()->all();
        }else{
            $citys = Admin::find()->select('admin.area,admin.company_name')->leftJoin('admin_role', 'admin.admin_role_id=admin_role.id')->distinct()->where('admin.status=1 and admin_role.parent_id=2 ')->asArray()->all();
            array_unshift($citys, ['company_name' => '全国', 'value' => '']);
        }
        if ($where) {
            $where = implode(' and ', $where);
        }
        $pageSize = Yii::$app->params['pagesize'];
        $offset = $pageSize * ($curPage - 1);
        $query = Shop::find()->asArray();
        if ($where) {
            $query = $query->where($where);
        }
        $rs = $query->offset($offset)->limit($pageSize)->orderBy('updated_at desc ,id desc')->all();
        $count = $query->count();

        /*$query = new \yii\db\Query();
        $rs = $query->select('shop.user_id,user.username,shop.realname,shop.shop_name,shop.updated_at,shop.status,shop.profit_rate,shop.mobile,shop.address asaddrs ')
            ->from('shop')
            ->leftJoin('user', 'shop.user_id=user.id')
            ->all();*/

        if ($rs) {
            foreach ($rs as $k => $v) {
                if ($v['shop_type'] == 1) {
                    $rs[$k]['shop_type'] = "代理";
                } else {
                    $rs[$k]['shop_type'] = "店铺";
                }
                $rs[$k]['updated_at'] = date("Y-m-d H:i:s", $v['updated_at']);
                $rs[$k]['address'] = $v['province'] . $v['city'] . $v['county'] . $v['address'];
            }
        }
        $enableAdd = false;
        $enableEdit = false;
        $enablePasswd = false;
        $enableShe = false;
        $enableBang = false;
        if (Yii::$app->session->get('username') != 'admin') {
            $aulist = explode('|', Yii::$app->session->get('authlist'));

            if (!in_array('addshopers', $aulist)) {
                $enableAdd = true;
            }
            if (!in_array('editshopers', $aulist)) {
                $enableEdit = true;
            }
            if (!in_array('enablePasswd', $aulist)) {
                $enablePasswd = true;
            }
            if (!in_array('multishopers', $aulist)) {
                $enableBang = true;
            }
            if (!in_array('shequshop', $aulist)) {
                $enableShe = true;
            }

        }

        return ['rs' => $rs, 'totalPage' => $count, 'pageSize' => $pageSize, 'search' => @$search, 'currpage' => $curPage, 'auth' => ['enableAdd' => $enableAdd, 'enableEdit' => $enableEdit, 'enablePasswd' => $enablePasswd, 'enableShe' => $enableShe, 'enableBang' => $enableBang],'citys'=>$citys];

    }

    public function actionAdd()
    {

        Yii::$app->response->format = 'json';
        $roleId = Yii::$app->session->get('parent_admin_role_id');

        if (Yii::$app->request->isGet) {

            // $citys = Admin::find()->select('id,company_name')->where('status=1')->all();

            $citys = Admin::find()->select('admin.id,admin.company_name')->leftJoin('admin_role', 'admin.admin_role_id=admin_role.id')->groupBy('admin.company_name')->where('admin.status=1 and admin_role.parent_id=2 ')->asArray()->all();


            if ($roleId == 2) {
                $adminId = Yii::$app->session->get('admin_id');
                $companyName = Admin::find()->where('id=' . $adminId)->asArray()->one();
                $companyName = explode(',', $companyName['company_name']);
                $disabledchange = [1, 2];
            }
            return ['cityList' => $citys, 'admin_id' => @$adminId, 'roleId' => $roleId, 'companyName' => @$companyName];
        }
        if (Yii::$app->request->isPost) {
           $post = Yii::$app->request->post();
            /*  if (Ycypuser::findOne(['username' => $post['user']['username']])) {
                  return ['rs' => 'false', 'msg' => '添加店东的账号已经存在!'];
              }*/
            if (Shop::find()->where('mobile="' . $post['user']['mobile'] . '"')->One()) {
                return ['rs' => 'false', 'msg' => '添加店东的手机号已经存在!'];
            }

            $city = array_column($post['user']['city'], 'name');
            $areacode = array_column($post['user']['city'], 'code');

            if (!$city[2] || !$areacode[2]) {
                return ['rs' => 'false', 'msg' => '添加店东的所在的区域码错误'];
            }
            unset($post['user']['city'], $city[3], $city[4], $areacode[3], $areacode[4]);

            $addr = implode('', $city) . $post['user']['addrdetail'];
            $key = 'AM2BZ-F33WK-WE3J4-ANFGB-HLGNS-Y6BWP';
            $location = file_get_contents('https://apis.map.qq.com/ws/geocoder/v1/?address=' . $addr . '&key=' . $key);
            $location = json_decode($location, true);
            if (!$location['status']) {
                $db = Yii::$app->db;
                $transaction = $db->beginTransaction();
                try {
                    $userModel = Ycypuser::find()->where('mobile="' . $post['user']['mobile'] . '"')->One();
                    $time = time();
                    $shopModel = new Shop();
                  //  $useraddrModel = new Useraddr();
                    if (!$userModel) {
                        $userModel = new Ycypuser();
                        $userModel->load($post, 'user', false);
                        $userModel->created_at = $time;
                    }
                    $userModel->password = hash('sha256', $userModel->password . Yii::$app->params['passwordkey'], false);
                    $userModel->type = 'shop';
                    $userModel->updated_at = $time;
                    if ($roleId == 2) {
                        @$adminAreacode = Yii::$app->session->get('role_area');
                        if ($areacode[0] . ':' . $areacode[1] != $adminAreacode) {
                            throw new Exception("所在城市填写错误，店东位置不在市公司范围内，,请选择市公司的所在市");
                        }

                    } else {
                        //如果所选的所在城市和地址不一样则抛出异常
                        $temparea = $areacode[0] . ':' . $areacode[1];
                        if (Admin::findOne($post['user']['admin_id'])->area != $temparea) {
                            throw new Exception("所在城市和所属分公司不匹配");
                        }

                    }
                    $userModel->area = implode(':', $areacode);
                    if (!$userModel->save(false)) {
                        throw new Exception('添加user表失败!' . __LINE__);
                    }

                  /*  $useraddrModel->user_id = $userModel->id;
                    $useraddrModel->realname = $userModel->realname;
                    $useraddrModel->province = $city[0];
                    $useraddrModel->city = $city[1];
                    $useraddrModel->county = $city[2];
                    $useraddrModel->longitude = $location['result']['location']['lng'];
                    $useraddrModel->latitude = $location['result']['location']['lat'];
                    $useraddrModel->created_at = $time;
                    $useraddrModel->updated_at = $time;
                    $useraddrModel->addrdetail = $post['user']['addrdetail'];
                    $useraddrModel->phone = $post['user']['mobile'];*/
                    $shopModel->user_id = $userModel->id;
                    $shopModel->shop_name = $post['user']['shop_name'];
                    $shopModel->mobile = $post['user']['mobile'];
                    $shopModel->realname = $post['user']['realname'];
                    $shopModel->opening_time = $post['user']['opening_time'];
                    $shopModel->closing_time = $post['user']['closing_time'];
                    $shopModel->admin_id = $post['user']['admin_id'];
                    $shopModel->reg_ip = Yii::$app->request->userIP;
                    $shopModel->logo = $post['user']['logo'];
                    $shopModel->profit_rate = $post['user']['profit_rate'];
                    $shopModel->created_at = $time;
                    $shopModel->updated_at = $time;
                    $shopModel->province = $city[0];
                    $shopModel->city = $city[1];
                    $shopModel->county = $city[2];
                    $shopcode = Shop::find()->where('id>=1')->orderBy('id desc')->one();
                    if ($shopcode) {
                        $shopcode = ($shopcode->id + 1) % 100000;
                        $shopcode = str_pad($shopcode, 6, '0', STR_PAD_LEFT);
                    } else {
                        $shopcode = 1 % 100000;
                        $shopcode = str_pad($shopcode, 6, '0', STR_PAD_LEFT);
                    }
                    if($post['user']['shop_type']=='店铺'){
                        $shopModel->shop_type=0;
                    }else{
                        $shopModel->shop_type=1;
                    }
                    if($post['user']['home_delivery']=='提供'){
                        $shopModel->home_delivery=1;
                    }else{
                        $shopModel->home_delivery=0;
                    }
                    $shopModel->shop_code = $shopcode;
                    $shopModel->longitude = $location['result']['location']['lng'];
                    $shopModel->latitude = $location['result']['location']['lat'];
                    $shopModel->area = $userModel->area;
                    $shopModel->address = $post['user']['addrdetail'];
                    if ($shopModel->save(false)) {
                        $transaction->commit();
                        return ['rs' => 'true'];
                    } else {
                        throw new Exception("添加店东失败" . __LINE__);
                    }

                } catch (Exception $e) {
                    $transaction->rollBack();
                    return ['rs' => "false", 'msg' => $e->getMessage() . __LINE__];
                }

            } else {
                return ['rs' => "false", 'msg' => '获取地理位置经纬度信息失败，稍后再试!'];
            }
        }
    }

    public function actionEdit()
    {
        Yii::$app->response->format = 'json';

        $shopId = Yii::$app->request->get('id');
        $rs = Shop::find()->asArray()->where('id=' . $shopId)->one();
        if(!$rs['shop_type']){
            $rs['shop_type']="店铺";
        }else{
            $rs['shop_type']="代理";
        }
        if($rs['home_delivery']=='1'){
            $rs['home_delivery']="提供";
        }else{
            $rs['home_delivery']="不提供";
        }
        /*$query = new \yii\db\Query();
        $rs = $query->select('shop.user_id,user.email,user.password,shop.logo,user.username,user.realname,shop.shop_name,shop.admin_id,shop.profit_rate,user.mobile,user_addr.province,user_addr.city,user_addr.county,user_addr.addrdetail,shop.opening_time,shop.closing_time')
            ->from('shop')
            ->leftJoin('user', 'shop.user_id=user.id')
            ->leftJoin('user_addr', 'shop.user_id=user_addr.user_id')
            ->where('shop.user_id=' . $userId)
            ->one();*/
        $rs['city'] = [$rs['province'], $rs['city'], $rs['county']];
        $roleId = Yii::$app->session->get('parent_admin_role_id');
        if ($roleId == 2) {
            $adminId = Yii::$app->session->get('admin_id');
            $companyName = Admin::find()->where('id=' . $adminId)->asArray()->one();
            $companyName = explode(',', $companyName['company_name']);
            $disabledchange = [1, 2];
        }
        $fs = Admin::find()->select('admin.id,admin.company_name')->leftJoin('admin_role', 'admin.admin_role_id=admin_role.id')->where('admin.status=1 and admin_role.parent_id=2 ')->asArray()->all();

        return ['rs' => $rs, 'fenggongsi' => $fs, 'companyName' => @$companyName, 'roleId' => $roleId];

    }

    public function actionEditsave()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        if (Ycypuser::find()->where(' id!=' . $post['user']['user_id'] . ' and mobile=' . $post['user']['mobile'])->one()) {
            return ['rs' => "false", 'msg' => '手机号已被使用' . __LINE__];
        }

        $city = array_column($post['user']['city'], 'name');
        $city = array_slice($city, 0, 3);
        $areacode = array_column($post['user']['city'], 'code');
        $areacode = array_slice($areacode, 0, 3);
        if (!$city[2] || !$areacode[2]) {
            return ['rs' => 'false', 'msg' => '添加店东的所在的区域码错误'];
        }
        unset($post['user']['city'], $city[3], $city[4], $areacode[3], $areacode[4]);

        $addr = implode('', $city) . $post['user']['address'];
        $key = 'AM2BZ-F33WK-WE3J4-ANFGB-HLGNS-Y6BWP';
        $location = file_get_contents('https://apis.map.qq.com/ws/geocoder/v1/?address=' . $addr . '&key=' . $key);
        $location = json_decode($location, true);
        if (!$location['status']) {
            $userModel = Ycypuser::findOne($post['user']['user_id']);
            $roleId = Yii::$app->session->get('parent_admin_role_id');

            /*$password = '';
            if ($userModel->password != $post['user']['password']) {
                $password = hash('sha256', $userModel->password . Yii::$app->params['passwordkey'], false);
            }*/
            $db = Yii::$app->db;
            $transaction = $db->beginTransaction();
            try {
                $time = time();
                if ($roleId == 2) {
                    @$adminAreacode = Yii::$app->session->get('role_area');
                    if ($areacode[0] . ':' . $areacode[1] != $adminAreacode) {
                        throw new Exception("所在城市填写错误，店东位置不在市公司范围内,请选择市公司的所在市");
                    }

                } else {
                    //如果所选的所在城市和地址不一样则抛出异常
                    @$temparea = $areacode[0] . ':' . $areacode[1];
                    if (Admin::findOne($post['user']['admin_id'])->area != $temparea) {
                        throw new Exception("所在城市和所属分公司不匹配");
                    }

                }

                if ($userModel) {
                    //  $userModel->load($post, 'user', false);
                    unset($userModel->password);
                    /* if ($password) {
                         $userModel->password = $password;
                     }*/

                    $userModel->area = implode(':', $areacode);
                    $userModel->mobile = $post['user']['mobile'];

                    $userModel->updated_at = $time;
                    $userModel->save(false);
                    if (!$userModel->save(false)) {
                        throw new Exception('添加user表失败!' . __LINE__);
                    }
                }

                /* $useraddrModel = Useraddr::find()->where('user_id=' . $post['user']['user_id'])->One();


                 $useraddrModel->realname = $userModel->realname;
                 $useraddrModel->province = $city[0];
                 $useraddrModel->city = $city[1];
                 $useraddrModel->county = $city[2];
                 $useraddrModel->longitude = $location['result']['location']['lng'];
                 $useraddrModel->latitude = $location['result']['location']['lat'];
                 $useraddrModel->updated_at = $time;
                 $useraddrModel->addrdetail = $post['user']['addrdetail'];
                 $useraddrModel->phone = $post['user']['mobile'];*/

                $shopModel = Shop::find()->where('id=' . $post['user']['id'])->One();
                $shopModel->shop_name = $post['user']['shop_name'];
                $shopModel->mobile = $post['user']['mobile'];
                $shopModel->realname = $post['user']['realname'];
                $shopModel->opening_time = $post['user']['opening_time'];
                $shopModel->closing_time = $post['user']['closing_time'];
                $shopModel->admin_id = $post['user']['admin_id'];
                $shopModel->logo = $post['user']['logo'];
                $shopModel->profit_rate = $post['user']['profit_rate'];
                $shopModel->updated_at = $time;
                $shopModel->province = $city[0];
                $shopModel->city = $city[1];
                $shopModel->county = $city[2];
                $shopModel->longitude = $location['result']['location']['lng'];
                $shopModel->latitude = $location['result']['location']['lat'];
                if($post['user']['shop_type']=='店铺'){
                    $shopModel->shop_type=0;
                }else{
                    $shopModel->shop_type=1;
                }
                if($post['user']['home_delivery']=='提供'){
                    $shopModel->home_delivery=1;
                }else{
                    $shopModel->home_delivery=0;
                }
                $shopModel->area = implode(':', $areacode);
                $shopModel->address = $post['user']['address'];
                unset($shopModel->profit_rate_amount);
                if (/*$useraddrModel->save(false) &&*/
                $shopModel->save(false)) {
                    $transaction->commit();
                    return ['rs' => 'true'];
                } else {
                    throw new Exception("编辑店东失败" . __LINE__);
                }

            } catch (Exception $e) {
                $transaction->rollBack();
                return ['rs' => "false", 'msg' => $e->getMessage()];
            }

        } else {
            return ['rs' => "false", 'msg' => '获取地理位置经纬度信息失败，稍后再试!'];
        }


    }


}
