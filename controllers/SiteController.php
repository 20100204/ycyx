<?php

namespace app\controllers;

use app\models\Shop;
use app\models\Ycypuser;
use app\queue\TemplateJob;
use Yii;
use yii\db\Query;
use yii\filters\AccessControl;
use yii\helpers\VarDumper;
use yii\queue\Queue;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\ContactForm;

class SiteController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        $this->layout = false;
        $pics = "http://yunwei.ycypsz.com/upload/imgs/20180311/22f91c8f96f130d9e89fb659f147bc32.png";
        $percent = 0.5;


        $key = 'AM2BZ-F33WK-WE3J4-ANFGB-HLGNS-Y6BWP';
         $location = file_get_contents('http://apis.map.qq.com/ws/geocoder/v1/?address=' . "江苏省无锡市政府" . '&key=' . $key);
        return $this->renderContent($location);
        //DAO->query build ->Ar
        //DAO
        //1、queryAll,queryOne,queryScalar,queryColumn
       // $conn = Yii::$app->db;
        //$sql="select count(*) from user where id>1 ";
       // $common = $conn->createCommand($sql);
        //$data = $common->queryAll(); //二维数组
        //$data = $common->queryOne(); //一维数组
        //$data = $common->queryColumn(); //一维数组，返回指定的列
       // $data = $common->queryScalar();//统计值
       // print_r($data);
        //VarDumper::dump($data);

        //2、DAO插入、更新和删除  1Yii::$app->db->createCommand($sql)->execute()2insert,batchInsert update,delete 执行结果是影响的条数
        //  echo Yii::$app->db->createCommand("insert test (name) values('china')")->execute();
       //   echo Yii::$app->db->createCommand()->insert('test',['name'=>'abeil'])->execute();
       //批量插入
     //   echo  Yii::$app->db->createCommand()->batchInsert('test',['name','age'],[['a',1],['b',2],['c',3]])->execute();
        //3更新
       // echo Yii::$app->db->createCommand()->update('test',['age'=>100],['id'=>1])->execute();
        //4删除
       // echo Yii::$app->db->createCommand()->delete('test')->execute();

        //绑定参数 bindValue  bindValues   bindParam

//===================================
        echo "<pre>";
        //1、query
      // $query = (new Query())->select("age")->from('test');
       //echo $query->all();
    //   echo $query->createCommand()->sql;
        $model = Ycypuser::findOne(2);
        print_r($model->attributes);
        print_r($model->attributes());
        print_r($model->getOldAttribute());
        $model->tel = 1;
        $model->tel = 2;
        print_r($model->attributes);
        print_r($model->attributes());
        print_r($model->getOldAttribute());
        $model->save();
        print_r($model->attributes);
        print_r($model->attributes());
        print_r($model->getOldAttribute('tel'));
       //$query
$this->layout = false;
    return $this->renderContent($model['area']);

      //  print_r($query);

//        for($i=0;$i<10;$i++){
//            $curl = curl_init();
//            curl_setopt($curl, CURLOPT_URL, 'https://user.ycypsz.com/api/v1/wechat/tplmsg/new_coupon');
//            curl_setopt($curl, CURLOPT_HEADER, false);
//            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
//            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
//            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
//            curl_setopt($curl, CURLOPT_POST, 1);
//            $post_data = array(
//                "user_id" =>1325,
//                "nickname" => "石头",
//                "title"=>"miaomaio",
//                "num"=>1,
//                "key"=>"vi3aeve7ieB4fa6y"
//            );
//            curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
//            curl_exec($curl);
//            curl_close($curl);
//        }



//        return;
//        Yii::$app->queue->push(new TemplateJob([
//            'userId'=>10
//        ]));
//        return 'ss';
//            $rs = array(
//                    array('订单数量',2,1,1),
//                    array('交易金额',2,1,1)
//            );
//
//       return json_encode(Shop::find()->where('id=2')->asArray()->one());


//        return json_encode($rs, JSON_UNESCAPED_UNICODE);
//         return hash('sha256', '123456' . Yii::$app->params['passwordkey'], false);
//        print_r(unserialize('a:1:{s:5:"goods";a:3:{i:0;s:11:"goods_index";i:1;s:9:"goodslist";i:2;s:8:"goodsadd";}}'));exit;
        //Ycypuser::findAll(['>','id',1]);
        return $this->render('index');
    }

    /**
     * Login action.
     *
     * @return Response|string
     */
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        }
        return $this->render('login', [
            'model' => $model,
        ]);
    }

    /**
     * Logout action.
     *
     * @return Response
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    /**
     * Displays contact page.
     *
     * @return Response|string
     */
    public function actionContact()
    {
        $model = new ContactForm();
        if ($model->load(Yii::$app->request->post()) && $model->contact(Yii::$app->params['adminEmail'])) {
            Yii::$app->session->setFlash('contactFormSubmitted');

            return $this->refresh();
        }
        return $this->render('contact', [
            'model' => $model,
        ]);
    }

    /**
     * Displays about page.
     *
     * @return string
     */
    public function actionAbout()
    {
        return $this->render('about');
    }
}
