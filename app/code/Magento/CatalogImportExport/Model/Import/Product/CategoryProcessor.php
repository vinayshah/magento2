<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\CatalogImportExport\Model\Import\Product;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Store\Model\Store;

/**
 * Class CategoryProcessor
 *
 * @api
 * @since 100.0.2
 */
class CategoryProcessor
{
    /**
     * Delimiter in category path.
     */
    const DELIMITER_CATEGORY = '/';

    /**
     * @var CollectionFactory
     */
    protected $categoryColFactory;

    /**
     * Categories text-path to ID hash.
     *
     * @var array
     */
    protected $categories = [];

    /**
     * Categories id to object cache.
     *
     * @var array
     */
    protected $categoriesCache = [];

    /**
     * Instance of catalog category factory.
     *
     * @var CategoryFactory
     */
    protected $categoryFactory;

    /**
     * Failed categories during creation
     *
     * @var   array
     * @since 100.1.0
     */
    protected $failedCategories = [];

    /**
     * @param CollectionFactory $categoryColFactory
     * @param CategoryFactory $categoryFactory
     */
    public function __construct(
        CollectionFactory $categoryColFactory,
        CategoryFactory $categoryFactory
    ) {
        $this->categoryColFactory = $categoryColFactory;
        $this->categoryFactory = $categoryFactory;
        $this->initCategories();
    }

    /**
     * Initialize categories
     *
     * @return $this
     */
    protected function initCategories()
    {
        if (empty($this->categories)) {
            $collection = $this->categoryColFactory->create();
            $collection->addAttributeToSelect('name')
                ->addAttributeToSelect('url_key')
                ->addAttributeToSelect('url_path');
            $collection->setStoreId(Store::DEFAULT_STORE_ID);
            /* @var $collection Collection */
            foreach ($collection as $category) {
                $structure = explode(self::DELIMITER_CATEGORY, $category->getPath());
                $pathSize = count($structure);

                $this->categoriesCache[$category->getId()] = $category;
                if ($pathSize > 1) {
                    $path = [];
                    for ($i = 1; $i < $pathSize; $i++) {
                        $name = $collection->getItemById((int)$structure[$i])->getName();
                        $path[] = $this->quoteDelimiter($name);
                    }
                    /**
                     * @var string $index
                     */
                    $index = $this->standardizeString(
                        implode(self::DELIMITER_CATEGORY, $path)
                    );
                    $this->categories[$index] = $category->getId();
                }
            }
        }
        return $this;
    }

    /**
     * Creates a category.
     *
     * @param string $name
     * @param int $parentId
     * @return int
     * @throws \Exception
     */
    protected function createCategory($name, $parentId)
    {
        /**
         * @var Category $category
         */
        $category = $this->categoryFactory->create();
        if (!($parentCategory = $this->getCategoryById($parentId))) {
            $parentCategory = $this->categoryFactory->create()->load($parentId);
        }
        $category->setPath($parentCategory->getPath());
        $category->setParentId($parentId);
        $category->setName($this->unquoteDelimiter($name));
        $category->setIsActive(true);
        $category->setIncludeInMenu(true);
        $category->setAttributeSetId($category->getDefaultAttributeSetId());
        $category->save();
        $this->categoriesCache[$category->getId()] = $category;
        return $category->getId();
    }

    /**
     * Returns ID of category by string path creating nonexistent ones.
     *
     * @param string $categoryPath
     * @return int
     * @throws \Exception
     */
    protected function upsertCategory($categoryPath)
    {
        /**
         * @var string $index
         */
        $index = $this->standardizeString($categoryPath);

        if (!isset($this->categories[$index])) {
            $pathParts = preg_split('~(?<!\\\)' . preg_quote(self::DELIMITER_CATEGORY, '~') . '~', $categoryPath);
            $parentId = Category::TREE_ROOT_ID;
            $path = '';

            foreach ($pathParts as $pathPart) {
                $path .= $this->standardizeString($pathPart);
                if (!isset($this->categories[$path])) {
                    $this->categories[$path] = $this->createCategory($pathPart, $parentId);
                }
                $parentId = $this->categories[$path];
                $path .= self::DELIMITER_CATEGORY;
            }
        }

        return $this->categories[$index];
    }

    /**
     * Returns IDs of categories by string path creating nonexistent ones.
     *
     * @param string $categoriesString
     * @param string $categoriesSeparator
     * @return array
     * @throws \Exception
     */
    public function upsertCategories($categoriesString, $categoriesSeparator)
    {
        $categoriesIds = [];
        $categories = explode($categoriesSeparator, $categoriesString);

        foreach ($categories as $category) {
            try {
                $categoriesIds[] = $this->upsertCategory($category);
            } catch (AlreadyExistsException $e) {
                $this->addFailedCategory($category, $e);
            }
        }

        return $categoriesIds;
    }

    /**
     * Add failed category
     *
     * @param string $category
     * @param AlreadyExistsException $exception
     *
     * @return $this
     */
    private function addFailedCategory($category, $exception)
    {
        $this->failedCategories[] =
            [
                'category' => $category,
                'exception' => $exception,
            ];
        return $this;
    }

    /**
     * Return failed categories
     *
     * @return array
     * @since  100.1.0
     */
    public function getFailedCategories()
    {
        return $this->failedCategories;
    }

    /**
     * Resets failed categories' array
     *
     * @return $this
     * @since  100.2.0
     */
    public function clearFailedCategories()
    {
        $this->failedCategories = [];
        return $this;
    }

    /**
     * Get category by Id
     *
     * @param int $categoryId
     *
     * @return Category|null
     */
    public function getCategoryById($categoryId)
    {
        return $this->categoriesCache[$categoryId] ?? null;
    }

    /**
     * Standardize a string.
     * For now it performs only a lowercase action, this method is here to include more complex checks in the future
     * if needed.
     *
     * @param string $string
     * @return string
     */
    private function standardizeString($string)
    {
        return mb_strtolower($string);
    }

    /**
     * Quoting delimiter character in string.
     *
     * @param string $string
     * @return string
     */
    private function quoteDelimiter($string)
    {
        return str_replace(self::DELIMITER_CATEGORY, '\\' . self::DELIMITER_CATEGORY, $string);
    }

    /**
     * Remove quoting delimiter in string.
     *
     * @param string $string
     * @return string
     */
    private function unquoteDelimiter($string)
    {
        return str_replace('\\' . self::DELIMITER_CATEGORY, self::DELIMITER_CATEGORY, $string);
    }
}
