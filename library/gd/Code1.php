<?php

namespace esp\library\gd;

use esp\error\EspError;
use esp\library\gd\ext\Gd;

/**
 * 条形码
 * Class Code1
 * @package tools
 *
 * $code = Array();
 * $code['value'] = $val;        //条码内容
 * $code['font'] = _ROOT . 'font/arial.ttf';//字体，若不指定，则用PHP默认字体
 * $code['size'] = 20;         //字体大小
 * $code['label'] = true;      //是否需要条码下面标签
 * $code['pixel'] = 5;         //分辨率即每个点显示的像素，建议3-5
 * $code['height'] = 20;       //条码部分高，实际像素为此值乘pixel
 * $code['style'] = null;      //条码格式，可选：A,B,C,或null，若为null则等同于C
 * Code1::create($code);
 */
class Code1
{
    /**
     * @param $option
     * @return mixed
     */
    public static function create($option)
    {
        if (!is_array($option)) {
            $option = ['value' => $option];
        }
        $code = Array();
        $code['code'] = microtime(true);        //条码内容
        $code['font'] = null;       //字体，若不指定，则用PHP默认字体
        $code['size'] = 10;         //字体大小
        $code['split'] = 4;         //条码值分组，每组字符个数，=0不分，=null不显示条码值
        $code['pixel'] = 3;         //分辨率即每个点显示的像素，建议3-5
        $code['height'] = 20;       //条码部分高，实际像素为此值乘pixel
        $code['style'] = null;      //条码格式，可选：A,B,C,或null，若为null则等同于C，这基本不需要指定，非C的条码，还不知道用在什么地方
        $code['root'] = getcwd();    //保存文件目录，不含在URL中部分
        $code['path'] = 'code1/';   //含在URL部分
        $code['save'] = 0;          //0：只显示，1：只保存，2：即显示也保存
        $code['filename'] = null;      //不带此参，或此参为false值，则随机产生

        $option += $code;

        $option['code'] = strval($option['code']);
        $option['root'] = rtrim($option['root'], '/');
        $option['path'] = '/' . trim($option['path'], '/') . '/';

        if (!preg_match('/^[\x20\w\!\@\#\$\%\^\&\*\(\)\_\+\`\-\=\[\]\{\}\;\'\\\:\"\|\,\.\/\<\>\?]+$/', $option['code'])) {
            throw new EspError("条形码只能是英文、数字及半角符号组成");
        }

        if (!!$option['split']) {
            $option['label'] = '* ' . implode(' ', str_split($option['code'], intval($option['split']))) . ' *';
        } elseif ($option['split'] === null) {
            $option['label'] = null;
        } else {
            $option['label'] = $option['code'];
        }

        $font = (!!$option['font']) ?
            (new BCG_FontFile($option['font'], intval($option['size']))) :
            (new BCG_FontPhp($option['size']));

        $color = new BCG_Color(0, 0, 0);
        $background = new BCG_Color(255, 255, 255);

        $file = Gd::getFileName($option['save'], $option['root'], $option['path'], $option['filename'], 'png');

        $Obj = new BCG_code128();
        $Obj->setLabel($option['label']);
        $Obj->setStart($option['style']);
        $Obj->setThickness($option['height']);
        $Obj->setScale($option['pixel']);
        $Obj->setBackgroundColor($background);
        $Obj->setForegroundColor($color);
        $Obj->setFont($font);
        $Obj->parse($option['code']);

        $size = $Obj->getDimension(0, 0);
        $width = max(1, $size[0]);
        $height = max(1, $size[1]);
        $im = imagecreatetruecolor($width, $height);
        imagefilledrectangle($im, 0, 0, $width - 1, $height - 1, $background->allocate($im));
        $Obj->draw($im);

        $option = [
            'save' => $option["save"],//0：只显示，1：只保存，2：即显示也保存
            'filename' => $file['filename'],
            'type' => IMAGETYPE_PNG,//文件类型
        ];

        Gd::draw($im, $option);
        return $file;
    }
}

final class BCG_Color
{
    protected $r, $g, $b;

    public function __construct()
    {
        $args = func_get_args();
        $c = count($args);
        if ($c === 3) {
            $this->r = intval($args[0]);
            $this->g = intval($args[1]);
            $this->b = intval($args[2]);
        } elseif ($c === 1) {
            list($this->r, $this->g, $this->b) = Gd::getRGBColor($args[0]);

        } else {
            $this->r = $this->g = $this->b = 0;
        }
    }

    public function allocate(&$im)
    {
        return imagecolorallocate($im, $this->r, $this->g, $this->b);
    }
}

final class BCG_Label
{
    const POSITION_TOP = 0;
    const POSITION_RIGHT = 1;
    const POSITION_BOTTOM = 2;
    const POSITION_LEFT = 3;

    const ALIGN_LEFT = 0;
    const ALIGN_TOP = 0;
    const ALIGN_CENTER = 1;
    const ALIGN_RIGHT = 2;
    const ALIGN_BOTTOM = 2;

    private $font;
    private $text;
    private $position;
    private $alignment;
    private $offset;
    private $spacing;
    private $rotationAngle;
    private $backgroundColor;

    /**
     * Constructor.
     *
     * @param string $text
     * @param BCG_Font $font
     * @param int $position
     * @param int $alignment
     */
    public function __construct($text = '', $font = null, $position = self::POSITION_BOTTOM, $alignment = self::ALIGN_CENTER)
    {
        $this->font = $font === null ? new BCG_FontPhp(5) : $font;
        $this->setFont($this->font);
        $this->setText($text);
        $this->setPosition($position);
        $this->setAlignment($alignment);
        $this->setSpacing(4);
        $this->setOffset(0);
        $this->setRotationAngle(0);
        $this->setBackgroundColor(new BCG_Color('white'));
    }

    /**
     * Gets the text.
     *
     * @return string
     */
    public function getText()
    {
        return $this->font->getText();
    }

    /**
     * Sets the text.
     *
     * @param string $text
     */
    public function setText($text)
    {
        $this->text = $text;
        $this->font->setText($this->text);
    }

    /**
     * Sets the font.
     *
     * @param BCG_Font $font
     */
    public function setFont($font = null)
    {
        if ($font === null) {
            throw new EspError('Font cannot be null.');
        }

        $this->font = clone $font;
        $this->font->setText($this->text);
        $this->font->setRotationAngle($this->rotationAngle);
        $this->font->setBackgroundColor($this->backgroundColor);
    }

    /**
     * Gets the text position for drawing.
     *
     * @return int
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * Sets the text position for drawing.
     *
     * @param int $position
     */
    public function setPosition($position)
    {
        $position = intval($position);
        if ($position !== self::POSITION_TOP && $position !== self::POSITION_RIGHT && $position !== self::POSITION_BOTTOM && $position !== self::POSITION_LEFT) {
            throw new EspError('The text position must be one of a valid constant.');
        }

        $this->position = $position;
    }

    /**
     * Gets the text alignment for drawing.
     *
     * @return int
     */
    public function getAlignment()
    {
        return $this->alignment;
    }

    /**
     * Sets the text alignment for drawing.
     *
     * @param int $alignment
     */
    public function setAlignment($alignment)
    {
        $alignment = intval($alignment);
        if ($alignment !== self::ALIGN_LEFT && $alignment !== self::ALIGN_TOP && $alignment !== self::ALIGN_CENTER && $alignment !== self::ALIGN_RIGHT && $alignment !== self::ALIGN_BOTTOM) {
            throw new EspError('The text alignment must be one of a valid constant.');
        }

        $this->alignment = $alignment;
    }

    /**
     * Gets the offset.
     *
     * @return int
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * Sets the offset.
     *
     * @param int $offset
     */
    public function setOffset($offset)
    {
        $this->offset = intval($offset);
    }

    /**
     * Gets the spacing.
     *
     * @return int
     */
    public function getSpacing()
    {
        return $this->spacing;
    }

