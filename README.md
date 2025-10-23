# ESP (Efficient Simple PHP)

- PHP>8.1
- 这是一个高效简洁的PHP框架
- 框架已应用于实际项目多年，但仍处于持续完善阶段，升级时请注意查看更新说明。

# 框架安装

1. `composer`直接安装：

```
composer create-project laocc/esp-install website
```

其中`website`为项目目录，可任意自定义

2. 在`composer.json`中引入

```json
{
  "name": "website/1.1",
  "require": {
    "laocc/esp": "*"
  }
}
```

在生产环境中，建议执行加载优化

```
composer dump-autoload --optimize
```

本库`readme`目录中含有核心部分的文档：

# 文档目录

- [文档目录](./readme/0.aboutme.md)
- [控制器、控制器方法`Controller`](./readme/1.controllers.md)
- [视图`View`和`layout`](./readme/2.views.md)
- [数据模型`Model`](./readme/3.models.md)
- [数据库`Mysql`及`Redis`](./readme/4.databases.md)
- [路由`Router`](./readme/5.routes.md)
- [请求方法控制`Request`](./readme/6.request.md)
- [结果显示`Response`和`Response`](./readme/7.response.md)
- [`Cookies`和`Session`](./readme/8.cookies.md)
- [标签解析器`Adapter`](./readme/9.adapter.md)
- [缓存及生成静态文件`Cache`](./readme/10.cache.md)
- [插件`Plugs`和`bootstrap`](./readme/11.plugs.md)
- [调试器`Debug`](./readme/20.debug.md)
