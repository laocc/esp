<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="UTF-8">
    <title>Mysql</title>
    <style>
        input[type="button"] {
            width: 100px;
            height: 34px;
            float: left;
            padding: 0 0 0 4px;
            background: #F4F6F7;
            border: 1px #90A9B7 solid;
        }

        textarea {
            width: 100%;
            height: 500px;
            float: left;
            padding: 3px 0 0 4px;
            background: #F4F6F7;
            border: 1px #90A9B7 solid;
        }
    </style>
</head>

<body>
<?php include_once 'menu.php' ?>

<div style="float:left;width:49.5%">
    <div>
        <label for="fields">Field：</label>
        <textarea name="fields" id="fields"></textarea></div>
    <div>
        <input type="button" value="Empty" onclick="empty()"/>
    </div>
</div>
<div style="float:right;width:49.5%;">
    <div>
        <label for="sql">SQL：</label><textarea name="sql" id="sql"></textarea></div>
    <div>
        <input type="button" value="Create" onclick="make_sql();"/>
        <div style="float:left;margin-left:20px;">
            Engine：
            <input type="radio" value="Innodb" checked name="ENGINE" id="Innodb"><label for="Innodb">Innodb</label>
            <input type="radio" value="MyISAM" name="ENGINE" id="MyISAM"><label for="MyISAM">MyISAM</label>
        </div>
        <div style="float:left;margin-left:20px;">
            Charset：
            <input type="radio" value="utf8" checked name="CHARSET" id="utf8"><label for="utf8">utf8</label>
            <input type="radio" value="gb2312" name="CHARSET" id="gb2312"><label for="gb2312">gb2312</label>
        </div>
    </div>
</div>

<script language="javascript">

    String.prototype.trim = function () {
        var v = this.replace(/(^\s*)|(\s*$)/g, "");
        v = v.replace(/\s/g, " ").replace("   ", " ").replace("  ", " ").replace("   ", " ");
        v = v.replace("  ", " ").replace("'  ", "'").replace("' ", "'").replace("  ", " ");
        return v;
    };

    function check_radio(name) {
        var RO = document.getElementsByName(name);
        for (var i = 0; i < RO.length; i++) {
            if (RO[i].checked) return RO[i].value;
        }
    }

    var $ = function (d) {
        return document.getElementById(d);
    };

    function make_sql() {
        var fields = ($("fields").value).split("\n");
        if (fields.length < 2) {
            alert('empty');
            return;
        }
        var tab = fields[0].trim().split(' ');
        var id = fields[1].trim().split(' ');
        var KeyID = id[0];
        var ENGINE = check_radio("ENGINE");
        var CharSet = check_radio("CHARSET");
        var COMMENT = tab[0];
        if (tab.length >= 2) COMMENT = tab[1];
        var key = '';
        var sql = "DROP TABLE IF EXISTS `" + tab[0] + "`;\nCREATE TABLE IF NOT EXISTS `" + tab[0] + "` (\n";
        for (var i = 1; i < fields.length; i++) {
            if (fields[i]) {
                var tr = (fields[i]).trim().split(' ');
                if (tr[0] === 'key') {
                    sql += "\t" + (fields[i]).trim().substring(4) + "',\n";
                    key += ",\n\tkey " + tr[1] + ' (' + tr[1] + ')';
                } else {
                    sql += "\t" + (fields[i]).trim() + "',\n";
                }
            }
        }
        sql += "primary key(" + KeyID + ")" + key + ")\n";

        sql += "ENGINE=" + ENGINE + " DEFAULT CHARSET=" + CharSet + " COMMENT='" + COMMENT + "';\n";
        $("sql").value = sql + "\n\n\n\n\n\n";
    }

    function empty() {
        $("fields").value = $("sql").value = '';
    }

</script>
</body>

</html>
