#安装 Composer
Composer 需要 PHP 5.3.2+ 才能运行。
```
# curl -sS https://getcomposer.org/installer | php
```
这个命令会将 composer.phar 下载到当前目录。PHAR（PHP 压缩包）是一个压缩格式，可以在命令行下直接运行。
1. 全局安装：
```
# mv composer.phar /usr/local/bin/composer

//在操作系统中任何地方都可直接执行composer
```
2. 项目单用：
```
# mv composer.phar /home/<SITE_PATH>

//只可以在<SITE PATH>中执行，而且须以下面方式：

# php composer.phar [option]
```

3. 加入到环境变量PATH


通常情况下只需将 composer.phar 的位置加入到 PATH 环境变量就可以，不一定要全局安装。


#composer定义设置
在项目目录下创建一个 composer.json 文件，大体格式如下：
```
{
    "name": "laocc/yaf",
    "require": {
        "php": ">7.0",
        "ext-yaf": ">3.0",
        "monolog/monolog": "1.2.*",
    },
    "require-dev": {
        "monolog/monolog": "1.2.*"
    }
}
```
#从中国镜像下载
在上面基础上添加：
```
{
    "require": {
        "monolog/monolog": "1.2.*"
    },
    "repositories": {
        "packagist": {
            "type": "composer",
            "url": "https://packagist.phpcomposer.com"
        }
    }
}
```
全局安装：
```
composer config -g repo.packagist composer https://packagist.phpcomposer.com
```
或对当前项目添加：
```
composer config repo.packagist composer https://packagist.phpcomposer.com
```
和上面手动加repositories是一样的，这句是自动添加上这部分。

#安装依赖
安装依赖非常简单，只需在项目目录下运行：
```
composer install
```
如果没有全局安装的话，则运行：
```
php composer.phar install
```
自动加载
Composer 提供了自动加载的特性，只需在你的代码的初始化部分中加入下面一行：
```
require 'vendor/autoload.php';
```
更新库：
```
composer update
```

只更新autoload加载项
```
composer dump-autoload
```



# 本系统composer.json中引入的应用说明：

- smarty/smarty: 模版标签引擎，\library\plugins\ext\Visual.php中调用
- michelf/php-markdown: MD文件解析引擎，控制器中显示MD代码时可能用到
- knplabs/knp-snappy: 生成网页快照用到，详见【关于网页快照.md】
- profburial/wkhtmltopdf-binaries: wkhtmltopdf库，用于生成网页快照
- mjaschen/phpgeo: 计算两个GPS之间距离，详见其自身的README.md

# 建议：
别把`vendor`放到你的GIT目录，也就是把这个添加到`.gitignore`中，线上服务器自行另拉取，避免你的GIT库爆掉，因为几个扩展拉下来的体积可不小。有些GIT服务器对库体积是有限制的，比如我用`coding.net`免费库，限制每个库1G量。

