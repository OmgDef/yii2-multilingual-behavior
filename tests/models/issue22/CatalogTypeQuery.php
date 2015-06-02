<?php

namespace tests\models\issue22;

use omgdef\multilingual\MultilingualTrait;
use yii\db\ActiveQuery;

/**
 * Class CatalogTypeQuery
 * @package app\modules\api\modules\catalogs\models
 */
class CatalogTypeQuery extends ActiveQuery
{
    use MultilingualTrait;
}

?>