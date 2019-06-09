<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\CatalogImportExport\Model\Import\Product;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\EntityManager\MetadataPool;

/**
 * Class SkuProcessor
 *
 * @api
 * @since 100.0.2
 */
class SkuProcessor
{
    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @var array
     */
    protected $oldSkus;

    /**
     * Dry-runned products information from import file.
     *
     * [SKU] => array(
     *     'type_id'        => (string) product type
     *     'attr_set_id'    => (int) product attribute set ID
     *     'entity_id'      => (int) product ID (value for new products will be set after entity save)
     *     'attr_set_code'  => (string) attribute set code
     * )
     *
     * @var array
     */
    protected $newSkus;

    /**
     * @var array
     */
    protected $productTypeModels;

    /**
     * Product metadata pool
     *
     * @var MetadataPool
     */
    private $metadataPool;

    /**
     * Product entity link field
     *
     * @var string
     */
    private $productEntityLinkField;

    /**
     * Product entity identifier field
     *
     * @var string
     */
    private $productEntityIdentifierField;

    /**
     * @param ProductFactory $productFactory
     */
    public function __construct(
        ProductFactory $productFactory
    ) {
        $this->productFactory = $productFactory;
    }

    /**
     * @param array $typeModels
     * @return $this
     */
    public function setTypeModels($typeModels)
    {
        $this->productTypeModels = $typeModels;
        return $this;
    }

    /**
     * Get old skus array.
     *
     * @return array
     * @throws Exception
     */
    public function getOldSkus()
    {
        if (!$this->oldSkus) {
            $this->oldSkus = $this->_getSkus();
        }
        return $this->oldSkus;
    }

    /**
     * Reload old skus.
     *
     * @return $this
     * @throws Exception
     */
    public function reloadOldSkus()
    {
        $this->oldSkus = $this->_getSkus();

        return $this;
    }

    /**
     * @param string $sku
     * @param array $data
     * @return $this
     */
    public function addNewSku($sku, $data)
    {
        $sku = strtolower($sku);
        $this->newSkus[$sku] = $data;
        return $this;
    }

    /**
     * @param string $sku
     * @param string $key
     * @param mixed $data
     * @return $this
     */
    public function setNewSkuData($sku, $key, $data)
    {
        $sku = strtolower($sku);
        if (isset($this->newSkus[$sku])) {
            $this->newSkus[$sku][$key] = $data;
        }
        return $this;
    }

    /**
     * @param null|string $sku
     * @return array|null
     */
    public function getNewSku($sku = null)
    {
        if ($sku !== null) {
            $sku = strtolower($sku);
            return $this->newSkus[$sku] ?? null;
        }
        return $this->newSkus;
    }

    /**
     * Get skus data.
     *
     * @return array
     * @throws Exception
     */
    protected function _getSkus()
    {
        $oldSkus = [];
        $columns = ['entity_id', 'type_id', 'attribute_set_id', 'sku'];
        if ($this->getProductEntityLinkField() != $this->getProductIdentifierField()) {
            $columns[] = $this->getProductEntityLinkField();
        }
        foreach ($this->productFactory->create()->getProductEntitiesInfo($columns) as $info) {
            $typeId = $info['type_id'];
            $sku = strtolower($info['sku']);
            $oldSkus[$sku] = [
                'type_id' => $typeId,
                'attr_set_id' => $info['attribute_set_id'],
                'entity_id' => $info['entity_id'],
                'supported_type' => isset($this->productTypeModels[$typeId]),
                $this->getProductEntityLinkField() => $info[$this->getProductEntityLinkField()],
            ];
        }
        return $oldSkus;
    }

    /**
     * Get product metadata pool
     *
     * @return MetadataPool
     */
    private function getMetadataPool()
    {
        if (!$this->metadataPool) {
            $this->metadataPool = ObjectManager::getInstance()
                ->get(MetadataPool::class);
        }
        return $this->metadataPool;
    }

    /**
     * Get product entity link field
     *
     * @return string
     * @throws Exception
     */
    private function getProductEntityLinkField()
    {
        if (!$this->productEntityLinkField) {
            $this->productEntityLinkField = $this->getMetadataPool()
                ->getMetadata(ProductInterface::class)
                ->getLinkField();
        }
        return $this->productEntityLinkField;
    }

    /**
     * Get product entity identifier field
     *
     * @return string
     * @throws Exception
     */
    private function getProductIdentifierField()
    {
        if (!$this->productEntityIdentifierField) {
            $this->productEntityIdentifierField = $this->getMetadataPool()
                ->getMetadata(ProductInterface::class)
                ->getIdentifierField();
        }
        return $this->productEntityIdentifierField;
    }
}