    /**
     * Sets the spacing.
     *
     * @param int $spacing
     */
    public function setSpacing($spacing)
    {
        $this->spacing = max(0, intval($spacing));
    }

    /**
     * Gets the rotation angle in degree.
     *
     * @return float
     */
    public function getRotationAngle()
    {
        return $this->font->getRotationAngle();
    }

    /**
     * Sets the rotation angle in degree.
     *
     * @param int $rotationAngle
     */
    public function setRotationAngle($rotationAngle)
    {
        $this->rotationAngle = (int)$rotationAngle;
        $this->font->setRotationAngle($this->rotationAngle);
    }

    /**
     * Gets the background color in case of rotation.
     *
     * @return BCG_Color
     */
    public function getBackgroundColor($backgroundColor)
    {
        return $this->font->getBackgroundColor();
    }

    /**
     * Sets the background color in case of rotation.
     *
     * @param BCG_Color $backgroundColor
     */
    public function setBackgroundColor($backgroundColor)
    {
        $this->backgroundColor = $backgroundColor;
        $this->font->setBackgroundColor($this->backgroundColor);
    }

    /**
     * Gets the dimension taken by the label, including the spacing and offset.
     * [0]: width
     * [1]: height
     *
     * @return int[]
     */
    public function getDimension()
    {
        $dimension = $this->font->getDimension();
        $w = $dimension[0];
        $h = $dimension[1];

        if ($this->position === self::POSITION_TOP || $this->position === self::POSITION_BOTTOM) {
            $h += $this->spacing;
            $w += max(0, $this->offset);
        } else {
            $w += $this->spacing;
            $h += max(0, $this->offset);
        }

        return array($w, $h);
    }

    /**
     * Draws the text.
     * The coordinate passed are the positions of the barcode.
     * $x1 and $y1 represent the top left corner.
     * $x2 and $y2 represent the bottom right corner.
     *
     * @param resource $im
     * @param int $x1
     * @param int $y1
     * @param int $x2
     * @param int $y2
     */
    public function draw($im, $x1, $y1, $x2, $y2)
    {
        $x = 0;
        $y = 0;

        $fontDimension = $this->font->getDimension();

        if ($this->position === self::POSITION_TOP || $this->position === self::POSITION_BOTTOM) {
            if ($this->position === self::POSITION_TOP) {
                $y = $y1 - $this->spacing - $fontDimension[1];
            } elseif ($this->position === self::POSITION_BOTTOM) {
                $y = $y2 + $this->spacing;
            }

            if ($this->alignment === self::ALIGN_CENTER) {
                $x = ($x2 - $x1) / 2 + $x1 - $fontDimension[0] / 2 + $this->offset;
            } elseif ($this->alignment === self::ALIGN_LEFT) {
                $x = $x1 + $this->offset;
            } else {
                $x = $x2 + $this->offset - $fontDimension[0];
            }
        } else {
            if ($this->position === self::POSITION_LEFT) {
                $x = $x1 - $this->spacing - $fontDimension[0];
            } elseif ($this->position === self::POSITION_RIGHT) {
                $x = $x2 + $this->spacing;
            }

            if ($this->alignment === self::ALIGN_CENTER) {
                $y = ($y2 - $y1) / 2 + $y1 - $fontDimension[1] / 2 + $this->offset;
            } elseif ($this->alignment === self::ALIGN_TOP) {
                $y = $y1 + $this->offset;
            } else {
                $y = $y2 + $this->offset - $fontDimension[1];
            }
        }

        $this->font->setText($this->text);
        $this->font->draw($im, 0, $x, $y);
    }
}

interface BCG_Font
{
    public function getText();

    public function setText($text);

    public function getRotationAngle();

    public function setRotationAngle($rotationDegree);

    public function getBackgroundColor();

    public function setBackgroundColor($backgroundColor);

    public function getDimension();

    public function draw($im, $color, $x, $y);
}

final class BCG_FontPhp implements BCG_Font
{
    private $font;
    private $text;
    private $rotationAngle;
    private $backgroundColor;

    /**
     * Constructor.
     *
     * @param int $font
     */
    public function __construct($font)
    {
        $this->font = max(0, intval($font));
        $this->setRotationAngle(0);
        $this->setBackgroundColor(new BCG_Color('white'));
    }

    /**
     * Gets the text associated to the font.
     *
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * Sets the text associated to the font.
     * @param string
     */
    public function setText($text)
    {
        $this->text = $text;
    }

    /**
     * Gets the rotation in degree.
     *
     * @return int
     */
    public function getRotationAngle()
    {
        return $this->rotationAngle;
    }

    /**
     * Sets the rotation in degree.
     *
     * @param int
     */
    public function setRotationAngle($rotationAngle)
    {
        $this->rotationAngle = (int)$rotationAngle;
        if ($this->rotationAngle !== 90 && $this->rotationAngle !== 180 && $this->rotationAngle !== 270) {
            $this->rotationAngle = 0;
        }
    }

    /**
     * Gets the background color.
     *
     * @return BCG_Color
     */
    public function getBackgroundColor()
    {
        return $this->backgroundColor;
    }

    /**
     * Sets the background color.
     *
     * @param BCG_Color $backgroundColor
     */
    public function setBackgroundColor($backgroundColor)
    {
        $this->backgroundColor = $backgroundColor;
    }

    /**
     * Returns the width and height that the text takes to be written.
     *
     * @return int[]
     */
    public function getDimension()
    {
        $w = imagefontwidth($this->font) * strlen($this->text);
        $h = imagefontheight($this->font);

        if ($this->rotationAngle === 90 || $this->rotationAngle === 270) {
            return array($h, $w);
        } else {
            return array($w, $h);
        }
    }

    /**
     * Draws the text on the image at a specific position.
     * $x and $y represent the left bottom corner.
     *
     * @param resource $im
     * @param int $color
     * @param int $x
     * @param int $y
     */
    public function draw($im, $color, $x, $y)
    {
        if ($this->rotationAngle !== 0) {
            if (!function_exists('imagerotate')) {
                throw new EspError('The method imagerotate doesn\'t exist on your server. Do not use any rotation.');
            }

            $w = imagefontwidth($this->font) * strlen($this->text);
            $h = imagefontheight($this->font);
            $gd = imagecreatetruecolor($w, $h);
            imagefilledrectangle($gd, 0, 0, $w - 1, $h - 1, $this->backgroundColor->allocate($gd));
            imagestring($gd, $this->font, 0, 0, $this->text, $color);
            $gd = imagerotate($gd, $this->rotationAngle, 0);
            imagecopy($im, $gd, $x, $y, 0, 0, imagesx($gd), imagesy($gd));
        } else {
            imagestring($im, $this->font, $x, $y, $this->text, $color);
        }
    }
}

final class BCG_FontFile implements BCG_Font
{
    const PHP_BOX_FIX = 0;
    private $path;
    private $size;
    private $text = '';
    private $rotationAngle = 0;
    private $box;
    private $underlineX;
    private $underlineY;

    /**
     * Constructor.
     *
     * @param string $fontPath path to the file
     * @param int $size size in point
     */
    public function __construct($fontPath, $size)
    {
        $this->path = $fontPath;
        $this->size = $size;
        $this->setRotationAngle(0);
    }

    /**
     * Gets the text associated to the font.
     *
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

    public function setText($text)
    {
        $this->text = $text;
        $this->rebuildBox();
    }

    /**
     * Gets the rotation in degree.
     *
     * @return int
     */
    public function getRotationAngle()
    {
        return $this->rotationAngle;
    }

    /**
     * Sets the rotation in degree.
     *
     * @param int
     */
    public function setRotationAngle($rotationAngle)
    {
        $this->rotationAngle = (int)$rotationAngle;
        if ($this->rotationAngle !== 90 && $this->rotationAngle !== 180 && $this->rotationAngle !== 270) {
            $this->rotationAngle = 0;
        }

        $this->rebuildBox();
    }

