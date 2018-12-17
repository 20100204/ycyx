<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/2/28
 * Time: 11:21
 */

namespace app\controllers;


use app\common\behavior\NoCsrs;
use app\models\Admin;
use app\models\Shop;
use yii\web\Controller;
use Yii;

class GuestsController extends Controller
{

    public function behaviors()
    {
        return [
            'csrf' => [
                'class' => NoCsrs::className(),
                'controller' => $this,
                'actions' => [
                    'list',
                ]
            ]
        ];
    }


    public function actionList()
    {
        Yii::$app->response->format = 'json';
        $curPage = 1;
        $where = ['user.type="guest"'];
         $search = Yii::$app->request->post();
        if ($search) {
            foreach ($search as $k => $v) {
                if ($k == 'curpage') {
                    $curPage = $v;
                    continue;
                }
                if (trim($v)) {
                    if($k=='nickname'){
                        $where[] = 'e.'.$k . ' like "%' . trim($v) . '%"';
                    }

                    if($k=='shop_name'){
                        $where[] = 'c.'.$k . ' like "%' . trim($v) . '%"';
                    }

                    if($k=='mobile'){
                        $where[] = 'user.'.$k . ' like "%' . trim($v) . '%"';
                    }
                    if($k=='company_name'){
                        $where[] = 'd.area="' . trim($v) . '"';
                    }

                }
            }

        }

        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            //如果是市公司用户who_uploader in()市公司只能看到本市的;
            $who_uploaderIds = Admin::find()->where('area="' . Yii::$app->session->get('role_area') . '"')->asArray()->select('id')->all();
            $who_uploaderIds = array_column($who_uploaderIds, 'id');
            $shopIds = Shop::find()->where(['admin_id' => $who_uploaderIds])->asArray()->select('id')->all();

            if ($shopIds) {
                $where[] = "c.id in (" . implode(',', array_column($shopIds, 'id')) . ")";

            }
            $citys = Admin::find()->select('admin.city,area') ->where('area="'.Yii::$app->session->get('role_area').'"')->distinct()->asArray()->all();
        }else{
           // $citys = Admin::find()->select('admin.city,area')->leftJoin('admin_role', 'admin.admin_role_id=admin_role.id')->distinct()->where('admin.status=1 and admin_role.parent_id=2 ')->asArray()->all();
            $citys = Admin::find()->select("city,area")->where("status=1 and admin_role_id=9")->all();
            array_unshift($citys, ['area' => '', 'city' => '全国']);
        }
        $where[] = "a.is_checked=1";
        $where = implode(' and ', $where);
        $pageSize = Yii::$app->params['pagesize'];
        $offset = $pageSize * ($curPage - 1);
        $query = new \yii\db\Query();
        $query = $query->select("user.*,c.shop_name,c.province,c.city,c.county,c.address,d.city as company_name,e.nickname")
            ->from("user")
            ->leftJoin('user_rel_shop  as a', 'user.id=a.user_id')
            ->leftJoin('user_rel_miniprogram as e',' user.id=e.user_id')
            ->leftJoin('shop as c','a.shop_id=c.id')
            ->leftJoin('admin as d','d.id=c.admin_id')
            ->where($where);
        $rs = $query->offset($offset)->limit($pageSize)->orderBy('user.updated_at desc ,user.id desc')->all();
        $count = $query->count();

        if ($rs) {
            foreach ($rs as $k => $v) {
                @$rs[$k]['created_at'] = date("Y-m-d H:i:s", $v['created_at']);
                @$rs[$k]['addr'] = $v['province'] . $v['city'] . $v['county'] . $v['address'];
            }
        }

        return ['rs' => $rs, 'totalPage' => $count, 'pageSize' => $pageSize, 'search' => @$search, 'currpage' => $curPage,'citys'=>$citys];


    }

}
