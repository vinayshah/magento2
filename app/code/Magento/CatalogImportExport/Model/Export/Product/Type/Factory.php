<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\CatalogImportExport\Model\Export\Product\Type;

use InvalidArgumentException;
use Magento\Framework\ObjectManagerInterface;

/**
 * Export product type factory
 * @package Magento\CatalogImportExport\Model\Export\Product\Type
 */
class Factory
{
    /**
     * Object Manager
     *
     * @var ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->_objectManager = $objectManager;
    }

    /**
     * @param string $className
     * @return AbstractType
     * @throws InvalidArgumentException
     */
    public function create($className)
    {
        if (!$className) {
            throw new InvalidArgumentException('Incorrect class name');
        }

        return $this->_objectManager->create($className);
    }
}
