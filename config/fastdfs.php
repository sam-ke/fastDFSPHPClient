<?php
/**
 * fastdfs 的本地配置
 *
 * 如下配置示例，包括四个监视器、四个存储节点，每个存储节点都有一个备份
 */
return array(
    'storage'=>array(

        'group1' => array(
            array(
                'ip_addr' => '127.0.0.1',
                'port' => 23000
            ),
            array(
                'ip_addr' => '127.0.0.2',
                'port' => 23000
            )
        ),
        'group2' => array(
            array(
                'ip_addr' => '127.0.0.3',
                'port' => 23000
            ),
            array(
                'ip_addr' => '127.0.0.4',
                'port' => 23000
            )
        ),
        'group3' => array(
            array(
                'ip_addr' => '127.0.0.5',
                'port' => 23000
            ),
            array(
                'ip_addr' => '127.0.0.6',
                'port' => 23000
            )
        ),
        'group4' => array(
            array(
                'ip_addr' => '127.0.0.7',
                'port' => 23000
            ),
            array(
                'ip_addr' =>'127.0.0.8',
                'port' => 23000
            )
        )
    ),


    'tracker'=>array(
        array(
            'ip_addr' => '127.0.0.2',
            'port' => 22122
        ),
        array(
            'ip_addr' => '127.0.0.4',
            'port' => 22122
        ),
        array(
            'ip_addr' => '127.0.0.6',
            'port' => 22122
        ),
        array(
            'ip_addr' => '127.0.0.8',
            'port' => 22122
        )
    )
);
