<?php

namespace ride\web\cms\content\text\variable;

use ride\library\cms\content\text\variable\AbstractVariableParser;
use ride\library\image\exception\ImageException;

use ride\service\AssetService;

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
     * Constructs a new asset variable service
     * @param \ride\service\AssetService $assetService
     * @return null
     */
    public function __construct(AssetService $assetService) {
        $this->assetService = $assetService;
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
        } catch (ImageException $exception) {
            $image = null;
        }

        return $image;
    }

}
