<?php

namespace ride\web\base\controller;

/**
 * Controller to ask the user's comment on the occured exception and send it
 * through email to the developers
 */
class ExceptionController extends AbstractController {

    /**
     * Returns a basic error page
     * @return null
     */
    public function indexAction() {
        $this->setTemplateView('base/exception', array('form' => null));

        $this->response->setHeader('X-Ride-ExceptionForm', 'true');

        return;
    }
}
