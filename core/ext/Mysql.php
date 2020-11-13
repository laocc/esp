<?php
declare(strict_types=1);

namespace esp\core\ext;


trait Mysql
{

    /**
     * select * from INFORMATION_SCHEMA.Columns where table_name='tabAdmin' and table_schema='dbPayCenter';
     * 当前模型表对应的主键字段名，即自增字段
     * @return null
     *************************** 1. row ***************************
     * TABLE_CATALOG: def
     * TABLE_SCHEMA: dbPlatform
     * TABLE_NAME: tabAdmin
     * COLUMN_NAME: adminID
     * ORDINAL_POSITION: 1
     * COLUMN_DEFAULT: NULL
     * IS_NULLABLE: NO
     * DATA_TYPE: int
     * CHARACTER_MAXIMUM_LENGTH: NULL
     * CHARACTER_OCTET_LENGTH: NULL
     * NUMERIC_PRECISION: 10
     * NUMERIC_SCALE: 0
     * DATETIME_PRECISION: NULL
     * CHARACTER_SET_NAME: NULL
     * COLLATION_NAME: NULL
     * COLUMN_TYPE: int(10) unsigned
     * COLUMN_KEY: PRI
     * EXTRA: auto_increment
     * PRIVILEGES: select,insert,update,references
     * COLUMN_COMMENT: ID
     * GENERATION_EXPRESSION:
     */
    /**
     * @param string|null $table
     * @return null
     */
    final public function PRI(string $table = null)
    {
        if (isset($this->_id) and !empty($this->_id)) return $this->_id;
        if (!is_null($this->__pri)) return $this->__pri;
        if (is_null($table)) {
            $table = $this->table();
        }
        if (!$table) throw new EspError('Unable to get table name');
        $val = $this->Mysql()->table('INFORMATION_SCHEMA.Columns')
            ->select('COLUMN_NAME')
            ->where(['table_name' => $table, 'EXTRA' => 'auto_increment'])
            ->get()->row();
        if (empty($val)) return null;
        $this->__pri = $val['COLUMN_NAME'];
        return $this->__pri;
    }

    /**
     * 设置自增ID起始值
     * @param string $table
     * @param int $id
     * @return bool|\esp\core\db\ext\Result|null
     */
    final public function increment(string $table, int $id = 1)
    {
        //TRUNCATE TABLE dbAdmin;
        //alter table users AUTO_INCREMENT=10000;
        /**
         * @var $mysql \esp\core\db\Mysql
         */
        $mysql = $this->Mysql();
        return $mysql->query("alter table {$table} AUTO_INCREMENT={$id}");
    }

    /**
     * 刷新INFORMATION_SCHEMA里的表信息
     * @param string $table
     * @return bool
     */
    final public function analyze(string $table)
    {
        $mysql = $this->Mysql();
        /**
         * @var $mysql \esp\core\db\Mysql
         */
        $this->cache_set("{$mysql->dbName}.{$table}", '_field', []);
        $this->cache_set("{$mysql->dbName}.{$table}", '_title', []);
        $val = $mysql->query("analyze table `{$table}`")->rows();
        if (isset($val[1])) {
            return $val[0]['Msg_text'];
        } else {
            return true;
        }
    }

    /**
     * 读取表信息
     * @param null $table
     * @param bool $html
     * @return array|string
     */
    final public function desc($table = null, bool $html = false)
    {
        if (is_bool($table)) list($table, $html) = [null, $table];
        $table = $table ?: $this->table();
        if (!$table) throw new EspError('Unable to get table name');
        /**
         * @var $mysql \esp\core\db\Mysql
         */
        $mysql = $this->Mysql();
        $val = $mysql->table('INFORMATION_SCHEMA.Columns')
            ->select('column_name as name,COLUMN_DEFAULT as default,column_type as type,column_key as key,column_comment as comment')
            ->where(['table_schema' => $mysql->dbName, 'table_name' => $table])
            ->order('ORDINAL_POSITION', 'asc')
            ->get()->rows();
        if (empty($val)) throw new EspError("Table '{$table}' doesn't exist");
        if ($html) {
            $table = [];
            $table[] = '<table class="layui-table">';
            $table[] = "<thead><tr><td width='80'>Key</td><td width='150'>字段</td><td width='180'>类型</td><td width='80'>默认值</td><td>摘要</td></tr></thead>";
            foreach ($val as $rs) {
                $table[] = "<tr><td>{$rs['key']}</td><td>{$rs['name']}</td><td>{$rs['type']}</td><td>{$rs['default']}</td><td>{$rs['comment']}</td></tr>";
            }
            $table[] = '<table>';
            return implode("\n", $table);
        }
        return $val;
    }

