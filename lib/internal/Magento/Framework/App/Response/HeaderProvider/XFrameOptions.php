<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Framework\App\Response\HeaderProvider;

use \Magento\Framework\App\Response\Http;

/**
 * Adds an X-FRAME-OPTIONS header to HTTP responses to safeguard against click-jacking.
 */
class XFrameOptions extends \Magento\Framework\App\Response\HeaderProvider\AbstractHeaderProvider
{
    /** Deployment config key for frontend x-frame-options header value */
    const DEPLOYMENT_CONFIG_X_FRAME_OPT = 'x-frame-options';

    /** Always send SAMEORIGIN in backend x-frame-options header */
    const BACKEND_X_FRAME_OPT = 'SAMEORIGIN';

    /**
     * x-frame-options Header name
     *
     * @var string
     */
    protected $name = Http::HEADER_X_FRAME_OPT;

    /**
     * x-frame-options header value
     *
     * @var string
     */
    protected $value = 'SAMEORIGIN';

    /**
     * @param string $xFrameOpt
     */
    public function __construct($xFrameOpt)
    {
        $this->value = $xFrameOpt;
    }
}
