<?php
/**
 * Video
 *
 * @copyright Copyright © 2019 Firebear Studio. All rights reserved.
 * @author    Firebear Studio <fbeardev@gmail.com>
 */

namespace Magento\CatalogImportExport\Model\Import\Product\Validator;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Url\Validator;

class Video extends AbstractImportValidator
{
    /**
     * Video URL
     */
    const VIDEO_URL = 'video_url';

    /**
     * @var \Magento\Framework\Url\Validator
     */
    private $validator;

    /**
     * @param Validator $validator The url validator
     */
    public function __construct(Validator $validator = null)
    {
        $this->validator = $validator ?: ObjectManager::getInstance()->get(Validator::class);
    }

    /**
     * Returns true if and only if $value meets the validation requirements
     *
     * If $value fails validation, then this method returns false, and
     * getMessages() will return an array of messages that explain why the
     * validation failed.
     *
     * @param mixed $value
     *
     * @return boolean
     */
    public function isValid($value)
    {
        $this->_clearMessages();
        $valid = true;
        if (isset($value[self::VIDEO_URL]) && $value[self::VIDEO_URL] !== '') {
            foreach (explode($this->context->getMultipleValueSeparator(), $value[self::VIDEO_URL]) as $video) {
                if (!$this->checkValidUrl($video) && !$this->validator->isValid($video)) {
                    $this->_addMessages(
                        [
                            sprintf(
                                $this->context->retrieveMessageTemplate(self::ERROR_INVALID_MEDIA_URL_OR_PATH),
                                self::VIDEO_URL
                            ),
                        ]
                    );
                    $valid = false;
                }
                break;
            }
        }
        return $valid;
    }

    /**
     * @param $url
     *
     * @return bool
     */
    protected function checkValidUrl($url)
    {
        $valid = false;
        if (strpos($url, 'youtube') > 0) {
            $valid = true;
        } elseif (strpos($url, 'vimeo') > 0) {
            $valid = true;
        }
        return $valid;
    }
}