    /**
     * @return array|null
     * *************************** 17. row ***************************
     * TABLE_CATALOG: def
     * TABLE_SCHEMA: dbPlatform
     * TABLE_NAME: tabWithdrawals
     * TABLE_TYPE: BASE TABLE
     * ENGINE: InnoDB
     * VERSION: 10
     * ROW_FORMAT: Dynamic
     * TABLE_ROWS: 16
     * AVG_ROW_LENGTH: 1024
     * DATA_LENGTH: 16384
     * MAX_DATA_LENGTH: 0
     * INDEX_LENGTH: 49152
     * DATA_FREE: 0
     * AUTO_INCREMENT: 17
     * CREATE_TIME: 2018-08-30 11:23:08
     * UPDATE_TIME: 2018-08-30 18:59:16
     * CHECK_TIME: NULL
     * TABLE_COLLATION: utf8_general_ci
     * CHECKSUM: NULL
     * CREATE_OPTIONS:
     * TABLE_COMMENT: 提现表
     *
     * select concat(round(sum(data_length/1024/1024),2),'MB') as data from tables where table_schema='home' and table_name='members';
     */
    final public function tables($html = false)
    {
        /**
         * @var $mysql \esp\core\db\Mysql
         */
        $mysql = $this->Mysql();
        $val = $mysql->table('INFORMATION_SCHEMA.TABLES')
            ->select("TABLE_NAME as name,DATA_LENGTH as data,TABLE_ROWS as rows,AUTO_INCREMENT as increment,TABLE_COMMENT as comment,UPDATE_TIME as time")
            ->where(['TABLE_SCHEMA' => $mysql->dbName])->get()->rows();
        if (empty($val)) return [];
        if ($html) {
            $table = [];
            $table[] = '<table class="layui-table">';
            $table[] = "<thead><tr><td>表名</td><td>行数</td><td>数据</td><td>自增ID</td><td>摘要</td><td>更新时间</td></tr></thead>";
            foreach ($val as $rs) {
                $rs['data'] = intval($rs['data'] / 1024 / 1024);
                $table[] = "<tr><td>{$rs['name']}</td><td>{$rs['rows']}</td><td>{$rs['data']}MB</td><td>{$rs['increment']}</td><td>{$rs['comment']}</td><td>{$rs['time']}</td></tr>";
            }
            $table[] = '<table>';
            return implode("\n", $table);
        }
        return $val;
    }

    /**
     * 列出表字段
     * @param string|null $table
     * @return mixed
     * @throws EspError
     */
    final public function fields(string $table = null)
    {
        $table = $table ?: $this->table();
        if (!$table) throw new EspError('Unable to get table name');
        /**
         * @var $mysql \esp\core\db\Mysql
         */
        $mysql = $this->Mysql();
        $val = $mysql->table('INFORMATION_SCHEMA.Columns')
            ->where(['table_schema' => $mysql->dbName, 'table_name' => $table])
            ->get()->rows();
        if (empty($val)) throw new EspError("Table '{$table}' doesn't exist");
        return $val;
    }

    /**
     * 根据数据库中的表，创建相应的模型
     * @param string $path
     * @param string $baseModel
     * @return string|array
     */
    final public function createModel(string $path, string $baseModel = 'BaseModel')
    {
        $root = \esp\helper\root($path);
        if (!is_dir($root)) return "请先创建[{$root}]目录";

        $mysql = $this->Mysql();
        /**
         * @var $mysql \esp\core\db\Mysql
         */
        $val = $mysql->table('INFORMATION_SCHEMA.TABLES')
            ->select('TABLE_NAME')
            ->where(['TABLE_SCHEMA' => $mysql->dbName])->get()->rows();
        $tables = [];
        foreach ($val as $table) {
            $tab = ucfirst(substr($table['TABLE_NAME'], 3)) . 'Model';
            if (!is_readable("{$root}/{$tab}.php")) {
                $keyID = $mysql->table('INFORMATION_SCHEMA.Columns')
                    ->select('COLUMN_NAME')
                    ->where(['table_schema' => $mysql->dbName, 'table_name' => $table['TABLE_NAME'], 'EXTRA' => 'auto_increment'])
                    ->get()->row();
                $namespace = str_replace('/', '\\', trim($path, '/'));
                $php = <<<PHP
<?php

namespace {$namespace};

class {$tab} extends {$baseModel} 
{
    public \$_table = '{$table['TABLE_NAME']}';
    public \$_id = '{$keyID['COLUMN_NAME']}';
    
}
PHP;
                $tables[] = $tab;
                file_put_contents("{$root}/{$tab}.php", $php);
            }
        }
        return $tables;
    }

