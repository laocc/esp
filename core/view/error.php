<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=0"/>
    <meta name="format-detection" content="telephone=no"/>

    <title><?= $title ?></title>

    <style>
        body {
            margin: 0;
            padding: 0;
            font-size: 1em;
            color: #555555;
            font-family: "Source Code Pro", "Arial", "Microsoft YaHei", "msyh", "sans-serif";
        }

        table {
            width: 80%;
            margin: 1em auto;
            border: 1px solid #456;
            box-shadow: 5px 5px 2px #ccc;
        }

        tr, td {
            overflow: hidden;
        }

        td {
            text-indent: 0.5em;
            line-height: 2em;
        }

        table.head {
            background: #def;
        }

        table.head td.l {
            width: 6em;
            font-weight: bold;
        }

        td.msg {
            color: red;
        }

        table.trade tr:nth-child(odd) {
            background: #ffe;
        }

        table.trade tr.nav {
            background: #f0c040;
        }

        td.time {
            text-align: right;
            padding-right: 1em;
        }

        table.trade td {
            border-bottom: 1px solid #abc;
        }

        table.trade td.l {
            width: 40%;
        }

    </style>
</head>
<body>
<table class="head" cellpadding="0" cellspacing="0">
    <tr>
        <td class="l">错误代码：</td>
        <td><?= $code ?></td>
    </tr>
    <tr>
        <td class="l">错误信息：</td>
        <td class="msg"><?= $title ?></td>
    </tr>
    <tr>
        <td class="l">错误位置：</td>
        <td><?= $file ?></td>
    </tr>
    <?= $info ?>
</table>
<table class="trade" cellpadding="0" cellspacing="0">
    <tr class="nav">
        <td><b>Trace</b> : (执行顺序从上往下)</td>
        <td class="time"><?= $time ?></td>
    </tr>
    <?= $trace ?>
</table>
</body>
</html>