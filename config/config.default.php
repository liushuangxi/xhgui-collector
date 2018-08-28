<?php

return [
    'debug'               => false,
    'mode'                => 'product',

    # 连接信息
    'save.handler'        => 'mongodb',
    'db.host'             => 'mongodb://127.0.0.1:27017',
    'db.db'               => 'xhprof',
    'db.options'          => [
        'socketTimeoutMS'          => 1000,
        'connectTimeoutMS'         => 1000,
        'serverSelectionTimeoutMS' => 1000,
    ],

    # 采样规则 true/false
    'profiler.enable'     => function () {
        return rand(1, 3) == 1;
    },

    # 基础路径，用于同类对比
    'profiler.simple_url' => function ($url) {
        return preg_replace('/\=\w+/', '', $url);
    },

    'profiler.options' => [],
];
