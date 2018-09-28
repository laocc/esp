<table id="demo" lay-filter="test"></table>

<script>
    layui.use('table', function () {
        var table = layui.table;

        //第一个实例
        table.render({
            elem: '#demo',
            limit: <?=$page_size?>,
            height: 600,
            url: '/mysql/tab1',
            page: true, //开启分页
            cols: [[ //表头
                {field: 'testID', title: 'ID', width: 100, sort: true, fixed: 'left'},
                {field: 'testTitle', title: 'Title', width: 200},
                {field: 'testBody', title: 'Body', width: 200, sort: true},
                {field: 'testTime', title: 'Time', width: 180, sort: true}
            ]]
        });

    });
</script>

