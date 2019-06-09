<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\CatalogImportExport\Model\Import\Product;

use Magento\CatalogImportExport\Model\Import\Proxy\Product\ResourceModel;
use Magento\CatalogImportExport\Model\Import\Proxy\Product\ResourceModelFactory;

/**
 * Class ImageTypeProcessor
 * @package Magento\CatalogImportExport\Model\Import\Product
 */
class ImageTypeProcessor
{
    /**
     * @var ResourceModelFactory
     */
    private $resourceFactory;

    /**
     * @param ResourceModelFactory $resourceFactory
     */
    public function __construct(
        ResourceModelFactory $resourceFactory
    ) {
        $this->resourceFactory = $resourceFactory;
    }

    /**
     * @return array
     */
    public function getImageTypes()
    {
        $imageKeys = [];
        /**
         * @var ResourceModel $resource
         */
        $resource = $this->resourceFactory->create();
        $connection = $resource->getConnection();
        $select = $connection->select();
        $select->from(
            $resource->getTable('eav_attribute'),
            ['code' => 'attribute_code']
        );
        $select->where(
            'frontend_input = :frontend_input'
        );
        $bind = [':frontend_input' => 'media_image'];

        $imageKeys = $connection->fetchCol($select, $bind);
        $imageKeys[] = '_media_image';

        return $imageKeys;
    }
}
