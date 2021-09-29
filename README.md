
# ESP (Efficient Simple PHP)
- PHP >7
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


# 说明文档：
本库`readme`目录中含有核心部分的文档：
- [目录](./readme/0.aboutme.md)

