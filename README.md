# XHGUI Collector
## 监控后台
* [官版](https://github.com/perftools/xhgui)
* [汉化版](https://github.com/laynefyc/xhgui-branch)
## 安装
### 扩展
* [MongoDB](http://www.mongodb.org/)
* [MongoDB Extension](http://pecl.php.net/package/mongodb)
* [Xhprof](http://pecl.php.net/package/xhprof),[Uprofiler](https://github.com/FriendsOfPHP/uprofiler),[Tideways](https://github.com/tideways/php-profiler-extension)
### 配置
#### MongoDB
<pre>
$ mongo
> use xhprof
> db.results.ensureIndex( { 'meta.SERVER.REQUEST_TIME' : -1 } )
> db.results.ensureIndex( { 'profile.main().wt' : -1 } )
> db.results.ensureIndex( { 'profile.main().mu' : -1 } )
> db.results.ensureIndex( { 'profile.main().cpu' : -1 } )
> db.results.ensureIndex( { 'meta.url' : 1 } )
> db.results.ensureIndex( { 'meta.simple_url' : 1 } )
</pre>
## 使用
### 代码安装
<pre>
composer require liushuangxi/xhgui-collector -vvv
</pre>
### [配置文件](https://github.com/liushuangxi/xhgui-collector/blob/master/config/config.default.php)
### PHP调用
collector.php
<pre>
use Liushuangxi\Xhgui\Collector;
 
$configFile = "/path/to/config.php";
 
$logger = new class()
{
    public function logInfo($message)
    {
        echo $message . "\n";
    }
};
 
Collector::run($configFile, $logger);
</pre>
### NGINX调用
<pre>
nginx web.conf
 
server {
   ......
    fastcgi_param PHP_VALUE "auto_prepend_file=/path/to/collector.php";
   ......
}
</pre>