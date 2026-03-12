<?php

namespace OpenCart\CLI\Support;

class ProductPayloadBuilder
{
    /**
     * @var OpenCartRuntime
     */
    private $runtime;

    /**
     * @var mixed
     */
    private $productModel;

    public function __construct(OpenCartRuntime $runtime)
    {
        $this->runtime = $runtime;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function buildCreatePayload(array $input): array
    {
        $registry = $this->runtime->registry();
        $config = $registry->get('config');
        $languageId = (int) $config->get('config_language_id');
        $name = (string) $input['name'];
        $description = (string) ($input['description'] ?? '');
        $metaTitle = (string) ($input['meta_title'] ?? $name);

        return [
            'model' => (string) $input['model'],
            'sku' => (string) ($input['sku'] ?: $input['model']),
            'upc' => '',
            'ean' => '',
            'jan' => '',
            'isbn' => '',
            'mpn' => '',
            'location' => '',
            'quantity' => (int) ($input['quantity'] ?? 0),
            'minimum' => 1,
            'subtract' => (int) ($input['subtract'] ?? 1),
            'stock_status_id' => (int) ($config->get('config_stock_status_id') ?: 7),
            'date_available' => date('Y-m-d'),
            'manufacturer_id' => (int) ($input['manufacturer_id'] ?? 0),
            'shipping' => 1,
            'price' => (float) $input['price'],
            'points' => 0,
            'weight' => (float) ($input['weight'] ?? 0),
            'weight_class_id' => (int) ($config->get('config_weight_class_id') ?: 1),
            'length' => 0,
            'width' => 0,
            'height' => 0,
            'length_class_id' => (int) ($config->get('config_length_class_id') ?: 1),
            'status' => (int) $input['status'],
            'tax_class_id' => 0,
            'sort_order' => 0,
            'image' => (string) ($input['image'] ?? ''),
            'product_description' => [
                $languageId => [
                    'name' => $name,
                    'description' => $description,
                    'tag' => '',
                    'meta_title' => $metaTitle,
                    'meta_description' => '',
                    'meta_keyword' => '',
                ],
            ],
            'product_store' => [0],
            'product_attribute' => [],
            'product_option' => [],
            'product_recurring' => [],
            'product_discount' => [],
            'product_special' => [],
            'product_image' => [],
            'product_download' => [],
            'product_category' => $this->resolveCategoryIds($input['category'] ?? null),
            'product_filter' => [],
            'product_related' => [],
            'product_reward' => [],
            'product_seo_url' => [],
            'product_layout' => [],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function loadEditablePayload(int $productId): ?array
    {
        $model = $this->productModel();
        $product = $model->getProduct($productId);

        if (!$product) {
            return null;
        }

        $payload = $product;
        $payload['product_description'] = $model->getProductDescriptions($productId);
        $payload['product_category'] = $model->getProductCategories($productId);
        $payload['product_filter'] = $model->getProductFilters($productId);
        $payload['product_attribute'] = $model->getProductAttributes($productId);
        $payload['product_option'] = $model->getProductOptions($productId);
        $payload['product_image'] = $model->getProductImages($productId);
        $payload['product_discount'] = $model->getProductDiscounts($productId);
        $payload['product_special'] = $model->getProductSpecials($productId);
        $payload['product_reward'] = $model->getProductRewards($productId);
        $payload['product_download'] = $model->getProductDownloads($productId);
        $payload['product_store'] = $model->getProductStores($productId);
        $payload['product_seo_url'] = $model->getProductSeoUrls($productId);
        $payload['product_layout'] = $model->getProductLayouts($productId);
        $payload['product_related'] = $model->getProductRelated($productId);
        $payload['product_recurring'] = $model->getRecurrings($productId);

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $updates
     * @return array<string, mixed>
     */
    public function applyUpdates(array $payload, array $updates): array
    {
        $languageId = (int) $this->runtime->registry()->get('config')->get('config_language_id');
        if (!isset($payload['product_description'][$languageId])) {
            $payload['product_description'][$languageId] = [
                'name' => $payload['name'] ?? '',
                'description' => '',
                'tag' => '',
                'meta_title' => $payload['name'] ?? '',
                'meta_description' => '',
                'meta_keyword' => '',
            ];
        }

        if (array_key_exists('name', $updates) && $updates['name'] !== null) {
            $payload['product_description'][$languageId]['name'] = (string) $updates['name'];
        }

        if (array_key_exists('description', $updates) && $updates['description'] !== null) {
            $payload['product_description'][$languageId]['description'] = (string) $updates['description'];
        }

        if (array_key_exists('meta_title', $updates) && $updates['meta_title'] !== null) {
            $payload['product_description'][$languageId]['meta_title'] = (string) $updates['meta_title'];
        }

        if (array_key_exists('model', $updates) && $updates['model'] !== null) {
            $payload['model'] = (string) $updates['model'];
        }

        if (array_key_exists('sku', $updates) && $updates['sku'] !== null) {
            $payload['sku'] = (string) $updates['sku'];
        }

        if (array_key_exists('price', $updates) && $updates['price'] !== null) {
            $payload['price'] = (float) $updates['price'];
        }

        if (array_key_exists('quantity', $updates) && $updates['quantity'] !== null) {
            $payload['quantity'] = (int) $updates['quantity'];
        }

        if (array_key_exists('status', $updates) && $updates['status'] !== null) {
            $payload['status'] = (int) $updates['status'];
        }

        if (array_key_exists('image', $updates) && $updates['image'] !== null) {
            $payload['image'] = (string) $updates['image'];
        }

        if (array_key_exists('subtract', $updates) && $updates['subtract'] !== null) {
            $payload['subtract'] = (int) $updates['subtract'];
        }

        if (array_key_exists('manufacturer_id', $updates) && $updates['manufacturer_id'] !== null) {
            $payload['manufacturer_id'] = (int) $updates['manufacturer_id'];
        }

        if (array_key_exists('category', $updates) && $updates['category'] !== null) {
            $payload['product_category'] = $this->resolveCategoryIds($updates['category']);
        }

        return $payload;
    }

    /**
     * @param mixed $value
     * @return int[]
     */
    public function resolveCategoryIds($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $db = $this->runtime->database();
        $prefix = $this->runtime->getDatabasePrefix();
        $languageId = (int) $this->runtime->registry()->get('config')->get('config_language_id');
        $values = is_array($value) ? $value : preg_split('/\s*,\s*/', (string) $value);
        $resolved = [];

        foreach ($values as $item) {
            if ($item === null || $item === '') {
                continue;
            }

            if (is_numeric($item)) {
                $query = $db->query(
                    "SELECT category_id FROM `" . $prefix . "category` WHERE category_id = '" . (int) $item . "'"
                );
            } else {
                $query = $db->query(
                    "SELECT category_id FROM `" . $prefix . "category_description` " .
                    "WHERE language_id = '" . $languageId . "' AND name = '" . $db->escape((string) $item) . "'"
                );
            }

            if (!empty($query->row['category_id'])) {
                $resolved[] = (int) $query->row['category_id'];
            }
        }

        return array_values(array_unique($resolved));
    }

    /**
     * @return mixed
     */
    public function productModel()
    {
        if ($this->productModel === null) {
            $this->productModel = $this->runtime->model('catalog/product');
        }

        return $this->productModel;
    }
}