    /**
     * Gets the background color.
     *
     * @return BCG_Color
     */
    public function getBackgroundColor()
    {
    }

    /**
     * Sets the background color.
     *
     * @param BCG_Color $backgroundColor
     */
    public function setBackgroundColor($backgroundColor)
    {
    }

    /**
     * Returns the width and height that the text takes to be written.
     *
     * @return int[]
     */
    public function getDimension()
    {
        $w = 0.0;
        $h = 0.0;

        if ($this->box !== null) {
            $minX = min(array($this->box[0], $this->box[2], $this->box[4], $this->box[6]));
            $maxX = max(array($this->box[0], $this->box[2], $this->box[4], $this->box[6]));
            $minY = min(array($this->box[1], $this->box[3], $this->box[5], $this->box[7]));
            $maxY = max(array($this->box[1], $this->box[3], $this->box[5], $this->box[7]));

            $w = $maxX - $minX;
            $h = $maxY - $minY;
        }

        if ($this->rotationAngle === 90 || $this->rotationAngle === 270) {
            return array($h + self::PHP_BOX_FIX, $w);
        } else {
            return array($w + self::PHP_BOX_FIX, $h);
        }
    }

    /**
     * Draws the text on the image at a specific position.
     * $x and $y represent the left bottom corner.
     *
     * @param resource $im
     * @param int $color
     * @param int $x
     * @param int $y
     */
    public function draw($im, $color, $x, $y)
    {
        $drawingPosition = $this->getDrawingPosition($x, $y);
        imagettftext($im, $this->size, $this->rotationAngle, $drawingPosition[0], $drawingPosition[1], $color, $this->path, $this->text);
    }

    private function getDrawingPosition($x, $y)
    {
        $dimension = $this->getDimension();
        if ($this->rotationAngle === 0) {
            $y += abs(min($this->box[5], $this->box[7]));
        } elseif ($this->rotationAngle === 90) {
            $x += abs(min($this->box[5], $this->box[7]));
            $y += $dimension[1];
        } elseif ($this->rotationAngle === 180) {
            $x += $dimension[0];
            $y += abs(max($this->box[1], $this->box[3]));
        } elseif ($this->rotationAngle === 270) {
            $x += abs(max($this->box[1], $this->box[3]));
        }

        return array($x, $y);
    }

    private function rebuildBox()
    {
        $gd = imagecreate(1, 1);
//        echo $this->path;
        $this->box = imagettftext($gd, $this->size, 0, 0, 0, 0, $this->path, $this->text);

        $this->underlineX = abs($this->box[0]);
        $this->underlineY = abs($this->box[1]);

        if ($this->rotationAngle === 90 || $this->rotationAngle === 270) {
            $this->underlineX ^= $this->underlineY ^= $this->underlineX ^= $this->underlineY;
        }
    }
}

abstract class BCG_Barcode
{
    const COLOR_BG = 0;
    const COLOR_FG = 1;

    protected $colorFg, $colorBg;        // Color Foreground, Barckground
    protected $scale;                    // Scale of the graphic, default: 1
    protected $offsetX, $offsetY;        // Position where to start the drawing
    protected $labels = array();        // Array of BCG_Label
    protected $pushLabel = array(0, 0);    // Push for the label, left and top

    /**
     * Constructor.
     */
    protected function __construct()
    {
        $this->setOffsetX(0);
        $this->setOffsetY(0);
        $this->setForegroundColor(0x000000);
        $this->setBackgroundColor(0xffffff);
        $this->setScale(1);
    }

    /**
     * Parses the text before displaying it.
     *
     * @param mixed $text
     */
    public function parse($text)
    {
    }

    /**
     * Gets the foreground color of the barcode.
     *
     * @return BCG_Color
     */
    public function getForegroundColor()
    {
        return $this->colorFg;
    }

    /**
     * Sets the foreground color of the barcode. It could be a BCG_Color
     * value or simply a language code (white, black, yellow...) or hex value.
     *
     * @param mixed $code
     */
    public function setForegroundColor($code)
    {
        if ($code instanceof BCG_Color) {
            $this->colorFg = $code;
        } else {
            $this->colorFg = new BCG_Color($code);
        }
    }

    /**
     * Gets the background color of the barcode.
     *
     * @return BCG_Color
     */
    public function getBackgroundColor()
    {
        return $this->colorBg;
    }

    /**
     * Sets the background color of the barcode. It could be a BCG_Color
     * value or simply a language code (white, black, yellow...) or hex value.
     *
     * @param mixed $code
     */
    public function setBackgroundColor($code)
    {
        if ($code instanceof BCG_Color) {
            $this->colorBg = $code;
        } else {
            $this->colorBg = new BCG_Color($code);
        }

        foreach ($this->labels as &$label) {
            $label->setBackgroundColor($this->colorBg);
        }
    }

    /**
     * Sets the color.
     *
     * @param mixed $fg
     * @param mixed $bg
     */
    public function setColor($fg, $bg)
    {
        $this->setForegroundColor($fg);
        $this->setBackgroundColor($bg);
    }

    /**
     * Gets the scale of the barcode.
     *
     * @return int
     */
    public function getScale()
    {
        return $this->scale;
    }

    /**
     * Sets the scale of the barcode in pixel.
     * If the scale is lower than 1, an exception is raised.
     *
     * @param int $scale
     */
    public function setScale($scale)
    {
        $scale = intval($scale);
        if ($scale <= 0) {
            throw new EspError('The scale must be larger than 0.');
        }

        $this->scale = $scale;
    }

    /**
     * Abstract method that draws the barcode on the resource.
     *
     * @param resource $im
     */
    abstract public function draw($im);

    /**
     * Returns the maximal size of a barcode.
     * [0]->width
     * [1]->height
     *
     * @param int $w
     * @param int $h
     * @return int[]
     */
    public function getDimension($w, $h)
    {
        $labels = $this->getBiggestLabels(false);
        $pixelsAround = array(0, 0, 0, 0); // TRBL
        if (isset($labels[BCG_Label::POSITION_TOP])) {
            $dimension = $labels[BCG_Label::POSITION_TOP]->getDimension();
            $pixelsAround[0] += $dimension[1];
        }

        if (isset($labels[BCG_Label::POSITION_RIGHT])) {
            $dimension = $labels[BCG_Label::POSITION_RIGHT]->getDimension();
            $pixelsAround[1] += $dimension[0];
        }

        if (isset($labels[BCG_Label::POSITION_BOTTOM])) {
            $dimension = $labels[BCG_Label::POSITION_BOTTOM]->getDimension();
            $pixelsAround[2] += $dimension[1];
        }

        if (isset($labels[BCG_Label::POSITION_LEFT])) {
            $dimension = $labels[BCG_Label::POSITION_LEFT]->getDimension();
            $pixelsAround[3] += $dimension[0];
        }

        $finalW = ($w + $this->offsetX) * $this->scale;
        $finalH = ($h + $this->offsetY) * $this->scale;

        // This section will check if a top/bottom label is too big for its width and left/right too big for its height
        $reversedLabels = $this->getBiggestLabels(true);
        foreach ($reversedLabels as &$label) {
            $dimension = $label->getDimension();
            $alignment = $label->getAlignment();
            if ($label->getPosition() === BCG_Label::POSITION_LEFT || $label->getPosition() === BCG_Label::POSITION_RIGHT) {
                if ($alignment === BCG_Label::ALIGN_TOP) {
                    $pixelsAround[2] = max($pixelsAround[2], $dimension[1] - $finalH);
                } elseif ($alignment === BCG_Label::ALIGN_CENTER) {
                    $temp = ceil(($dimension[1] - $finalH) / 2);
                    $pixelsAround[0] = max($pixelsAround[0], $temp);
                    $pixelsAround[2] = max($pixelsAround[2], $temp);
                } elseif ($alignment === BCG_Label::ALIGN_BOTTOM) {
                    $pixelsAround[0] = max($pixelsAround[0], $dimension[1] - $finalH);
                }
            } else {
                if ($alignment === BCG_Label::ALIGN_LEFT) {
                    $pixelsAround[1] = max($pixelsAround[1], $dimension[0] - $finalW);
                } elseif ($alignment === BCG_Label::ALIGN_CENTER) {
                    $temp = ceil(($dimension[0] - $finalW) / 2);
                    $pixelsAround[1] = max($pixelsAround[1], $temp);
                    $pixelsAround[3] = max($pixelsAround[3], $temp);
                } elseif ($alignment === BCG_Label::ALIGN_RIGHT) {
                    $pixelsAround[3] = max($pixelsAround[3], $dimension[0] - $finalW);
                }
            }
        }

        $this->pushLabel[0] = $pixelsAround[3];
        $this->pushLabel[1] = $pixelsAround[0];

        $finalW = ($w + $this->offsetX) * $this->scale + $pixelsAround[1] + $pixelsAround[3];
        $finalH = ($h + $this->offsetY) * $this->scale + $pixelsAround[0] + $pixelsAround[2];

        return array($finalW, $finalH);
    }

