<?php

namespace esp\library\img\code1;

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
            throw new \Exception('Font cannot be null.');
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
            throw new \Exception('The text position must be one of a valid constant.');
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
            throw new \Exception('The text alignment must be one of a valid constant.');
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
