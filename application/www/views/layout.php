<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="UTF-8">
    <?php
    /**
     * @var $_title ;
     * @var $_meta ;
     * @var $_css ;
     * @var $_js_head ;
     * @var $_js_body ;
     * @var $_js_foot ;
     * @var $_js_defer ;
     * @var $_view_html ;
     */
    ?>
    <?= $_meta; ?>
    <?= $_css; ?>
    <?= $_js_head; ?>
    <title><?= $_title ?></title>
    <style>
        html {
            font-family: "Microsoft YaHei", "Source Code Pro", "Arial", "sans-serif";
        }

        body {
            padding: 0;
            margin: 0;
            background: #eeeeee;
        }

        div {
            width: 100%;
            height: 2em;
            line-height: 2em;
            display: block;
            font-size: 100px;
            clear: both;
            text-align: center;
            margin-top: 3em;
            overflow: hidden;
            color: #eee;
            background: #123;
            text-shadow: 2px 2px 4px #fff;
            border-bottom: 3px solid #aa0000;
        }
    </style>
</head>
<body>
<?= $_js_body ?>
<?= $_view_html ?>
</body>
<?= $_js_foot ?>
<?= $_js_defer ?>
</html>