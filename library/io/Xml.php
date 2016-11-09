<?php
namespace io;


class Xml
{
    const ROOT = 'xml';
    private $value;
    private $notes;

    public function __construct($value, $notes = null)
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
    private function xml_notes(\XMLWriter &$xml, &$item, &$data)
    {
        if (is_int($item)) {
            if (is_array($data)) {
                foreach ($data as $key => &$row) {
                    if (is_array($row)) {
                        $this->xml_notes($xml, $key, $row);
                    } else {
                        $xml->startElement($key);
                        $xml->writeCdata($row);
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