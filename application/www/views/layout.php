<!DOCTYPE html>
<html lang="en">
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
     * @var $_body_html ;
     */
    ?>
    <title><?= $_title ?></title>
    <?= $_meta; ?>
    <?= $_css; ?>
    <?= $_js_head; ?>
    <style>
        body {
            width: 100%;
            height: 100%;
        }

        div {
            width: 100%;
            height: 100%;
            line-height: 100%;
            font-size: 100px;
            clear: both;
            text-align: center;
            margin-top: 300px;
            color: #567;
        }
    </style>
</head>
<body>
<?= $_js_body ?>
<?= $_body_html ?>
</body>
<?= $_js_foot ?>
<?= $_js_defer ?>
</html>