<?php
namespace app\common\service;
use think\facade\Cache;
class IdempotencyService
{
 public static function cached(int $userId,string $scope,string $key):?array
 {
  $key=trim($key);if($key==='')return null;if(!preg_match('/^[A-Za-z0-9._:-]{8,100}$/',$key))throw new \RuntimeException('幂等键格式不合法');
  $value=Cache::get(self::cacheKey($userId,$scope,$key));return is_array($value)?$value:null;
 }
 public static function remember(int $userId,string $scope,string $key,array $result):void
 {
  $key=trim($key);if($key!=='')Cache::set(self::cacheKey($userId,$scope,$key),$result,86400);
 }
 private static function cacheKey(int $userId,string $scope,string $key):string{return 'idem:'.hash('sha256',$userId.'|'.$scope.'|'.$key);}
 public static function execute(int $userId,string $scope,string $key,callable $handler):array
 {
  $key=trim($key);if($key==='')return $handler();if(!preg_match('/^[A-Za-z0-9._:-]{8,100}$/',$key))throw new \RuntimeException('幂等键格式不合法');
  $cacheKey=self::cacheKey($userId,$scope,$key);$cached=Cache::get($cacheKey);if(is_array($cached))return $cached;
  $result=$handler();Cache::set($cacheKey,$result,86400);return $result;
 }
}