    /**
     * Gets the X offset.
     *
     * @return int
     */
    public function getOffsetX()
    {
        return $this->offsetX;
    }

    /**
     * Sets the X offset.
     *
     * @param int $offsetX
     */
    public function setOffsetX($offsetX)
    {
        $offsetX = intval($offsetX);
        if ($offsetX < 0) {
            throw new EspError('The offset X must be 0 or larger.');
        }

        $this->offsetX = $offsetX;
    }

    /**
     * Gets the Y offset.
     *
     * @return int
     */
    public function getOffsetY()
    {
        return $this->offsetY;
    }

    /**
     * Sets the Y offset.
     *
     * @param int $offsetY
     */
    public function setOffsetY($offsetY)
    {
        $offsetY = intval($offsetY);
        if ($offsetY < 0) {
            throw new EspError('The offset Y must be 0 or larger.');
        }

        $this->offsetY = $offsetY;
    }

    /**
     * Adds the label to the drawing.
     *
     * @param BCG_Label $label
     */
    public function addLabel(BCG_Label $label)
    {
        $label->setBackgroundColor($this->colorBg);
        $this->labels[] = $label;
    }

    /**
     * Removes the label from the drawing.
     *
     * @param BCG_Label $label
     */
    public function removeLabel(BCG_Label $label)
    {
        $remove = -1;
        $c = count($this->labels);
        for ($i = 0; $i < $c; $i++) {
            if ($this->labels[$i] === $label) {
                $remove = $i;
                break;
            }
        }

        if ($remove > -1) {
            array_splice($this->labels, $remove, 1);
        }
    }

    /**
     * Clears the labels.
     */
    public function clearLabels()
    {
        $this->labels = array();
    }

    /**
     * 画标签
     */
    protected function drawText($im, $x1, $y1, $x2, $y2)
    {
        foreach ($this->labels as &$label) {
            $label->draw($im,
                ($x1 + $this->offsetX) * $this->scale + $this->pushLabel[0],
                ($y1 + $this->offsetY) * $this->scale + $this->pushLabel[1],
                ($x2 + $this->offsetX) * $this->scale + $this->pushLabel[0],
                ($y2 + $this->offsetY) * $this->scale + $this->pushLabel[1]);
        }
    }

    /**
     * Draws 1 pixel on the resource at a specific position with a determined color.
     *
     * @param resource $im
     * @param int $x
     * @param int $y
     * @param int $color
     */
    protected function drawPixel($im, $x, $y, $color = self::COLOR_FG)
    {
        $xR = ($x + $this->offsetX) * $this->scale + $this->pushLabel[0];
        $yR = ($y + $this->offsetY) * $this->scale + $this->pushLabel[1];

        // We always draw a rectangle
        imagefilledrectangle($im,
            $xR,
            $yR,
            $xR + $this->scale - 1,
            $yR + $this->scale - 1,
            $this->getColor($im, $color));
    }

    /**
     * Draws an empty rectangle on the resource at a specific position with a determined color.
     *
     * @param resource $im
     * @param int $x1
     * @param int $y1
     * @param int $x2
     * @param int $y2
     * @param int $color
     */
    protected function drawRectangle($im, $x1, $y1, $x2, $y2, $color = self::COLOR_FG)
    {
        if ($this->scale === 1) {
            imagerectangle($im,
                ($x1 + $this->offsetX) * $this->scale + $this->pushLabel[0],
                ($y1 + $this->offsetY) * $this->scale + $this->pushLabel[1],
                ($x2 + $this->offsetX) * $this->scale + $this->pushLabel[0],
                ($y2 + $this->offsetY) * $this->scale + $this->pushLabel[1],
                $this->getColor($im, $color));
        } else {
            imagefilledrectangle($im, ($x1 + $this->offsetX) * $this->scale + $this->pushLabel[0], ($y1 + $this->offsetY) * $this->scale + $this->pushLabel[1], ($x2 + $this->offsetX) * $this->scale + $this->pushLabel[0] + $this->scale - 1, ($y1 + $this->offsetY) * $this->scale + $this->pushLabel[1] + $this->scale - 1, $this->getColor($im, $color));
            imagefilledrectangle($im, ($x1 + $this->offsetX) * $this->scale + $this->pushLabel[0], ($y1 + $this->offsetY) * $this->scale + $this->pushLabel[1], ($x1 + $this->offsetX) * $this->scale + $this->pushLabel[0] + $this->scale - 1, ($y2 + $this->offsetY) * $this->scale + $this->pushLabel[1] + $this->scale - 1, $this->getColor($im, $color));
            imagefilledrectangle($im, ($x2 + $this->offsetX) * $this->scale + $this->pushLabel[0], ($y1 + $this->offsetY) * $this->scale + $this->pushLabel[1], ($x2 + $this->offsetX) * $this->scale + $this->pushLabel[0] + $this->scale - 1, ($y2 + $this->offsetY) * $this->scale + $this->pushLabel[1] + $this->scale - 1, $this->getColor($im, $color));
            imagefilledrectangle($im, ($x1 + $this->offsetX) * $this->scale + $this->pushLabel[0], ($y2 + $this->offsetY) * $this->scale + $this->pushLabel[1], ($x2 + $this->offsetX) * $this->scale + $this->pushLabel[0] + $this->scale - 1, ($y2 + $this->offsetY) * $this->scale + $this->pushLabel[1] + $this->scale - 1, $this->getColor($im, $color));
        }
    }

    /**
     * Draws a filled rectangle on the resource at a specific position with a determined color.
     *
     * @param resource $im
     * @param int $x1
     * @param int $y1
     * @param int $x2
     * @param int $y2
     * @param int $color
     */
    protected function drawFilledRectangle($im, $x1, $y1, $x2, $y2, $color = self::COLOR_FG)
    {
        if ($x1 > $x2) { // Swap
            $x1 ^= $x2 ^= $x1 ^= $x2;
        }

        if ($y1 > $y2) { // Swap
            $y1 ^= $y2 ^= $y1 ^= $y2;
        }

        imagefilledrectangle($im,
            ($x1 + $this->offsetX) * $this->scale + $this->pushLabel[0],
            ($y1 + $this->offsetY) * $this->scale + $this->pushLabel[1],
            ($x2 + $this->offsetX) * $this->scale + $this->pushLabel[0] + $this->scale - 1,
            ($y2 + $this->offsetY) * $this->scale + $this->pushLabel[1] + $this->scale - 1,
            $this->getColor($im, $color));
    }

    /**
     * Allocates the color based on the integer.
     *
     * @param resource $im
     * @param int $color
     * @return int
     */
    protected function getColor($im, $color)
    {
        if ($color === self::COLOR_BG) {
            return $this->colorBg->allocate($im);
        } else {
            return $this->colorFg->allocate($im);
        }
    }

