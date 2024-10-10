<?php

namespace Tigress;

use Exception;
use Google\Service\Script;
use Google\Service\Script\ExecutionRequest;

/**
 * Class GoogleApiScript (PHP version 8.3)
 *
 * @author Rudy Mas <rudy.mas@rudymas.be>
 * @copyright 2024, rudymas.be. (http://www.rudymas.be/)
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3 (GPL-3.0)
 * @version 1.0.0
 * @lastmodified 2024-10-10
 * @package Tigress\GoogleApiScript
 */
class GoogleApiScript extends GoogleApiAuth
{
    private array $params = [];
    private string $scriptId;

    /**
     * Get the version of the class.
     *
     * @return string
     */
    public static function version(): string
    {
        return '1.0.0';
    }

    /**
     * Replace the data in the Google Sheet.
     *
     * @param string $scriptFunction
     * @return void
     */
    public function replaceData(string $scriptFunction): void
    {
        $service = new Script($this->client);
        $request = new ExecutionRequest();
        $request->setFunction($scriptFunction);
        $request->setParameters($this->getParams());

        try {
            $response = $service->scripts->run($this->getScriptId(), $request);
            if ($response->getError()) {
                $error = $response->getError()['details'][0];
                printf("Script error message: %s\n", $error['errorMessage']);

                if (array_key_exists('scriptStackTraceElements', $error)) {
                    // There may not be a stacktrace if the script didn't start executing.
                    print "Script error stacktrace:\n";
                    foreach ($error['scriptStackTraceElements'] as $trace) {
                        printf("\t%s: %d\n", $trace['function'], $trace['lineNumber']);
                    }
                }
            }
        } catch (Exception $e) {
            echo 'Caught exception: ', $e->getMessage(), "\n";
        }
    }

    /**
     * Get the parameters.
     *
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Set the parameters.
     *
     * @param array $params
     * @return void
     */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    /**
     * Get the script id.
     *
     * @return string
     */
    public function getScriptId(): string
    {
        return $this->scriptId;
    }

    /**
     * Set the script id.
     *
     * @param string $scriptId
     * @return void
     */
    public function setScriptId(string $scriptId): void
    {
        $this->scriptId = $scriptId;
    }
}