<?php
namespace www;

class TestController extends BaseController
{
    public function testAction()
    {
        $this->getView();
        pre($this->getRequest());
    }
}