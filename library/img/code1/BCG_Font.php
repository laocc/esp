<?php

namespace esp\library\img\code1;

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
