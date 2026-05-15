<?php

class Category extends CategoryCore
{
    /*
     * PrestaShop's core searchByPath() forgets the parent category ID after
     * creating a new category, breaking nested category resolution on import.
     * This override carries the parent ID through each iteration so the full
     * path is assembled correctly.
     */

    public static function searchByPath($idLang, $path, $objectToCreate = false, $methodToCreate = false)
    {
        $categories = explode('/', trim($path));
        $category = $idParentCategory = false;

        if (is_array($categories) && count($categories)) {
            foreach ($categories as $idx => $categoryName) {
                if ($idParentCategory) {
                    $category = Category::searchByNameAndParentCategoryId($idLang, $categoryName, $idParentCategory);
                } else {
                    $category = Category::searchByName($idLang, $categoryName, true, true);
                }

                if (!$category && $objectToCreate && $methodToCreate) {
                    call_user_func_array(array($objectToCreate, $methodToCreate), array($idLang, $categoryName, $idParentCategory));
                    $selfPath = implode('/', array_slice($categories, 0, $idx + 1));
                    $category = Category::searchByPath($idLang, $selfPath);
                }
                if (isset($category['id_category']) && $category['id_category']) {
                    $idParentCategory = (int)$category['id_category'];
                }
            }
        }

        return $category;
    }
}
