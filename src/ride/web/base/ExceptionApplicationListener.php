<?php

namespace ride\web\base;

use ride\library\event\Event;
use ride\library\event\EventManager;
use ride\library\http\Header;
use ride\library\i18n\I18n;
use ride\library\security\exception\UnauthorizedException;

use ride\service\ExceptionService;

use ride\web\WebApplication;

/**
 * Application listener to override the default exception view with a error
 * reporting action
 */
class ExceptionApplicationListener {

    /**
     * Handle a exception, redirect to the error report form
     * @param \ride\library\event\Event $event
     * @param \ride\service\ExceptionService $service
     * @param \ride\library\event\EventManager $eventManager
     * @param \ride\library\i18n\I18n $i18n
     * @return null
     */
    public function handleException(Event $event, ExceptionService $service, EventManager $eventManager, I18n $i18n) {
        $exception = $event->getArgument('exception');
        if ($exception instanceof UnauthorizedException) {
            return;
        }

        // gather needed variables
        $web = $event->getArgument('web');
        $request = $web->getRequest();
        /** @var \ride\library\http\Response $response */
        $response = $web->getResponse();
        $locale = $i18n->getLocale();

        // Write and send report to FLAREAPP
        $id = $service->sendReport($exception);

        $route = $web->getRouterService()->getRouteById('exception.log');

        if ($id) {
            $route->setArguments(array('id' => $id));
        }

        $request = $web->createRequest($route->getPath(), 'GET');
        $request->setRoute($route);

        $statusCode = $response->getStatusCode();

        $response->setOk();
        $response->setView(null);
        $response->removeHeader(Header::HEADER_CONTENT_TYPE);
        $response->clearRedirect();

        $dispatcher = $web->getDispatcher();
        $dispatcher->dispatch($request, $response);

        $response->setStatusCode($statusCode);

        if ($web->getState() == WebApplication::STATE_RESPONSE) {
            // exception occured while rendering the template, trigger the pre
            // response event again for the new view
            $eventManager->triggerEvent(WebApplication::EVENT_PRE_RESPONSE, array('web' => $web));
        }
    }

}