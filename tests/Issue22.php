<?php
namespace tests;

use tests\models\issue22\CatalogType;
use Yii;


class Issue22 extends DatabaseTestCase
{
    protected static $migrationFileName = 'issue22.sql';

    public function testCreate()
    {
        $model = CatalogType::find()->multilingual()->where(['id' => 3])->one();
        var_dump($model->title_ru_ru, $model->title_en_us);
    }

    /**
     * @inheritdoc
     */
    public function getDataSet()
    {
        return $this->createFlatXMLDataSet(__DIR__ . '/data/issue22.xml');
    }
}
