<?php
declare(strict_types=1);

namespace esp\scripts;

class Install
{
    private static function getRoot()
    {
        if ($dirI = strpos(__DIR__, '/vendor/laocc/esp/core')) {
            $rootPath = substr(__DIR__, 0, $dirI);
        } else if ($dirI = strpos(__DIR__, '/laocc/esp/core')) {
            $rootPath = (substr(__DIR__, 0, $dirI));
        } else {
            $rootPath = dirname($_SERVER['DOCUMENT_ROOT'], 2);
        }
        return $rootPath;
    }

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
        echo date('Y-m-d H:i:s') . " Install End;\n ";
        echo "check runtime: ";
        $root = self::getRoot();
        if (!file_exists($root . '/runtime')) {
            @mkdir($root . '/runtime', 0740, true);
            @chown($root . '/runtime', 'www');
            echo "mkdir\n";
        } else {
            echo "success\n";
        }
    }


    public static function update_post()
    {
        echo date('Y-m-d H:i:s') . " Update End;\n ";

        $root = self::getRoot();
        if (!file_exists($root . '/runtime')) {
            @mkdir($root . '/runtime/debug', 0740, true);
            @chown($root . '/runtime/debug', 'www');
            echo "mkdir\n";
        } else {
            echo "success\n";
        }
    }
}