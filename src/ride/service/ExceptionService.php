<?php

namespace ride\service;

use ride\library\http\Request;
use ride\library\log\Log;
use ride\library\security\model\User;
use ride\library\security\SecurityManager;
use ride\library\system\file\File;
use ride\library\validation\exception\ValidationException;
use ride\library\StringHelper;
use Facade\FlareClient\Flare;

use \Exception;

/**
 * Module to report and log exceptions
 */
class ExceptionService {

    /**
     * Instance of the log
     * @var \ride\library\log\Log
     */
    protected $log;

    /**
     * Instance of the incoming request
     * @var \ride\library\http\Request
     */
    protected $request;

    /**
     * Instance of the current user
     * @var \ride\library\security\model\User
     */
    protected $user;

    /**
     * Directory to write the error reports to
     * @var \ride\library\system\file\File
     */
    protected $directory;

    /**
     * Flare API key
     * @var string
     */
    protected $flareKey;

    /**
     * Sets the instance of the log
     * @param \ride\library\log\Log
     * @return null
     */
    public function setLog(Log $log) {
        $this->log = $log;
    }

    /**
     * Sets the instance of the incoming request
     * @param \ride\library\http\Request
     * @return null
     */
    public function setRequest(Request $request) {
        $this->request = $request;
    }

    /**
     * Sets the instance of the current user
     * @param \ride\library\security\model\User
     * @return null
     */
    public function setSecurityManager(SecurityManager $securityManager) {
        try {
            $this->user = $securityManager->getUser();
        } catch (Exception $exception) {
            $this->user = null;
        }
    }

    /**
     * Sets the directory to write the reports to
     * @param \ride\library\system\file\File
     * @return null
     */
    public function setDirectory(File $directory) {
        $this->directory = $directory;
    }


    /**
     * Sets the API key for Flare
     * @param string $recipient
     * @return null
     */
    public function setFlareKey($flareKey) {
        $this->flareKey = $flareKey;
    }

    /**
     * Gets the API key for Flare
     * @return string
     */
    public function getFlareKey() {
        return $this->flareKey;
    }

    /**
     * Logs an exception to a report file and sends it to Flare App
     * @param \Exception $exception Instance of the exception
     * @return string Id of the report
     */
    public function sendReport(Exception $exception) {
        $report = $this->createReport($exception, $this->request, $this->user);
        //Add report to LOG
        $this->writeReport($report);

        if (!$this->$this->flareKey) {
            return;
        }

        $flare = Flare::register($this->flareKey)->registerFlareHandlers();
        if ($this->user) {
            $flare->group('user', [
                'email'    => $this->user->getEmail(),
                'name'     => $this->user->getName(),
                'username' => $this->user->getUserName()
            ]);
        }

        $flare->createReport($exception);
        //Send the actual exception to Flare App
        $flare->report($exception);
    }

    /**
     * Gets a plain text error report for the provided exception
     * @param Exception $exception Instance of the exception
     * @param \ride\library\http\Request $request Request where the exception
     * occured
     * @param \ride\library\security\model\User $user Current user
     * @return string Plain text error report
     * @see getExceptionArray
     */
    protected function createReport(Exception $exception, Request $request = null, User $user = null) {
        $exception = $this->getExceptionArray($exception);

        $report = 'Date: ' . date('d/m/Y H:i:s', time()) . "\n";
        $report .= 'User: ' . ($user ? $user->getUsername() : 'anonymous') . "\n";

        if ($request) {
            $report .= "\nRequest:\n" . $request;

            if ($request->hasSession()) {
                $session = $request->getSession();

                $sessionVariables = $session->getAll();
                if ($sessionVariables) {
                    $report .= "\nSession (" . $session->getId() . "):\n";

                    foreach ($sessionVariables as $key => $value) {
                        if (is_object($value)) {
                            $report .= $key . ': ' . get_class($value) . "\n";
                        } else {
                            $report .= $key . ': ' . var_export($value, true) . "\n";
                        }
                    }
                }
            }
        }

        $report .= "\n";
        do {
            $report .= $exception['message'] . "\n";
            $report .= $exception['trace'] . "\n";

            if (isset($exception['cause'])) {
                $exception = $exception['cause'];

                $report .= "\nCaused by:\n\n";
            } else {
                $exception = null;
            }
        } while ($exception);

        return $report;
    }

    /**
     * Writes the report to a file in the directory
     * @param string $report Report to write
     * @return string Id of the request/error
     */
    protected function writeReport($report) {
        if ($this->log) {
            $id = $this->log->getId();
        } else {
            $id = substr(md5(time() . '-' . StringHelper::generate(8)), 0, 10);
        }

        $file = $this->getReportFile($id);
        $file->write($report);

        return $id;
    }

    /**
     * Gets the file for the report
     * @param string $id
     * @return \ride\library\system\file\File
     */
    protected function getReportFile($id) {
        return $this->directory->getChild('error-' . $id . '.txt');
    }

    /**
     * Parse the exception in a structured array for easy display
     * @param Exception $exception
     * @return array Array containing the values needed to display the exception
     */
    public function getExceptionArray(Exception $exception) {
        $message = $exception->getMessage();

        $array = array();
        $array['message'] = get_class($exception) . (!empty($message) ? ': ' . $message : '');
        $array['file'] = $exception->getFile() . ':' . $exception->getLine();
        $array['trace'] = $exception->getTraceAsString();
        $array['cause'] = null;

        if ($exception instanceof ValidationException) {
            $array['message'] .= $exception->getErrorsAsString();
        }

        $cause = $exception->getPrevious();
        if (!empty($cause)) {
            $array['cause'] = self::getExceptionArray($cause);
        }

        return $array;
    }

    /**
     * Gets the source snippet where the exception has been thrown
     * @param Exception $exception
     * @param integer $offset Number of lines before and after the throw to get
     * @return string Source snippet for the exception
     */
    public function getExceptionSource(Exception $exception, $offset = 5) {
        $source = file_get_contents($exception->getFile());
        $source = StringHelper::addLineNumbers($source);
        $source = explode("\n", $source);

        $line = $exception->getLine();

        $offsetAfter = ceil($offset / 2);
        $offsetBefore = $offset + ($offset - $offsetAfter);

        $sourceOffset = max(0, $line - $offsetBefore);
        $sourceLength = min(count($source), $line + $offsetAfter) - $sourceOffset;

        $source = array_slice($source, $sourceOffset, $sourceLength);
        $source = implode("\n", $source);

        return $source;
    }

}