    /**
     * Returning the biggest label widths for LEFT/RIGHT and heights for TOP/BOTTOM.
     *
     * @param bool $reversed
     * @return BCG_Label[]
     */
    private function getBiggestLabels($reversed = false)
    {
        $searchLR = $reversed ? 1 : 0;
        $searchTB = $reversed ? 0 : 1;

        $labels = array();
        foreach ($this->labels as &$label) {
            $position = $label->getPosition();
            if (isset($labels[$position])) {
                $savedDimension = $labels[$position]->getDimension();
                $dimension = $label->getDimension();
                if ($position === BCG_Label::POSITION_LEFT || $position === BCG_Label::POSITION_RIGHT) {
                    if ($dimension[$searchLR] > $savedDimension[$searchLR]) {
                        $labels[$position] = $label;
                    }
                } else {
                    if ($dimension[$searchTB] > $savedDimension[$searchTB]) {
                        $labels[$position] = $label;
                    }
                }
            } else {
                $labels[$position] = $label;
            }
        }

        return $labels;
    }
}

abstract class BCG_Barcode1D extends BCG_Barcode
{
    const SIZE_SPACING_FONT = 5;

    const AUTO_LABEL = '##!!AUTO_LABEL!!##';

    protected $thickness;
    protected $keys, $code;
    protected $positionX;
    protected $textfont;
    protected $text;
    protected $checksumValue;
    protected $displayChecksum;
    protected $label;                    // Label
    protected $defaultLabel;            // BCG_Label
    protected $font;

    protected function __construct()
    {
        parent::__construct();

        $this->setThickness(30);

        $this->defaultLabel = new BCG_Label();
        $this->defaultLabel->setPosition(BCG_Label::POSITION_BOTTOM);
        $this->setLabel(self::AUTO_LABEL);
        $this->setFont(new BCG_FontPhp(5));

        $this->text = '';
        $this->checksumValue = false;
    }

    public function getThickness()
    {
        return $this->thickness;
    }

    public function setThickness($thickness)
    {
        $this->thickness = intval($thickness);
        if ($this->thickness <= 0) {
            throw new EspError('The thickness must be larger than 0.');
        }
    }

    /**
     * Gets the label.
     * If the label was set to BCG_Barcode1D::AUTO_LABEL, the label will display the value from the text parsed.
     *
     * @return string
     */
    public function getLabel()
    {
        $label = $this->label;
        if ($this->label === self::AUTO_LABEL) {
            $label = $this->text;
            if ($this->displayChecksum === true && ($checksum = $this->processChecksum()) !== false) {
                $label .= $checksum;
            }
        }

        return $label;
    }

    /**
     * Sets the label.
     * You can use BCG_BarCode::AUTO_LABEL to have the label automatically written based on the parsed text.
     *
     * @param string $label
     */
    public function setLabel($label)
    {
        $this->label = $label;
    }

    /**
     * Sets the font.
     *
     * @param mixed $font BCG_Font or int
     */
    public function setFont($font)
    {
        if (is_int($font)) {
            if ($font === 0) {
                $font = null;
            } else {
                $font = new BCG_FontPhp($font);
            }
        }

        $this->font = $font;
    }

    /**
     * Parses the text before displaying it.
     *
     * @param mixed $text
     */
    public function parse($text)
    {
        $this->text = $text;
        $this->checksumValue = false;        // Reset checksumValue
        $this->validate();
        parent::parse($text);
        $this->addDefaultLabel();
    }

    /**
     * Gets the checksum of a Barcode.
     * If no checksum is available, return FALSE.
     *
     * @return string
     */
    public function getChecksum()
    {
        return $this->processChecksum();
    }

    /**
     * Sets if the checksum is displayed with the label or not.
     * The checksum must be activated in some case to make this variable effective.
     *
     * @param boolean $displayChecksum
     */
    public function setDisplayChecksum($displayChecksum)
    {
        $this->displayChecksum = (bool)$displayChecksum;
    }

    /**
     * 加标签
     */
    protected function addDefaultLabel()
    {
        $label = $this->getLabel();
        $font = $this->font;
        if ($label !== null && $label !== '' && $font !== null && $this->defaultLabel !== null) {
            $this->defaultLabel->setText($label);
            $this->defaultLabel->setFont($font);
            $this->addLabel($this->defaultLabel);
        }
    }

    /**
     * Validates the input
     */
    protected function validate()
    {
        // No validation in the abstract class.
    }

    /**
     * Returns the index in $keys (useful for checksum).
     *
     * @param mixed $var
     * @return mixed
     */
    protected function findIndex($var)
    {
        return array_search($var, $this->keys);
    }

    /**
     * Returns the code of the char (useful for drawing bars).
     *
     * @param mixed $var
     * @return string
     */
    protected function findCode($var)
    {
        return $this->code[$this->findIndex($var)];
    }

    /**
     * Draws all chars thanks to $code. if $start is true, the line begins by a space.
     * if $start is false, the line begins by a bar.
     *
     * @param resource $im
     * @param string $code
     * @param boolean $startBar
     */
    protected function drawChar($im, $code, $startBar = true)
    {
        $colors = array(self::COLOR_FG, self::COLOR_BG);
        $currentColor = $startBar ? 0 : 1;
        $c = strlen($code);
        for ($i = 0; $i < $c; $i++) {
            for ($j = 0; $j < intval($code[$i]) + 1; $j++) {
                $this->drawSingleBar($im, $colors[$currentColor]);
                $this->nextX();
            }

            $currentColor = ($currentColor + 1) % 2;
        }
    }

    /**
     * Draws a Bar of $color depending of the resolution.
     *
     * @param resource $img
     * @param int $color
     */
    protected function drawSingleBar($im, $color)
    {
        $this->drawFilledRectangle($im, $this->positionX, 0, $this->positionX, $this->thickness - 1, $color);
    }

    /**
     * Moving the pointer right to write a bar.
     */
    protected function nextX()
    {
        $this->positionX++;
    }

    /**
     * Method that saves FALSE into the checksumValue. This means no checksum
     * but this method should be overriden when needed.
     */
    protected function calculateChecksum()
    {
        $this->checksumValue = false;
    }

    /**
     * Returns FALSE because there is no checksum. This method should be
     * overriden to return correctly the checksum in string with checksumValue.
     *
     * @return string
     */
    protected function processChecksum()
    {
        return false;
    }
}

final class BCG_code128 extends BCG_Barcode1D
{
    const KEYA_FNC3 = 96;
    const KEYA_FNC2 = 97;
    const KEYA_SHIFT = 98;
    const KEYA_CODEC = 99;
    const KEYA_CODEB = 100;
    const KEYA_FNC4 = 101;
    const KEYA_FNC1 = 102;

    const KEYB_FNC3 = 96;
    const KEYB_FNC2 = 97;
    const KEYB_SHIFT = 98;
    const KEYB_CODEC = 99;
    const KEYB_FNC4 = 100;
    const KEYB_CODEA = 101;
    const KEYB_FNC1 = 102;

    const KEYC_CODEB = 100;
    const KEYC_CODEA = 101;
    const KEYC_FNC1 = 102;

    const KEY_STARTA = 103;
    const KEY_STARTB = 104;
    const KEY_STARTC = 105;

    const KEY_STOP = 106;

    protected $keysA, $keysB, $keysC;
    private $starting_text;
    private $indcheck, $data;
    private $tilde;

    private $shift;
    private $latch;
    private $fnc;

    private $METHOD = NULL; // Array of method available to create PDF417 (PDF417_TM, PDF417_NM, PDF417_BM)


