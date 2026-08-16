<?php
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
require ROOT_PATH . 'vendor/autoload.php';
if (is_file(ROOT_PATH . '.env')) Dotenv\Dotenv::createImmutable(ROOT_PATH)->load();
$app = new think\App(ROOT_PATH); $app->initialize();
$db = think\facade\Db::connect('sqlite');
$exists = $db->name('system_config')->where('config_key', 'dict_customer_industry')->count();
if (!$exists) {
    $db->name('system_config')->insert([
        'config_key' => 'dict_customer_industry',
        'config_value' => json_encode(['GOV'=>'政府单位','REAL_ESTATE'=>'房地产','FOOD_TOURISM'=>'餐饮旅游','OTHER'=>'其他'], JSON_UNESCAPED_UNICODE),
        'group_name' => 'dict',
    ]);
}
echo "SQLite v2.50.3 customer industry dictionary migration complete\n";
