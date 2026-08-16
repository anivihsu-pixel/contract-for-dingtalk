#!/usr/bin/env bash
# 运行 PHP + JavaScript 测试基线
# 优先使用项目根 phpunit.phar；其次 vendor/bin/phpunit（composer require-dev 安装）。
set -e
cd "$(dirname "$0")/.."

PHPUNIT=""
if [ -f phpunit.phar ]; then
  PHPUNIT="php phpunit.phar"
elif [ -f vendor/bin/phpunit ]; then
  PHPUNIT="vendor/bin/phpunit"
fi

if [ -z "$PHPUNIT" ]; then
  echo "未找到 PHPUnit：请执行 composer install（require-dev 已含 phpunit/phpunit），或把 phpunit.phar 放到项目根目录。"
  exit 1
fi

echo ">>> 使用：$PHPUNIT"
$PHPUNIT --configuration phpunit.xml.dist

if ! command -v node >/dev/null 2>&1; then
  echo "未找到 Node.js，无法运行 JavaScript 测试。"
  exit 1
fi
if [ ! -d node_modules/jsdom ]; then
  echo "未安装 JavaScript 测试依赖，请先执行 npm ci。"
  exit 1
fi

echo ">>> JavaScript tests"
npm test
