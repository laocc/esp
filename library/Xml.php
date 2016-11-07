<?php
namespace wbf\library;

/*

XML生成器：下面是三种示例：


    $val = [];
    $val[]['name'] = '科比';
    $val[]['sex'] = 35;
----------------------
<?xml version="1.0" encoding="UTF-8"?>
<root>
 <name><![CDATA[科比]]></name>
 <sex>35</sex>
</root>



    $val = [];
    $val['name'] = '科比';
    $val['sex'] = 35;
----------------------
<?xml version="1.0" encoding="UTF-8"?>
<root name="科比" sex="35"/>


    $arr = [];
    $arr['cto']['name'] = '老船长';
    $arr['cto']['time'] = '2016-1-1';
    $arr['cto'][] = ['sex' => '男'];
    $arr['cto'][] = ['age' => '40'];
    $arr['cto'][]['tel'] = '18801230456';
    $arr['cfo']['name'] = '科比';
    $arr['cfo']['time'] = '2016-2-1';
    $arr['cfo'][] = ['sex' => '男'];
    $arr['cfo'][] = ['age' => '35'];
    $arr['cfo'][]['tel'] = '18801230789';
----------------------
<?xml version="1.0" encoding="UTF-8"?>
<root>
 <cto name="老船长" time="2016-1-1">
  <sex><![CDATA[男]]></sex>
  <age>40</age>
  <tel>18801230456</tel>
 </cto>
 <cfo name="科比" time="2016-2-1">
  <sex><![CDATA[男]]></sex>
  <age>35</age>
  <tel>18801230789</tel>
 </cfo>
</root>



 */

class Xml
{
    const ROOT = 'xml';
    private $value;
    private $notes;

    public function __construct(array $value, $notes = null)
    {
        $this->value = $value;
        $this->notes = $notes;
    }

    /**
     * @param bool $output
     * @return \XMLWriter
     */
    private function adapter($output = true)
    {
        $xml = new \XMLWriter();
        if ($output) {
            $xml->openUri("php://output");
        } else {
            $xml->openMemory();
        }
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'utf-8');
        $xml->startElement($this->notes ?: self::ROOT);
        if (is_array($this->value)) {
            foreach ($this->value as $item => &$data) {
                $this->xml_notes($xml, $item, $data);
            }
        } else {
            $xml->startElement($this->notes);
            $xml->text($this->value);
            $xml->endElement();
        }
        $xml->endElement();
        $xml->endDocument();
        return $xml;
    }

    /**
     * 直接输出
     */
    public function flush()
    {
        $this->adapter(true)->flush(true);
    }

    public function display()
    {
        $this->adapter(true)->flush(true);
    }

    /**
     * 不输出，返回解析结果
     * @return string
     */
    public function render()
    {
        return $this->adapter(false)->outputMemory(true);
    }


    /**
     * 节点
     * @param $item
     * @param $data
     */
    private function xml_notes(\XMLWriter $xml, &$item, &$data)
    {
        if (is_int($item)) {
            if (is_array($data)) {
                foreach ($data as $key => &$row) {
                    if (is_array($row)) {
                        $this->xml_notes($xml, $key, $row);
                    } else {
                        $xml->startElement($key);//加：<name><![CDATA[wbf]]></name>中的标签名name
                        if (is_numeric($row)) {
                            $xml->text($row);//加：<age>35</age>
                        } else {
                            $xml->writeCdata($row);//加：<name><![CDATA[老船长]]></name>中的<![CDATA[老船长]]>部分
                        }
//                        $xml->writeComment('notes');//加<!--notes-->备注
                        $xml->endElement();
                    }
                }
            }
        } else {
            if (is_array($data)) {
                $xml->startElement($item);
                foreach ($data as $key => &$row) {
                    $this->xml_notes($xml, $key, $row);
                }
                $xml->endElement();
            } else {
                //加属性：<node value="title"/>
                $xml->writeAttribute($item, $this->xmlEncode($data));
            }
        }
    }

    /**
     * 数据转码
     * @param $tag
     * @param int $cdata
     * @return mixed|string
     */
    private function xmlEncode($tag)
    {
        return str_replace(["&", "<", ">", "'", '"'], ["&amp;", "&lt;", "&gt;", "&apos;", "&quot;"], $tag);
    }


}