<?php
namespace omgdef\multilingual;

use Yii;
use yii\db\ActiveQuery;

/**
 * Multilingual trait. Used in ActiveQuery to override @see ActiveQuery::createCommand()
 * Modify ActiveRecord query for multilingual support
 * @package app\modules\core\behaviors\multilingual
 */
trait MultilingualTrait
{
    /**
     * @var string the name of the lang field of the translation table. Default to 'language'.
     */
    public $languageField = 'language';

    /**
     * Scope for querying by languages
     * @param $language
     * @return ActiveQuery
     */
    public function localized($language = null)
    {
        if (!$language)
            $language = Yii::$app->language;

        $this->with(['translation' => function ($query) use ($language) {
            $query->andWhere($this->languageField . '=:language', [':language' => substr($language, 0, 2)]);
        }]);
        return $this;
    }

    /**
     * Scope for querying by all languages
     * @return ActiveQuery
     */
    public function multilingual()
    {
        $this->with('translations');
        return $this;
    }
}