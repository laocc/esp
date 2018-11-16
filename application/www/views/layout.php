<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="UTF-8">
    <title><?= $_title ?></title>
    <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=0"/>
    <meta name="format-detection" content="telephone=no"/>
    <?= $_css ?>
    <link rel="stylesheet" href="/resource/layui-v2.3.0/css/layui.css" media="all">
    <link rel="stylesheet" href="/resource/css/auto.css" media="all">
    <link rel="stylesheet" href="/resource/css/page.css" media="all">
    <script src="/resource/layui-v2.3.0/layui.all.js"></script>

</head>
<body>
<?php
echo $_view_html;
?>
</body>

<script>
    layui.table.init('layuiTable');
</script>
</html>