<?php

namespace Application\Controller;

use Modseven\Controller;

class Welcome extends Controller
{

    public function action_index()
    {
        $this->response->body('hello, world!');
    }

} // End Welcome
