<div id="body" class="fixedForm"
     xmlns:v-slot="http://www.w3.org/1999/XSL/Transform"
     xmlns:v-html="http://www.w3.org/1999/XSL/Transform">

    <el-form :inline="true" class="searchForm" @submit="loadBodyData" onsubmit="return !1;">
        <el-form-item>
            <el-radio-group v-model="bodyForm.type" size="small" @change="loadBodyData">
                <el-radio-button label="">今天</el-radio-button>
                <el-radio-button label="1">昨天</el-radio-button>
                <el-radio-button label="2">前天</el-radio-button>
            </el-radio-group>
        </el-form-item>

        <el-form-item>
            <db-input v-model="bodyForm.key" @enter="doInputEnter" placeholder="搜索关键词" clearable></db-input>
        </el-form-item>
        <el-form-item>
            <db-button class="btn primary small" @click="loadBodyData">查询</db-button>
        </el-form-item>
    </el-form>


    <table class="dbTable">
        <thead>
        <tr>
            <th width="90">Action</th>
            <th v-for="h in 24" style="font-size: 12px;">{{h-1}}-{{h}}</th>
        </tr>
        </thead>
        <tbody>
        <template v-for="(day,vt) in bodyData">
            <tr>
                <td colspan="25" style="text-align: center;background: #2d5dc7;color: #fff;">{{vt}}</td>
            </tr>
            <tr v-for="act in day.action">
                <td>{{act}}</td>
                <td v-for="h in 24">{{day.data[h]?day.data[h][act]:''}}</td>
            </tr>

        </template>

        </tbody>
    </table>
    <db-open v-model="openOption"></db-open>

</div>
<?php

/**
 * 下面 /debug/counter 指向的控制器示例：
 */


function counterAjax()
{
    $time = _TIME;

    $data = \esp\core\Debug::class()->counter($time);

    return $data;
}


?>
<script>

    let vm = new Vue({
        el: '#body',
        mixins: [expBodyMixin],
        data() {
            return {
                bodyDataApi: '/debug/counter',
            }
        }
    });

    function callback(v) {
        if (v.success) vm.loadBodyData();
    }

</script>