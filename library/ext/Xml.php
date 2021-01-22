<?php

namespace esp\library\ext;

use esp\error\EspError;

final class Xml
{
    private $value;
    private $notes;


    /**
     * 将数组转换成XML格式
     * @param string $root
     * @param array $array
     * @return string
     */
    /**
     * @param string $root
     * @param array $array
     * @return string
     */
    public static function encode(string $root, array $array)
    {
        return (new Xml($array, $root))->render();
    }

    /**
     * XML解析成数组或对象
     * @param string $str
     * @param bool $toArray
     * @return mixed|null
     */
    public static function decode(string $str, bool $toArray = true)
    {
        if (!$str) return null;
        $xml_parser = xml_parser_create();
        if (!xml_parse($xml_parser, $str, true)) {
            xml_parser_free($xml_parser);
            return null;
        }
        return json_decode(json_encode(@simplexml_load_string($str, "SimpleXMLElement", LIBXML_NOCDATA)), $toArray);
    }


    /**
     * Xml constructor.
     * @param $value
     * @param string $notes
     */
    public function __construct($value, $notes = 'xml')
    {
        if (is_array($notes) and is_string($value)) list($value, $notes) = [$notes, $value];
        if (!is_array($value)) throw new EspError('XML数据要求为数组格式');
        if (!preg_match('/^[a-z]+$/i', $notes)) throw new EspError('XML根节点名称只能是字母组成的字符串');
        $this->value = $value;
        $this->notes = $notes;
    }

    /**
     * @param bool $output
     * @return \XMLWriter
     */
    private function adapter(bool $output = true)
    {
        $xml = new \XMLWriter();
        if ($output) {
            $xml->openUri("php://output");
        } else {
            $xml->openMemory();
        }
        $xml->setIndent(true);
        if (isset($this->value['_encoding'])) {
            $encoding = $this->value['_encoding'];
            unset($this->value['_encoding']);
        } else {
            $encoding = 'utf-8';
        }
        if (isset($this->value['_version'])) {
            $version = $this->value['_version'];
            unset($this->value['_version']);
        } else {
            $version = '1.0';
        }
        $xml->startDocument($version, $encoding);
        if (isset($this->value['_css'])) {
            $xml->writePi('xml-stylesheet', 'type="text/css" href="' . $this->value['_css'] . '"');
            unset($this->value['_css']);
        }
        $xml->startElement($this->notes);
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
    public function render(bool $outHead = true)
    {
        $xml = $this->adapter(false)->outputMemory(true);
        if (!$outHead) {
            $xml = substr($xml, strpos($xml, '?>') + 3);
        }
        return $xml;
    }

    private function append(\XMLWriter $xml, $val)
    {
        if (is_numeric($val)) {
            $xml->text($val);//加：<age>35</age>
        } else {
            $xml->writeCdata($val);//加：<name><![CDATA[老船长]]></name>中的CDATA部分
        }
    }

    /**
     * 查询子节点是否列表式的子项
     * @param $arr
     * @return int
     * 0：数字
     * 1：文本
     * 2：列表式
     * 3：其他
     */
    private function childStyle($arr)
    {
        if (is_numeric($arr)) return 0;
        if (is_string($arr)) return 1;
        foreach ($arr as $i => $v) {
            return is_int($i) ? 2 : 3;
        }
        return false;
    }

    /**
     * 节点
     * @param $item
     * @param $data
     */
    private function xml_notes(\XMLWriter $xml, $item, $data)
    {
        if (is_string($item) and !is_array($data)) {//直接是终节点
            $xml->startElement($item);
            $this->append($xml, $data);
            $xml->endElement();
        } elseif (is_array($data)) {
            if ($this->childStyle($data) === 2) {//子项是列表式
                foreach ($data as $key => $row) {
                    $this->xml_notes($xml, $item, $row);
                }
            } else {
                $xml->startElement($item);
                foreach ($data as $key => $row) {
                    if (is_string($key)) {
                        $this->xml_notes($xml, $key, $row);
                    } else {
//                        $xml->writeAttribute('val', $this->xmlEncode($row));
                    }
                }
                $xml->endElement();
            }
        }
    }

    /**
     * •XMLWriter::text — Write text    <age>35</age>
     * •XMLWriter::writeAttributeNS — Write full namespaced attribute 加属性：<node value="title"/>
     * •XMLWriter::writeAttribute — Write full attribute 加属性：<node value="title"/>
     * •XMLWriter::writeCData — Write full CDATA tag//加：<name><![CDATA[老船长]]></name>中的CDATA部分
     * •XMLWriter::writeComment — Write full comment tag //加<!--notes-->备注
     * •XMLWriter::writeDTDAttlist — Write full DTD AttList tag
     * •XMLWriter::writeDTDElement — Write full DTD element tag
     * •XMLWriter::writeDTDEntity — Write full DTD Entity tag
     * •XMLWriter::writeDTD — Write full DTD tag
     * •XMLWriter::writeElementNS — Write full namespaced element tag $xml->writeElementNS('name','why','abc');写出： <name:why xmlns:name="abc"/>
     * •XMLWriter::writeElement — Write full element tag 关闭一个标签：<why/>
     * •XMLWriter::writePI — Writes a PI $xml->writePi('xml-stylesheet', 'type="text/css" href="' . $this->value['_css'] . '"');
     * •XMLWriter::writeRaw — Write a raw XML text 给每个节点内容前加： <artUrl>why<![CDATA[fazo]]></artUrl>里的why
     */

    /**
     * 数据转码
     * @param $tag
     * @param int $cdata
     * @return mixed|string
     */
    private function encodeTags($tag)
    {
        return str_replace(["&", "<", ">", "'", '"'], ["&amp;", "&lt;", "&gt;", "&apos;", "&quot;"], $tag);
    }


}