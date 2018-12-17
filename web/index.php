<?php

// comment out the following two lines when deployed to production dev test prod
//defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');
 //defined('YII_ENV') or define('YII_ENV', 'prod');
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';//工具文件

$config = require __DIR__ . '/../config/web.php';//应用配置

(new yii\web\Application($config))->run();//执行应用
