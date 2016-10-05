<?php

namespace ride\web\cms\content\text\variable;

use ride\library\cms\content\text\variable\AbstractVariableParser;
use ride\library\log\Log;

use ride\service\AssetService;

use \Exception;

/**
 * Implementation to parse asset variables
 */
class AssetVariableParser extends AbstractVariableParser {

    /**
     * Instance of the asset service
     * @var \ride\service\AssetService
     */
    protected $assetService;

    /**
     * Instance of the log
     * @var \ride\library\log\Log
     */
    protected $log;

    /**
     * Constructs a new asset variable service
     * @param \ride\service\AssetService $assetService
     * @return null
     */
    public function __construct(AssetService $assetService, Log $log) {
        $this->assetService = $assetService;
        $this->log = $log;
    }

    /**
     * Parses the provided variable
     * @param string $variable Full variable
     * @return mixed Value of the variable if resolved, null otherwise
     */
    public function parseVariable($variable) {
        $tokens = explode('.', $variable);

        $numTokens = count($tokens);
        if ($tokens[0] !== 'asset' || $numTokens < 2) {
            return null;
        }

        $asset = $tokens[1];

        $style = null;
        if (isset($tokens[2])) {
            $style = $tokens[2];
        }

        $class = null;
        if (isset($tokens[3])) {
            $class = $tokens[3];
        }

        try {
            $image = $this->assetService->getAssetHtml($asset, $style, $class);
        } catch (Exception $exception) {
            $this->log->logWarning('Could not render asset #' . $asset, $exception->getMessage());

            $image = null;
        }

        if ($image === null) {
            $image = '';
        }

        return $image;
    }

}