    public function __construct($start = NULL)
    {
        parent::__construct();

        /* CODE 128 A */
        $this->keysA = ' !"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^_';
        for ($i = 0; $i < 32; $i++) {
            $this->keysA .= chr($i);
        }

        /* CODE 128 B */
        $this->keysB = ' !"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^_`abcdefghijklmnopqrstuvwxyz{|}~' . chr(127);

        /* CODE 128 C */
        $this->keysC = '0123456789';

        $this->code = array(
            '101111',    /* 00 */
            '111011',    /* 01 */
            '111110',    /* 02 */
            '010112',    /* 03 */
            '010211',    /* 04 */
            '020111',    /* 05 */
            '011102',    /* 06 */
            '011201',    /* 07 */
            '021101',    /* 08 */
            '110102',    /* 09 */
            '110201',    /* 10 */
            '120101',    /* 11 */
            '001121',    /* 12 */
            '011021',    /* 13 */
            '011120',    /* 14 */
            '002111',    /* 15 */
            '012011',    /* 16 */
            '012110',    /* 17 */
            '112100',    /* 18 */
            '110021',    /* 19 */
            '110120',    /* 20 */
            '102101',    /* 21 */
            '112001',    /* 22 */
            '201020',    /* 23 */
            '200111',    /* 24 */
            '210011',    /* 25 */
            '210110',    /* 26 */
            '201101',    /* 27 */
            '211001',    /* 28 */
            '211100',    /* 29 */
            '101012',    /* 30 */
            '101210',    /* 31 */
            '121010',    /* 32 */
            '000212',    /* 33 */
            '020012',    /* 34 */
            '020210',    /* 35 */
            '001202',    /* 36 */
            '021002',    /* 37 */
            '021200',    /* 38 */
            '100202',    /* 39 */
            '120002',    /* 40 */
            '120200',    /* 41 */
            '001022',    /* 42 */
            '001220',    /* 43 */
            '021020',    /* 44 */
            '002012',    /* 45 */
            '002210',    /* 46 */
            '022010',    /* 47 */
            '202010',    /* 48 */
            '100220',    /* 49 */
            '120020',    /* 50 */
            '102002',    /* 51 */
            '102200',    /* 52 */
            '102020',    /* 53 */
            '200012',    /* 54 */
            '200210',    /* 55 */
            '220010',    /* 56 */
            '201002',    /* 57 */
            '201200',    /* 58 */
            '221000',    /* 59 */
            '203000',    /* 60 */
            '110300',    /* 61 */
            '320000',    /* 62 */
            '000113',    /* 63 */
            '000311',    /* 64 */
            '010013',    /* 65 */
            '010310',    /* 66 */
            '030011',    /* 67 */
            '030110',    /* 68 */
            '001103',    /* 69 */
            '001301',    /* 70 */
            '011003',    /* 71 */
            '011300',    /* 72 */
            '031001',    /* 73 */
            '031100',    /* 74 */
            '130100',    /* 75 */
            '110003',    /* 76 */
            '302000',    /* 77 */
            '130001',    /* 78 */
            '023000',    /* 79 */
            '000131',    /* 80 */
            '010031',    /* 81 */
            '010130',    /* 82 */
            '003101',    /* 83 */
            '013001',    /* 84 */
            '013100',    /* 85 */
            '300101',    /* 86 */
            '310001',    /* 87 */
            '310100',    /* 88 */
            '101030',    /* 89 */
            '103010',    /* 90 */
            '301010',    /* 91 */
            '000032',    /* 92 */
            '000230',    /* 93 */
            '020030',    /* 94 */
            '003002',    /* 95 */
            '003200',    /* 96 */
            '300002',    /* 97 */
            '300200',    /* 98 */
            '002030',    /* 99 */
            '003020',    /* 100*/
            '200030',    /* 101*/
            '300020',    /* 102*/
            '100301',    /* 103*/
            '100103',    /* 104*/
            '100121',    /* 105*/
            '122000'    /*STOP*/
        );
        $this->setStart($start);
        $this->setTilde(true);

        $this->latch = array(
            array(null, self::KEYA_CODEB, self::KEYA_CODEC),
            array(self::KEYB_CODEA, null, self::KEYB_CODEC),
            array(self::KEYC_CODEA, self::KEYC_CODEB, null)
        );
        $this->shift = array(
            array(null, self::KEYA_SHIFT),
            array(self::KEYB_SHIFT, null)
        );
        $this->fnc = array(
            array(self::KEYA_FNC1, self::KEYA_FNC2, self::KEYA_FNC3, self::KEYA_FNC4),
            array(self::KEYB_FNC1, self::KEYB_FNC2, self::KEYB_FNC3, self::KEYB_FNC4),
            array(self::KEYC_FNC1, null, null, null)
        );

        // Method available
        $this->METHOD = array(1 => 'A', 2 => 'B', 3 => 'C');
    }

    /**
     * Specifies the start code. Can be 'A', 'B', 'C', or NULL
     *  - Table A: Capitals + ASCII 0-31 + punct
     *  - Table B: Capitals + LowerCase + punct
     *  - Table C: Numbers
     *
     * If NULL is specified, the table selection is automatically made.
     * The default is NULL.
     *
     * @param string $table
     */
    public function setStart($table)
    {
        if ($table !== 'A' && $table !== 'B' && $table !== 'C' && $table !== NULL) {
            throw new EspError('The starting table must be A, B, C or NULL.');
        }

        $this->starting_text = $table;
    }

    /**
     * Gets the tilde.
     *
     * @return bool
     */
    public function getTilde()
    {
        return $this->tilde;
    }

    /**
     * Accepts tilde to be process as a special character.
     * If true, you can do this:
     *  - ~~    : to make ONE tilde
     *  - ~Fx    : to insert FCNx. x is equal from 1 to 4.
     *
     * @param boolean $accept
     */
    public function setTilde($accept)
    {
        $this->tilde = (bool)$accept;
    }

    /**
     * Parses the text before displaying it.
     *
     * @param mixed $text
     */
    public function parse($text)
    {
        $this->setStartFromText($text);

        $this->text = '';
        $seq = '';

        $currentMode = $this->starting_text;

        if (!is_array($text)) {
            $seq = $this->getSequence($text, $currentMode);
            $this->text = $text;
        } else {
            reset($text);
            while (list($key1, $val1) = each($text)) {            // We take each value
                if (!is_array($val1)) {                    // This is not a table
                    if (is_string($val1)) {                // If it's a string, parse as unknown
                        $seq .= $this->getSequence($val1, $currentMode);
                        $this->text .= $val1;
                    } else {
                        // it's the case of "array(ENCODING, 'text')"
                        // We got ENCODING in $val1, calling 'each' again will get 'text' in $val2
                        list($key2, $val2) = each($text);
                        $seq .= $this->{'setParse' . $this->METHOD[$val1]}($val2, $currentMode);
                        $this->text .= $val2;
                    }
                } else {                        // The method is specified
                    // $val1[0] = ENCODING
                    // $val1[1] = 'text'
                    $value = isset($val1[1]) ? $val1[1] : '';    // If data available
                    $seq .= $this->{'setParse' . $this->METHOD[$val1[0]]}($value, $currentMode);
                    $this->text .= $value;
                }
            }
        }

        if ($seq !== '') {
            $stream = $this->createBinaryStream($this->text, $seq);
            $this->setData($stream);
        }

        $this->addDefaultLabel();
    }

    public function draw($im)
    {
        $c = count($this->data);
        for ($i = 0; $i < $c; $i++) {
            $this->drawChar($im, $this->data[$i], true);
        }
        $this->drawChar($im, '1', true);
        $this->drawText($im, 0, 0, $this->positionX, $this->thickness);
    }

    /**
     * Returns the maximal size of a barcode.
     *
     * @param int $w
     * @param int $h
     * @return int[]
     */
    public function getDimension($w, $h)
    {
        // Contains start + text + checksum + stop
        $textlength = count($this->data) * 11;
        $endlength = 2; // + final bar

        $w += $textlength + $endlength;
        $h += $this->thickness;
        return parent::getDimension($w, $h);
    }

    /**
     * Validates the input.
     */
    protected function validate()
    {
        $c = count($this->data);
        if ($c === 0) {
            throw new EspError('No data has been entered.');
        }

        parent::validate();
    }