    /**
     * 列出所有字段的名称
     * @return array
     */
    final public function title()
    {
        $mysql = $this->Mysql();
        /**
         * @var $mysql \esp\core\db\Mysql
         */
        $table = $this->table();
        $data = $this->cache_get("{$mysql->dbName}.{$table}", '_title');
        if (!empty($data)) return $data;

        if (!$table) throw new EspError('Unable to get table name');
        $val = $mysql->table('INFORMATION_SCHEMA.Columns')
            ->select('COLUMN_NAME as field,COLUMN_COMMENT as title')
            ->where(['table_name' => $table])->get()->rows();
        if (empty($val)) throw new EspError("Table '{$table}' doesn't exist");
        $this->cache_set("{$mysql->dbName}.{$table}", '_title', $val);
        return $val;
    }

    /**
     * 新增行时，填充字段
     * @param string $dbName
     * @param string $table
     * @param array $data
     * @return array|mixed
     */
    final private function _FillField(string $dbName, string $table, array $data)
    {
        $field = $this->cache_get("{$dbName}.{$table}", '_field');
        if (empty($field)) {
            $field = $this->fields($table);
            $s = $this->cache_set("{$dbName}.{$table}", '_field', $field);
        }
        if (isset($data[0])) {
            $rowData = $data[0];
        } else {
            $rowData = $data;
        }

        foreach ($field as $i => $rs) {
            if (strtolower($rs['EXTRA']) === 'auto_increment') continue;//自增字段
            if (isset($rowData[$rs['COLUMN_NAME']])) continue;//传入数据中，字段有值
            $string = array('CHAR', 'VARCHAR', 'TINYBLOB', 'TINYTEXT', 'BLOB', 'TEXT', 'MEDIUMBLOB', 'MEDIUMTEXT', 'LONGBLOB', 'LONGTEXT');
            $number = array('INT', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'INTEGER', 'BIGINT');
            $float = array('FLOAT', 'DOUBLE', 'DECIMAL');
            if (in_array(strtoupper($rs['DATA_TYPE']), $number)) $rowData[$rs['COLUMN_NAME']] = intval($rs['COLUMN_DEFAULT']);//数值型
            elseif (in_array(strtoupper($rs['DATA_TYPE']), $float)) $rowData[$rs['COLUMN_NAME']] = floatval($rs['COLUMN_DEFAULT']);//浮点型
            elseif (in_array(strtoupper($rs['DATA_TYPE']), $string)) $rowData[$rs['COLUMN_NAME']] = strval($rs['COLUMN_DEFAULT']);//文本型
            else $data[$rs['COLUMN_NAME']] = null;//其他类型，均用null填充，主要是日期和时间类型
        }
        if (isset($data[0])) {
            foreach ($data as $i => $d) {
                $data[$i] = $d + $rowData;
            }
            return $data;
        } else {
            return $rowData;
        }
    }

    final private function _AllField(string $dbName, string $table, array $data)
    {
        $field = $this->cache_get("{$dbName}.{$table}", '_field');
        if (empty($field)) {
            $field = $this->fields($table);
            $this->cache_set("{$dbName}.{$table}", '_field', $field);
        }
        if (isset($data[0])) {
            $rowData = $data[0];
        } else {
            $rowData = $data;
        }

        foreach ($field as $i => $rs) {
            if (strtolower($rs['EXTRA']) === 'auto_increment') continue;//自增字段
            if (isset($rowData[$rs['COLUMN_NAME']])) continue;//传入数据中，字段有值
            $string = array('CHAR', 'VARCHAR', 'TINYBLOB', 'TINYTEXT', 'BLOB', 'TEXT', 'MEDIUMBLOB', 'MEDIUMTEXT', 'LONGBLOB', 'LONGTEXT');
            $number = array('INT', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'INTEGER', 'BIGINT');
            $float = array('FLOAT', 'DOUBLE', 'DECIMAL');
            if (in_array(strtoupper($rs['DATA_TYPE']), $number)) $rowData[$rs['COLUMN_NAME']] = intval($rs['COLUMN_DEFAULT']);//数值型
            elseif (in_array(strtoupper($rs['DATA_TYPE']), $float)) $rowData[$rs['COLUMN_NAME']] = floatval($rs['COLUMN_DEFAULT']);//浮点型
            elseif (in_array(strtoupper($rs['DATA_TYPE']), $string)) $rowData[$rs['COLUMN_NAME']] = strval($rs['COLUMN_DEFAULT']);//文本型
            else $data[$rs['COLUMN_NAME']] = null;//其他类型，均用null填充，主要是日期和时间类型
        }
        if (isset($data[0])) {
            foreach ($data as $i => $d) {
                $data[$i] = $d + $rowData;
            }
            return $data;
        } else {
            return $rowData;
        }
    }

}