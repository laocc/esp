
<div style="padding:0 1em;">
    <table class="layui-table" lay-even lay-filter="layuiTable">
        <thead>
        <tr>
            <th lay-data="{field:'testID', width:100}">ID</th>
            <th lay-data="{field:'testTitle', width:200}">Title</th>
            <th lay-data="{field:'testTime', width:180,sort:true}">Time</th>
            <th lay-data="{field:'testBody'}">Body</th>
        </tr>
        </thead>
        <tbody>
        <?php
        function ttd($n = 10, $tr = '')
        {
            return "<tr {$tr}>" . str_repeat('<td>%s</td>', $n) . "</tr>\n";
        }

        $tr = ttd(4);

        foreach ($data as $i => $rs) {
            $bar = '';
            echo sprintf($tr,
                $rs['testID'],
                $rs['testTitle'],
                date('Y-m-d H:i:s', $rs['testTime']),
                $rs['testBody']
            );
        }
        ?>
        </tbody>
    </table>
    <table class="layui-table">
        <tfoot>
        <tr>
            <td style="padding:0;">
                <?= $page; ?>
            </td>
        </tr>
        </tfoot>
    </table>
</div>
