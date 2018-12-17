<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/5
 * Time: 16:57
 */

namespace app\controllers;

use app\common\behavior\NoCsrs;
use app\models\Shop;
use app\models\Warehouse;
use yii\web\Controller;
use Yii;

class WarehouseController extends Controller
{
    public function behaviors()
    {
        return [
            'csrf' => [
                'class' => NoCsrs::className(),
                'controller' => $this,
                'actions' => [
                    'addhouse',
                ]
            ]
        ];
    }

    /*改变仓库状态*/
    public function actionChangestatus(){
        Yii::$app->response->format='json';
         $get = Yii::$app->request->get();
        $adminId = Yii::$app->session->get('admin_id');
        if(!$get['id']||!$get['status']||!$adminId){
            return ['rs'=>"false",'msg'=>"参数缺失"];
        }
        $model = Warehouse::findOne($get['id']);
        $status = $get['status']==2?1:2;
        $model->status = $status;
        $model->checker_id = $adminId;
        $model->updated_at = time();
        if($model->save(false)){
            return ['rs'=>'true','checker'=>Yii::$app->session->get('username'),'update'=>date("Y-m-d H:i:s")];
        }
        return ['rs'=>'false','msg'=>"操作失败"];
    }
    public function actionHouselist()
    {
        Yii::$app->response->format = 'json';
        $sql="select concat(a.province,a.city,a.county,a.address) as ca,a.province,a.city,a.county,a.address,a.shop_id,a.id,CASE a.warehouse_type WHEN 1 THEN '前置仓' ELSE '城市仓' END   as warehouse_type,a.warehouse_name,a.leader_name,a.leader_mobile,FROM_UNIXTIME(a.updated_at) as updated,a.status,b.username,c.username as checker from warehouse as a left JOIN admin as b on a.admin_id=b.id left JOIN  admin as c on a.checker_id=c.id order by a.updated_at DESC ";
        $rs= Yii::$app->db->createCommand($sql)->queryAll();
        $enableAdd = false;
        $enableEdit = false;
        $enableChange= false;
        if (Yii::$app->session->get('username') != 'admin') {
            $aulist = explode('|', Yii::$app->session->get('authlist'));
            if (!in_array('warehouseadd', $aulist)) {
                $enableAdd = true;
            }
            if (!in_array('warehousechangestatus', $aulist)) {
                $enableChange = true;
            }
            if (!in_array('warehouseedit', $aulist)) {
                $enableEdit = true;
            }

        }
        return ['rs'=>$rs,'enableAdd'=>$enableAdd,'enableEdit'=>$enableEdit,'enableChange'=>$enableChange];

    }


    /*所有的可用的前置仓*/
    public function actionShops(){
        Yii::$app->response->format="json";
        $where =" status !=2 ";
         if(Yii::$app->session->get("parent_admin_role_id")==2){
             $area = Yii::$app->session->get("role_area");
           $where .=" and area like '%.$area.%'";
         }
        $sql     = "select id,CASE shop_type WHEN 1 THEN '代理' ELSE '超市' END   as shop_type,shop_name,mobile,city,realname from shop where  ".$where;
        return $command = Yii::$app->db->createCommand($sql)->queryAll();

    }

    /*取得前置舱信息*/
    public function actionGetshop(){
        Yii::$app->response->format = 'json';
        return Shop::findOne(Yii::$app->request->get("id"));
    }



    public function actionAddhouse()
    {
        Yii::$app->response->format = 'json';
         $post = Yii::$app->request->post();
         $rs = ['rs' => 'false', 'msg' => "插入失败"];
        if (!$post['skuItem']['leader_mobile'] || !$post['skuItem']['leader_name'] || !$post['skuItem']['warehouse_name'] || !$post['skuItem']['warehouse_type']) {
            $rs['msg'] = "参数不全";
            return $rs;
        }
        if($post['skuItem']['warehouse_type']=="前置仓"){
            if(!$post['skuItem']['shop_id']){
                $rs['msg'] = "请选择店铺".__LINE__;
                return $rs;
            }

             $shopIfno = Shop::findOne($post['skuItem']['shop_id']);
             $area  = explode(":",$shopIfno->area);
            $post['skuItem']['area'] = $area[0] . ":" . $area[1];
            $post['skuItem']['address'] = $shopIfno->address;
            $post['skuItem']['longitude']=$shopIfno->longitude;
            $post['skuItem']['latitude']=$shopIfno->latitude;
            $post['skuItem']['province'] = $shopIfno->province;
            $post['skuItem']['city'] = $shopIfno->city;
            $post['skuItem']['county'] = $shopIfno->county;

        }else{
            $area = array_column($post['skuItem']['city'], 'code');
            $city = array_column($post['skuItem']['city'], 'name');

            $post['skuItem']['area'] = $area[0] . ":" . $area[1];

           // $post['skuItem']['address'] = implode("", array_column($post['skuItem']['city'], 'name')).$post['skuItem']['address'];
            $key = 'AM2BZ-F33WK-WE3J4-ANFGB-HLGNS-Y6BWP';
            $location = file_get_contents('https://apis.map.qq.com/ws/geocoder/v1/?address=' . implode("",$city).$post['skuItem']['address'] . '&key=' . $key);
            $location = json_decode($location, true);
            if($location['status']){
                $rs['msg'] = "地址查询错误";
                return $rs;
            }
            $post['skuItem']['longitude'] = $location['result']['location']['lng'];
            $post['skuItem']['latitude'] = $location['result']['location']['lat'];
            $post['skuItem']['province'] = $city[0];
            $post['skuItem']['city'] = $city[1];
            $post['skuItem']['county'] = $city[2];
        }
        $post['skuItem']['warehouse_type']=$post['skuItem']['warehouse_type']=="前置仓"?1:0;

        $time = time();

        $post['skuItem']['updated_at'] = $time;
        if($post['skuItem']['id']){
            $model = Warehouse::findOne($post['skuItem']['id']);
            $post['skuItem']['status'] = 2;
        }else{
            $model = new Warehouse();
            $post['skuItem']['created_at'] = $time;
            $post['skuItem']['admin_id'] = Yii::$app->session->get("admin_id");
        }

        $model->load($post,"skuItem",false);
        if($model->save(false)){
            return ['rs'=>"true"];
        }
        return ['rs'=>'false','msg'=>"插入失败"];


    }
}