    /**
     * Overloaded method to calculate checksum.
     */
    protected function calculateChecksum()
    {
        // Checksum
        // First Char (START)
        // + Starting with the first data character following the start character,
        // take the value of the character (between 0 and 102, inclusive) multiply
        // it by its character position (1) and add that to the running checksum.
        // Modulated 103
        $this->checksumValue = $this->indcheck[0];
        $c = count($this->indcheck);
        for ($i = 1; $i < $c; $i++) {
            $this->checksumValue += $this->indcheck[$i] * $i;
        }

        $this->checksumValue = $this->checksumValue % 103;
    }

    /**
     * Overloaded method to display the checksum.
     */
    protected function processChecksum()
    {
        if ($this->checksumValue === false) { // Calculate the checksum only once
            $this->calculateChecksum();
        }

        if ($this->checksumValue !== false) {
            return $this->keys[$this->checksumValue];
        }

        return false;
    }

    /**
     * Specifies the starting_text table if none has been specified earlier.
     *
     * @param string $text
     */
    private function setStartFromText($text)
    {
        if ($this->starting_text === NULL) {
            // If we have a forced table at the start, we get that one...
            if (is_array($text)) {
                if (is_array($text[0])) {
                    // Code like array(array(ENCODING, ''))
                    $this->starting_text = $this->METHOD[$text[0][0]];
                    return;
                } else {
                    if (is_string($text[0])) {
                        // Code like array('test') (Automatic text)
                        $text = $text[0];
                    } else {
                        // Code like array(ENCODING, '')
                        $this->starting_text = $this->METHOD[$text[0]];
                        return;
                    }
                }
            }

            // At this point, we had an "automatic" table selection...
            // If we can get at least 4 numbers, go in C; otherwise go in B.
            $tmp = preg_quote($this->keysC, '/');
            if (strlen($text) >= 4 && preg_match('/[' . $tmp . ']/', substr($text, 0, 4))) {
                $this->starting_text = 'C';
            } else {
                if (strpos($this->keysB, $text[0])) {
                    $this->starting_text = 'B';
                } else {
                    $this->starting_text = 'A';
                }
            }
        }
    }

    /**
     * Extracts the ~ value from the $text at the $pos.
     * If the tilde is not ~~, ~F1, ~F2, ~F3, ~F4; an error is raised.
     *
     * @param string $text
     * @param int $pos
     * @return string
     */
    private function extractTilde($text, $pos)
    {
        if ($text[$pos] === '~') {
            if (isset($text[$pos + 1])) {
                // Do we have a tilde?
                if ($text[$pos + 1] === '~') {
                    return '~~';
                } elseif ($text[$pos + 1] === 'F') {
                    // Do we have a number after?
                    if (isset($text[$pos + 2])) {
                        $v = intval($text[$pos + 2]);
                        if ($v >= 1 && $v <= 4) {
                            return '~F' . $v;
                        } else {
                            throw new EspError('Bad ~F. You must provide a number from 1 to 4.');
                        }
                    } else {
                        throw new EspError('Bad ~F. You must provide a number from 1 to 4.');
                    }
                } else {
                    throw new EspError('Wrong code after the ~.');
                }
            } else {
                throw new EspError('Wrong code after the ~.');
            }
        } else {
            throw new EspError('There is no ~ at this location.');
        }
    }

    /**
     * Gets the "dotted" sequence for the $text based on the $currentMode.
     * There is also a check if we use the special tilde ~
     *
     * @param string $text
     * @param string $currentMode
     * @return string
     */
    private function getSequenceParsed($text, $currentMode)
    {
        if ($this->tilde) {
            $sequence = '';
            $previousPos = 0;
            while (($pos = strpos($text, '~', $previousPos)) !== false) {
                $tildeData = $this->extractTilde($text, $pos);

                $simpleTilde = ($tildeData === '~~');
                if ($simpleTilde && $currentMode !== 'B') {
                    throw new EspError('The Table ' . $currentMode . ' doesn\'t contain the character ~.');
                }

                // At this point, we know we have ~Fx
                if ($tildeData !== '~F1' && $currentMode === 'C') {
                    // The mode C doesn't support ~F2, ~F3, ~F4
                    throw new EspError('The Table C doesn\'t contain the function ' . $tildeData . '.');
                }

                $length = $pos - $previousPos;
                if ($currentMode === 'C') {
                    if ($length % 2 === 1) {
                        throw new EspError('The text "' . $text . '" must have an even number of character to be encoded in Table C.');
                    }
                }

                $sequence .= str_repeat('.', $length);
                $sequence .= '.';
                $sequence .= (!$simpleTilde) ? 'F' : '';
                $previousPos = $pos + strlen($tildeData);
            }

            // Flushing
            $length = strlen($text) - $previousPos;
            if ($currentMode === 'C') {
                if ($length % 2 === 1) {
                    throw new EspError('The text "' . $text . '" must have an even number of character to be encoded in Table C.');
                }
            }

            $sequence .= str_repeat('.', $length);

            return $sequence;
        } else {
            return str_repeat('.', strlen($text));
        }
    }

    /**
     * Parses the text and returns the appropriate sequence for the Table A.
     *
     * @param string $text
     * @param string $currentMode
     * @return string
     */
    private function setParseA($text, &$currentMode)
    {
        $tmp = preg_quote($this->keysA, '/');

        // If we accept the ~ for special character, we must allow it.
        if ($this->tilde) {
            $tmp .= '~';
        }

        $match = array();
        if (preg_match('/[^' . $tmp . ']/', $text, $match) === 1) {
            // We found something not allowed
            throw new EspError('The text "' . $text . '" can\'t be parsed with the Table A. The character "' . $match[0] . '" is not allowed.');
        } else {
            $latch = ($currentMode === 'A') ? '' : '0';
            $currentMode = 'A';

            return $latch . $this->getSequenceParsed($text, $currentMode);
        }
    }

    /**
     * Parses the text and returns the appropriate sequence for the Table B.
     *
     * @param string $text
     * @param string $currentMode
     * @return string
     */
    private function setParseB($text, &$currentMode)
    {
        $tmp = preg_quote($this->keysB, '/');

        $match = array();
        if (preg_match('/[^' . $tmp . ']/', $text, $match) === 1) {
            // We found something not allowed
            throw new EspError('The text "' . $text . '" can\'t be parsed with the Table B. The character "' . $match[0] . '" is not allowed.');
        } else {
            $latch = ($currentMode === 'B') ? '' : '1';
            $currentMode = 'B';

            return $latch . $this->getSequenceParsed($text, $currentMode);
        }
    }

    /**
     * Parses the text and returns the appropriate sequence for the Table C.
     *
     * @param string $text
     * @param string $currentMode
     * @return string
     */
    private function setParseC($text, &$currentMode)
    {
        $tmp = preg_quote($this->keysC, '/');

        // If we accept the ~ for special character, we must allow it.
        if ($this->tilde) {
            $tmp .= '~F';
        }

        $match = array();
        if (preg_match('/[^' . $tmp . ']/', $text, $match) === 1) {
            // We found something not allowed
            throw new EspError('The text "' . $text . '" can\'t be parsed with the Table C. The character "' . $match[0] . '" is not allowed.');
        } else {
            $latch = ($currentMode === 'C') ? '' : '2';
            $currentMode = 'C';

            return $latch . $this->getSequenceParsed($text, $currentMode);
        }
    }

