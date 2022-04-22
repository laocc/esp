<?php
declare(strict_types=1);

namespace esp\scripts;

class Install
{

    public static function install_pre()
    {
        echo date('Y-m-d H:i:s') . " Esp Install:\n ";
    }

    public static function update_pre()
    {
        echo date('Y-m-d H:i:s') . " Esp Update:\n ";
    }

    public static function install_post()
    {
        echo date('Y-m-d H:i:s') . " Install End\n ";
        self::checkRuntime();
    }


    public static function update_post()
    {
        echo date('Y-m-d H:i:s') . " Update End\n ";
        self::checkRuntime();
    }

    private static function checkRuntime()
    {
        $root = self::getRoot();
        echo "check {$root}/runtime: ";
        if (!file_exists($root . '/runtime')) {
            $stat = stat($root);
            @mkdir($root . '/runtime', 0740, true);
//            @chown($root . '/runtime', $stat['uid']);
//            @chgrp($root . '/runtime', $stat['gid']);
            @chown($root . '/runtime', 'www');
            @chgrp($root . '/runtime', 'www');
            echo "mkdir\n";
        } else {
            echo "success\n";
        }
    }

    private static function getRoot()
    {
        if ($dirI = strpos(__DIR__, '/vendor/laocc/esp/scripts')) {
            $rootPath = substr(__DIR__, 0, $dirI);
        } else if ($dirI = strpos(__DIR__, '/laocc/esp/scripts')) {
            $rootPath = (substr(__DIR__, 0, $dirI));
        } else {
            $rootPath = dirname($_SERVER['DOCUMENT_ROOT'], 2);
        }
        return $rootPath;
    }

}