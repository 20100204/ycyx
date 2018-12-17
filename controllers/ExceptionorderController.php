<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/2/15
 * Time: 10:13
 */

namespace app\controllers;

use app\common\behavior\NoCsrs;

use app\models\Admin;
use app\models\ExceptionOrder;
use yii\web\Controller;
use Yii;

class ExceptionorderController   extends Controller
{
    public function behaviors()
    {
        return [
            'csrf' => [
                'class' => NoCsrs::className(),
                'controller' => $this,
                'actions' => [
                    "list",
                    "check"
                ]
            ]
        ];
    }

    public function actionList(){
        Yii::$app->response->format = 'json';
        $where = [];
        $curPage = 1;
        if (Yii::$app->request->isPost) {
            $search = Yii::$app->request->post();
            foreach ($search as $k => $v) {
                if ($k == 'curpage') {
                    $curPage = $v;
                    continue;
                }
                if ($search[$k]) {
                   $where[] = 'a.'.$k.'=' . trim($v);
                }
            }
        }
        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            //如果是市公司用户who_uploader in()市公司只能看到本市的;
            $who_uploaderIds = Admin::find()->where('area="' . Yii::$app->session->get('role_area') . '"')->asArray()->select('id')->all();
            $who_uploaderIds = array_column($who_uploaderIds, 'id');
            $where[] = 'd.admin_id in (' . implode(',', $who_uploaderIds) . ')';

        }


        $where = $where ? implode(' and ', $where) : [];

        $query = new \yii\db\Query();
        $pageSize = Yii::$app->params['pagesize'];
        $offset = $pageSize * ($curPage - 1);
        $query = $query->select("a.*,b.username   ")
            ->from('exception_order as a')
            ->leftJoin('order as c','a.order_id=c.id')
            ->leftJoin('shop as d','c.shop_id=d.id')
            ->leftJoin('admin as b', 'a.admin_id=b.id');

        if ($where) {
            $query = $query->where($where);
        }
        $totalPage = $query->count();
        $rs = $query->offset($offset)->limit($pageSize)->orderBy('a.created_at desc,a.id desc')->all();
        foreach ($rs as $k => $v) {
            $rs[$k]['created_at'] = date("Y-m-d H:i:s", $v['created_at']);
            $rs[$k]['audited_at'] =  $v['audited_at']? date("Y-m-d H:i:s", $v['audited_at']) :null;
        }
        $enableCheck = false;
        if (Yii::$app->session->get('username') != 'admin') {
            $aulist = explode('|', Yii::$app->session->get('authlist'));
            if (!in_array('excptionordercheck', $aulist)) {
                $enableCheck = true;
            }

        }
        return ['rs' => $rs, 'totalPage' => $totalPage, 'pageSize' => $pageSize, 'search' => @$search, 'currpage' => $curPage,'enableCheck'=>$enableCheck];

    }


    public function actionCheck(){

        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post('id');
        if(!$post){
            return ['rs'=>'false','msg'=>'参数缺失，无法审核'];
        }

        if(!Yii::$app->session->get('admin_id')){
            return ['rs'=>'false','msg'=>'参数缺失，无法审核'];
        }

        if(!ExceptionOrder::updateAll(['admin_id'=>Yii::$app->session->get('admin_id'),'audited_at'=>time(),'status'=>1],['id'=>$post])){
            return ['rs'=>'false','msg'=>' 审核失败'];
        }
        return ['rs'=>'true'];
    }


}