    /**
     * Depending on the $text, it will return the correct
     * sequence to encode the text.
     *
     * @param string $text
     * @param string $starting_text
     * @return string
     */
    private function getSequence(&$text, &$starting_text)
    {
        $e = 10000;
        $latLen = array(
            array(0, 1, 1),
            array(1, 0, 1),
            array(1, 1, 0)
        );
        $shftLen = array(
            array($e, 1, $e),
            array(1, $e, $e),
            array($e, $e, $e)
        );
        $charSiz = array(2, 2, 1);

        $startA = $e;
        $startB = $e;
        $startC = $e;
        if ($starting_text === 'A') $startA = 0;
        if ($starting_text === 'B') $startB = 0;
        if ($starting_text === 'C') $startC = 0;

        $curLen = array($startA, $startB, $startC);
        $curSeq = array(null, null, null);

        $nextNumber = false;

        $x = 0;
        $xLen = strlen($text);
        for ($x = 0; $x < $xLen; $x++) {
            $input = $text[$x];

            // 1.
            for ($i = 0; $i < 3; $i++) {
                for ($j = 0; $j < 3; $j++) {
                    if (($curLen[$i] + $latLen[$i][$j]) < $curLen[$j]) {
                        $curLen[$j] = $curLen[$i] + $latLen[$i][$j];
                        $curSeq[$j] = $curSeq[$i] . $j;
                    }
                }
            }

            // 2.
            $nxtLen = array($e, $e, $e);
            $nxtSeq = array();

            // 3.
            $flag = false;
            $posArray = array();

            // Special case, we do have a tilde and we process them
            if ($this->tilde && $input === '~') {
                $tildeData = $this->extractTilde($text, $x);

                if ($tildeData === '~~') {
                    // We simply skip a tilde
                    $posArray[] = 1;
                    $x++;
                } elseif (substr($tildeData, 0, 2) === '~F') {
                    $v = intval($tildeData[2]);
                    $posArray[] = 0;
                    $posArray[] = 1;
                    if ($v === 1) {
                        $posArray[] = 2;
                    }

                    $x += 2;
                    $flag = true;
                }
            } else {
                $pos = strpos($this->keysA, $input);
                if ($pos !== false) {
                    $posArray[] = 0;
                }

                $pos = strpos($this->keysB, $input);
                if ($pos !== false) {
                    $posArray[] = 1;
                }

                // Do we have the next char a number?? OR a ~F1
                $pos = strpos($this->keysC, $input);
                if ($nextNumber || ($pos !== false && isset($text[$x + 1]) && strpos($this->keysC, $text[$x + 1]) !== false)) {
                    $nextNumber = !$nextNumber;
                    $posArray[] = 2;
                }
            }

            $c = count($posArray);
            for ($i = 0; $i < $c; $i++) {
                if (($curLen[$posArray[$i]] + $charSiz[$posArray[$i]]) < $nxtLen[$posArray[$i]]) {
                    $nxtLen[$posArray[$i]] = $curLen[$posArray[$i]] + $charSiz[$posArray[$i]];
                    $nxtSeq[$posArray[$i]] = $curSeq[$posArray[$i]] . '.';
                }

                for ($j = 0; $j < 2; $j++) {
                    if ($j === $posArray[$i]) continue;
                    if (($curLen[$j] + $shftLen[$j][$posArray[$i]] + $charSiz[$posArray[$i]]) < $nxtLen[$j]) {
                        $nxtLen[$j] = $curLen[$j] + $shftLen[$j][$posArray[$i]] + $charSiz[$posArray[$i]];
                        $nxtSeq[$j] = $curSeq[$j] . chr($posArray[$i] + 65) . '.';
                    }
                }
            }

            if ($c === 0) {
                // We found an unsuported character
                throw new EspError('Character ' . $input . ' not supported.');
            }

            if ($flag) {
                for ($i = 0; $i < 5; $i++) {
                    if (isset($nxtSeq[$i])) {
                        $nxtSeq[$i] .= 'F';
                    }
                }
            }

            // 4.
            for ($i = 0; $i < 3; $i++) {
                $curLen[$i] = $nxtLen[$i];
                if (isset($nxtSeq[$i])) {
                    $curSeq[$i] = $nxtSeq[$i];
                }
            }
        }

        // Every curLen under $e is possible but we take the smallest
        $m = $e;
        $k = -1;
        for ($i = 0; $i < 3; $i++) {
            if ($curLen[$i] < $m) {
                $k = $i;
                $m = $curLen[$i];
            }
        }

        if ($k === -1) {
            return '';
        }

        $starting_text = chr($k + 65);

        return $curSeq[$k];
    }

    /**
     * Depending on the sequence $seq given (returned from getSequence()),
     * this method will return the code stream in an array. Each char will be a
     * string of bit based on the Code 128.
     *
     * Each letter from the sequence represents bits.
     *
     * 0 to 2 are latches
     * A to B are Shift + Letter
     * . is a char in the current encoding
     *
     * @param string $text
     * @param string $seq
     * @return string[][]
     */
    private function createBinaryStream($text, $seq)
    {
        $c = strlen($seq);

        $data = array(); // code stream
        $indcheck = array(); // index for checksum

        $currentEncoding = 0;
        if ($this->starting_text === 'A') {
            $currentEncoding = 0;
            $indcheck[] = self::KEY_STARTA;
        } elseif ($this->starting_text === 'B') {
            $currentEncoding = 1;
            $indcheck[] = self::KEY_STARTB;
        } elseif ($this->starting_text === 'C') {
            $currentEncoding = 2;
            $indcheck[] = self::KEY_STARTC;
        }

        $data[] = $this->code[103 + $currentEncoding];

        $temporaryEncoding = -1;
        for ($i = 0, $counter = 0; $i < $c; $i++) {
            $input = $seq[$i];
            $inputI = intval($input);
            if ($input === '.') {
                $this->encodeChar($data, $currentEncoding, $seq, $text, $i, $counter, $indcheck);
                if ($temporaryEncoding !== -1) {
                    $currentEncoding = $temporaryEncoding;
                    $temporaryEncoding = -1;
                }
            } elseif ($input >= 'A' && $input <= 'B') {
                // We shift
                $encoding = ord($input) - 65;
                $shift = $this->shift[$currentEncoding][$encoding];
                $indcheck[] = $shift;
                $data[] = $this->code[$shift];
                if ($temporaryEncoding === -1) {
                    $temporaryEncoding = $currentEncoding;
                }

                $currentEncoding = $encoding;
            } elseif ($inputI >= 0 && $inputI < 3) {
                $temporaryEncoding = -1;

                // We latch
                $latch = $this->latch[$currentEncoding][$inputI];
                if ($latch !== NULL) {
                    $indcheck[] = $latch;
                    $data[] = $this->code[$latch];
                    $currentEncoding = $inputI;
                }
            }
        }

        return array($indcheck, $data);
    }

    /**
     * Encodes characters, base on its encoding and sequence
     *
     * @param int[] $data
     * @param int $encoding
     * @param string $seq
     * @param string $text
     * @param int $i
     * @param int $counter
     * @param int[] $indcheck
     */
    private function encodeChar(&$data, $encoding, $seq, $text, &$i, &$counter, &$indcheck)
    {
        if (isset($seq[$i + 1]) && $seq[$i + 1] === 'F') {
            // We have a flag !!
            if ($text[$counter + 1] === 'F') {
                $number = $text[$counter + 2];
                $fnc = $this->fnc[$encoding][$number - 1];
                $indcheck[] = $fnc;
                $data[] = $this->code[$fnc];

                // Skip F + number
                $counter += 2;
            } else {
                // Not supposed
            }

            $i++;
        } else {
            if ($encoding === 2) {
                // We take 2 numbers in the same time
                $code = (int)substr($text, $counter, 2);
                $indcheck[] = $code;
                $data[] = $this->code[$code];
                $counter++;
                $i++;
            } else {
                $keys = ($encoding === 0) ? $this->keysA : $this->keysB;
                $pos = strpos($keys, $text[$counter]);
                $indcheck[] = $pos;
                $data[] = $this->code[$pos];
            }
        }

        $counter++;
    }

    /**
     * Saves data into the classes.
     *
     * This method will save data, calculate real column number
     * (if -1 was selected), the real error level (if -1 was
     * selected)... It will add Padding to the end and generate
     * the error codes.
     *
     * @param array $data
     */
    private function setData($data)
    {
        $this->indcheck = $data[0];
        $this->data = $data[1];
        $this->calculateChecksum();
        $this->data[] = $this->code[$this->checksumValue];
        $this->data[] = $this->code[self::KEY_STOP];
    }
}



