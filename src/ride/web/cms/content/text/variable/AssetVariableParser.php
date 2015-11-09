<?php

namespace ride\web\cms\content\text\variable;

use ride\library\cms\content\text\variable\AbstractVariableParser;

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

        $asset = $this->assetService->getAsset($tokens[1]);
        if (!$asset) {
            return null;
        }

        $style = null;
        if (isset($tokens[2])) {
            $style = $tokens[2];
        }

        return '<img src="' . $this->assetService->getAssetUrl($asset, $style) . '" title="' . htmlentities($asset->getName()) . '">';
    }

}
