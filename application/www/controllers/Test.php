<?php
namespace www;

class TestController extends BaseController
{
    public function testAction()
    {
        $this->view(false);
        pre($this->getRequest());
    }
}