<?php

namespace esp\library\img\code1;

use esp\error\EspError;

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
