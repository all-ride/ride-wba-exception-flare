<?php

namespace ride\web\base\controller;

use ride\library\http\Response;
use ride\library\log\Log;

/**
 * Controller to ask the user's comment on the occured exception and send it
 * through email to the developers
 */
class ExceptionController extends AbstractController
{
    /**
     * Returns a basic error page
     *
     * @return null
     */
    public function indexAction(Log $log, $id)
    {
        $logSession = $this->getLog()->getLogSession($id);
        if (!$logSession) {
            $this->response->setNotFound();

            return;
        }

        $this->setTemplateView('bootstrap4/base/log.detail', [
            'logSession' => $logSession,
            'request' => $this->getLogHttpRequest($logSession),
            'response' => $this->getLogHttpResponse($logSession),
            'session' => $this->getLogHttpSession($logSession),
            'application' => $this->getLogApplication($logSession),
            'mail' => $logSession->getLogMessagesBySource('mail'),
            'database' => $logSession->getLogMessagesBySource('database'),
            'security' => $logSession->getLogMessagesBySource('security'),
            'i18n' => $logSession->getLogMessagesBySource('i18n'),
        ]);
    }

    /**
     * Gets the application log messages from the log session
     * @param \ride\library\log\LogSession $logSession
     * @return array
     */
    protected function getLogApplication($logSession) {
        $inSession = false;

        $messages = $logSession->getLogMessagesBySource(array('app', 'event'));
        foreach ($messages as $index => $message) {
            $title = $message->getTitle();

            if ($inSession && strpos($title, '- ') === 0) {
                unset($messages[$index]);

                continue;
            } else {
                $inSession = false;
            }

            if (strpos($title, 'Receiving request') !== false || strpos($title, 'Receiving header') !== false || strpos($title, 'Sending response') !== false || strpos($title, 'Sending header') !== false) {
                unset($messages[$index]);

                continue;
            }

            if (strpos($title, 'Current session') !== false) {
                unset($messages[$index]);

                $inSession = true;

                continue;
            }
        }

        return $messages;
    }

    protected function getLogHttpRequest($logSession) {
        $request = array(
            'method' => null,
            'path' => null,
            'headers' => array(),
        );

        $messages = $logSession->getLogMessagesByQuery('Receiving request');
        if ($messages && count($messages) == 1) {
            $message = array_pop($messages);

            [$request['method'], $request['path']] = explode(' ', $message->getDescription(), 2);
        }

        if (!$request['method']) {
            return null;
        }

        $messages = $logSession->getLogMessagesByQuery('Receiving header');
        $request['headers'] = $this->parseHttpHeaders($messages);

        return $request;
    }

    protected function getLogHttpResponse($logSession) {
        $response = array(
            'status' => null,
            'headers' => array(),
        );

        $messages = $logSession->getLogMessagesByQuery('Sending response');
        if ($messages && count($messages) == 1) {
            $message = array_pop($messages);

            $response['status'] = trim(str_replace('Status code', '', $message->getDescription()));
            $response['statusPhrase'] = Response::getStatusPhrase($response['status']);
        }

        if (!$response['status']) {
            return null;
        }

        $messages = $logSession->getLogMessagesByQuery('Sending header');
        $response['headers'] = $this->parseHttpHeaders($messages);

        return $response;
    }

    protected function parseHttpHeaders(array $messages) {
        $headers = array();
        foreach ($messages as $message) {
            [$header, $value] = explode(':', $message->getDescription(), 2);

            $headers[] = array(
                'name' => $header,
                'value' => trim($value),
            );
        }

        return $headers;
    }

    protected function getLogHttpSession($logSession) {
        $session = array(
            'id' => null,
            'variables' => array(),
        );
        $isSession = false;

        $messages = $logSession->getLogMessages();
        foreach ($messages as $index => $message) {
            if (strpos($message->getTitle(), 'Current session') !== false) {
                $isSession = true;

                $session['id'] = $message->getDescription();

                continue;
            }

            if ($isSession && strpos($message->getTitle(), '- ') !== 0) {
                break;
            } elseif ($isSession) {
                $session['variables'][substr($message->getTitle(), 2)] = $message->getDescription();
            }
        }

        if (!$session['id']) {
            return null;
        }

        return $session;
    }
}
