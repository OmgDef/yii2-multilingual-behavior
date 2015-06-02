<?php

namespace tests\models\issue22;

use omgdef\multilingual\MultilingualBehavior;
use yii\db\ActiveRecord;

/**
 * Class CatalogType
 * @package app\modules\api\modules\catalogs\models
 */
class CatalogType extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName()
    {
        return '{{%catalogs_types}}';
    }

    /**
     * @return CatalogTypeQuery
     */
    public static function find()
    {
        return new CatalogTypeQuery(get_called_class());
    }

    /**
     * @return array
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        $behaviors['lang'] = [
            'class' => MultilingualBehavior::className(),
            'requireTranslations' => true,
            'languages' => ['ru-RU', 'en-US'],
            'languageField' => 'lang_id',
            'langForeignKey' => 'type_id',
            'tableName' => "{{%catalogs_types_lang}}",
            'attributes' => ['title'],
            'abridge' => false
        ];

        return $behaviors;
    }

    /**
     * @return array
     */
    public function rules()
    {
        return [
            [['title'], 'required'],
            [['title'], 'string', 'max' => 255],
            ['sort', 'integer']
        ];
    }
}
?>