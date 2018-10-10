<?php

namespace Liushuangxi\Xhgui;

/**
 * Class Collector
 *
 * @package Liushuangxi\Xhgui
 */
class Collector
{
    private static $logger = null;

    /**
     * 数据采集
     *
     * @param      $configFile
     * @param null $logger
     *
     * @return bool
     */
    public static function run($configFile, $logger = null)
    {
        try {
            ## 日志对象
            self::$logger = $logger;

            require_once dirname(__DIR__) . '/Xhgui/Config.php';

            ## 配置文件
            if (file_exists($configFile) && is_readable($configFile)) {
                \Xhgui_Config::load($configFile);
            } else {
                self::log('xhgui - config file ' . $configFile . ' not exists or can\'t read');

                return false;
            }

            ## 检查是否采样
            if (\Xhgui_Config::shouldRun()) {
                if (!self::loadExtension()) {
                    return false;		
                }
                self::registerShutdown();

                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            self::log($e->getMessage());

            return false;
        }
    }

    /**
     * 记录日志
     *
     * @param $message
     */
    private static function log($message)
    {
        if (method_exists(self::$logger, 'logInfo')) {
            self::$logger->logInfo($message);
        }
    }

    /**
     * 加载扩展
     *
     * @return bool
     */
    private static function loadExtension()
    {
        if (!extension_loaded('xhprof')
            && !extension_loaded('uprofiler')
            && !extension_loaded('tideways')
            && !extension_loaded('tideways_xhprof')
        ) {
            self::log('xhgui - either extension xhprof, uprofiler, tideways or tideways_xhprof must be loaded');

            return false;
        }

        if ((!extension_loaded('mongo') && !extension_loaded('mongodb'))
            && \Xhgui_Config::read('save.handler') === 'mongodb'
        ) {
            self::log('xhgui - extension mongo not loaded');

            return false;
        }

        $options = \Xhgui_Config::read('profiler.options');
        if (extension_loaded('uprofiler')) {
            uprofiler_enable(UPROFILER_FLAGS_CPU | UPROFILER_FLAGS_MEMORY, $options);
        } else if (extension_loaded('tideways')) {
            tideways_enable(TIDEWAYS_FLAGS_CPU | TIDEWAYS_FLAGS_MEMORY | TIDEWAYS_FLAGS_NO_SPANS, $options);
        } elseif (extension_loaded('tideways_xhprof')) {
            tideways_xhprof_enable(TIDEWAYS_XHPROF_FLAGS_CPU | TIDEWAYS_XHPROF_FLAGS_MEMORY);
        } else {
            if (PHP_MAJOR_VERSION == 5 && PHP_MINOR_VERSION > 4) {
                xhprof_enable(XHPROF_FLAGS_CPU | XHPROF_FLAGS_MEMORY | XHPROF_FLAGS_NO_BUILTINS, $options);
            } else {
                xhprof_enable(XHPROF_FLAGS_CPU | XHPROF_FLAGS_MEMORY, $options);
            }
        }

        return true;
    }

    /**
     * 注册shutdown函数
     */
    private static function registerShutdown()
    {
        register_shutdown_function(
            function () {
                ## 性能数据
                if (extension_loaded('uprofiler')) {
                    $data['profile'] = uprofiler_disable();
                } else if (extension_loaded('tideways')) {
                    $data['profile'] = tideways_disable();
                } elseif (extension_loaded('tideways_xhprof')) {
                    $data['profile'] = tideways_xhprof_disable();
                } else {
                    $data['profile'] = xhprof_disable();
                }

                ignore_user_abort(true);
                flush();
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }

                ## 数据整理
                if (!isset($_SERVER['REQUEST_TIME_FLOAT'])) {
                    $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);
                }

                $uri = array_key_exists('REQUEST_URI', $_SERVER)
                    ? $_SERVER['REQUEST_URI']
                    : null;

                if (empty($uri) && isset($_SERVER['argv'])) {
                    $cmd = basename($_SERVER['argv'][0]);
                    $uri = $cmd . ' ' . implode(' ', array_slice($_SERVER['argv'], 1));
                }

                $time = array_key_exists('REQUEST_TIME', $_SERVER)
                    ? $_SERVER['REQUEST_TIME']
                    : time();

                $delimiter        = (strpos($_SERVER['REQUEST_TIME_FLOAT'], ',') !== false) ? ',' : '.';
                $requestTimeFloat = explode($delimiter, $_SERVER['REQUEST_TIME_FLOAT']);
                if (!isset($requestTimeFloat[1])) {
                    $requestTimeFloat[1] = 0;
                }

                if (\Xhgui_Config::read('save.handler') === 'mongodb') {
                    $requestTs      = new \MongoDate($time);
                    $requestTsMicro = new \MongoDate($requestTimeFloat[0], $requestTimeFloat[1]);
                } else {
                    $requestTs      = ['sec' => $time, 'usec' => 0];
                    $requestTsMicro = ['sec' => $requestTimeFloat[0], 'usec' => $requestTimeFloat[1]];
                }

                $data['meta'] = [
                    'url'              => $uri,
                    'SERVER'           => $_SERVER,
                    'get'              => $_GET,
                    'env'              => $_ENV,
                    'simple_url'       => \Xhgui_Util::simpleUrl($uri),
                    'request_ts'       => $requestTs,
                    'request_ts_micro' => $requestTsMicro,
                    'request_date'     => date('Y-m-d', $time),
                ];

                ## 保存数据
                try {
                    $config = \Xhgui_Config::all();
                    $config += ['db.options' => []];
                    $saver  = \Xhgui_Saver::factory($config);
                    $saver->save($data);
                } catch (\Exception $e) {
                    self::log('xhgui - ' . $e->getMessage());
                }
            }
        );
    }
}
