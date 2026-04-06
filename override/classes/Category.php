<?php

class Category extends CategoryCore
{

    /*
     * Okay this little sucker fucks over our imports;  It forgets the parent after it creates the new category
     * This will help it remember.  Need testing for that path reassemble.
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
                    $selfPath = implode('/',array_slice($categories, 0 , $idx+1));
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