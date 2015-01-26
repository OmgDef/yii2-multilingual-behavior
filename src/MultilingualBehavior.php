<?php
namespace omgdef\multilingual;

use Yii;
use yii\base\Behavior;
use yii\base\UnknownPropertyException;
use yii\base\InvalidConfigException;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\validators\Validator;

class MultilingualBehavior extends Behavior
{
    /**
     * Multilingual attributes
     * @var array
     */
    public $attributes;

    /**
     * Available languages
     * It can be a simple array: array('fr', 'en') or an associative array: array('fr' => 'Français', 'en' => 'English')
     * For associative arrays, only the keys will be used.
     * @var array
     */
    public $languages;

    /**
     * @var string the default language.
     * Example: 'en'.
     */
    public $defaultLanguage;

    /**
     * @var string the name of the translation table
     */
    public $tableName;

    /**
     * @var string the name of translation model class.
     */
    public $langClassName;

    /**
     * @var string the name of the foreign key field of the translation table related to base model table.
     */
    public $langForeignKey;

    /**
     * @var string the prefix of the localized attributes in the lang table. Here to avoid collisions in queries.
     * In the translation table, the columns corresponding to the localized attributes have to be name like this: 'l_[name of the attribute]'
     * and the id column (primary key) like this : 'l_id'
     * Default to ''.
     */
    public $localizedPrefix = '';

    /**
     * @var string the name of the lang field of the translation table. Default to 'language'.
     */
    public $languageField = 'language';

    /**
     * @var boolean whether to force overwrite of the default language value with translated value even if it is empty.
     * Default to false.
     */
    public $forceOverwrite = false;

    /**
     * @var boolean whether to force deletion of the associated translations when a base model is deleted.
     * Not needed if using foreign key with 'on delete cascade'.
     * Default to true.
     */
    public $forceDelete = true;

    /**
     * @var boolean whether to dynamically create translation model class.
     * If true, the translation model class will be generated on runtime with the use of the eval() function so no additionnal php file is needed.
     * See {@link createLangClass()}
     * Default to true.
     */
    public $dynamicLangClass = true;

    private $_currentLanguage;
    private $_ownerClassName;
    private $_ownerPrimaryKey;
    private $_langClassShortName;
    private $_ownerClassShortName;
    private $_langAttributes = [];

    /**
     * @var array excluded validators
     */
    private $_excludedValidators = ['unique'];

    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_FIND => 'afterFind',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterUpdate',
            ActiveRecord::EVENT_AFTER_INSERT => 'afterInsert',
            ActiveRecord::EVENT_AFTER_DELETE => 'afterDelete',
        ];
    }

    /**
     * @inheritdoc
     */
    public function attach($owner)
    {
        parent::attach($owner);

        if (!$this->languages || !is_array($this->languages)) {
            throw new InvalidConfigException('Please specify array of available languages for the ' . get_class($this) . ' in the '
                . get_class($this->owner) . ' or in the application parameters', 101);
        } elseif (array_values($this->languages) !== $this->languages) { //associative array
            $this->languages = array_keys($this->languages);
        }

        $languages = [];
        foreach ($this->languages as $language) {
            $languages[] = $this->getLanguageBaseName($language, 0, 2);
        }

        $this->languages = $languages;

        if (!$this->defaultLanguage) {
            $language = isset(Yii::$app->params['defaultLanguage']) && Yii::$app->params['defaultLanguage'] ?
                Yii::$app->params['defaultLanguage'] : Yii::$app->language;
            $this->defaultLanguage = $this->getLanguageBaseName($language, 0, 2);
        }

        if (!$this->_currentLanguage) {
            $this->_currentLanguage = $this->getLanguageBaseName(Yii::$app->language, 0, 2);
        }

        if (!$this->attributes) {
            throw new InvalidConfigException('Please specify multilingual attributes for the ' . get_class($this) . ' in the '
                . get_class($this->owner), 103);
        }

        if (!$this->langClassName) {
            $this->langClassName = get_class($this->owner) . 'Lang';
        }


        $this->_langClassShortName = $this->getShortClassName($this->langClassName);
        $this->_ownerClassName = get_class($this->owner);
        $this->_ownerClassShortName = $this->getShortClassName($this->_ownerClassName);

        /** @var ActiveRecord $className */
        $className = $this->_ownerClassName;
        $this->_ownerPrimaryKey = $className::primaryKey()[0];

        if (!isset($this->langForeignKey)) {
            throw new InvalidConfigException('Please specify langForeignKey for the ' . get_class($this) . ' in the '
                . get_class($this->owner), 105);
        }

        /** @var ActiveRecord $owner */
        $owner = $this->owner;

        $rules = $owner->rules();
        $validators = $owner->getValidators();

        foreach ($this->languages as $language) {
            foreach ($this->attributes as $attribute) {
                foreach ($rules as $rule) {
                    if (is_array($rule[0]))
                        $rule_attributes = $rule[0];
                    else
                        $rule_attributes = [$rule[0]];

                    if (!in_array($rule[1], $this->_excludedValidators)) {
                        if ((is_array($rule_attributes) && in_array($attribute, $rule_attributes)) || (!is_array($rule_attributes) && $rule_attributes == $attribute)) {
                            if ($rule[1] !== 'required' || $this->forceOverwrite) {
                                if (isset($rule['skipOnEmpty']) && !$rule['skipOnEmpty'])
                                    $rule['skipOnEmpty'] = !$this->forceOverwrite;
                                $validators[] = Validator::createValidator($rule[1], $owner, $attribute . '_' . $language, array_slice($rule, 2));
                            } elseif ($rule[1] === 'required') {
                                //We add a safe rule in case the attribute has only a 'required' validation rule assigned
                                //and forceOverWrite == false
                                $validators[] = Validator::createValidator('safe', $owner, $attribute . '_' . $language, array_slice($rule, 2));
                            }
                        }
                    }
                }
            }
        }

        if ($this->dynamicLangClass) {
            $this->createLangClass();
        }

        $owner = new $this->langClassName;
        foreach ($this->languages as $lang) {
            foreach ($this->attributes as $attribute) {
                $ownerFiled = $this->localizedPrefix . $attribute;
                $this->setLangAttribute($attribute . '_' . $lang, $owner->{$ownerFiled});
            }
        }
    }

    public function createLangClass()
    {
        if (!class_exists($this->langClassName, false)) {
            $namespace = substr($this->langClassName, 0, strrpos($this->langClassName, '\\'));
            eval('
            namespace ' . $namespace . ';
            use yii\db\ActiveRecord;
            class ' . $this->_langClassShortName . ' extends ActiveRecord
            {
                public static function tableName()
                {
                    return \'' . $this->tableName . '\';
                }

                public function ' . strtolower($this->_ownerClassShortName) . '()
                {
                    return $this->hasOne(\'' . $this->_ownerClassName . '\', [\'' . $this->_ownerPrimaryKey . '\' => \'
                    ' . $this->langForeignKey . '\']);
                }
            }');
        }
    }

    /**
     * Relation to model translations
     * @return ActiveQuery
     */
    public function getTranslations()
    {
        return $this->owner->hasMany($this->langClassName, [$this->langForeignKey => $this->_ownerPrimaryKey]);
    }

    /**
     * Relation to model translation
     * @param $language
     * @return ActiveQuery
     */
    public function getTranslation($language = null)
    {
        $language = $language ? $language : $this->_currentLanguage;
        return $this->owner->hasMany($this->langClassName, [$this->langForeignKey => $this->_ownerPrimaryKey])
            ->where([$this->languageField => $language]);
    }

    /**
     * Handle 'afterFind' event of the owner.
     */
    public function afterFind()
    {
        /** @var ActiveRecord $owner */
        $owner = $this->owner;

        if ($owner->isRelationPopulated('translations')) {

            $related = $owner->getRelatedRecords();

            foreach ($this->languages as $lang) {
                foreach ($this->attributes as $attribute) {

                    $attributeValue = null;
                    if ($related['translations']) {
                        $translations = $this->indexByLanguage($related['translations']);
                        foreach ($translations as $translation) {
                            if ($translation->{$this->languageField} == $lang) {
                                $attributeName = $this->localizedPrefix . $attribute;
                                $attributeValue = isset($translation->$attributeName) ? $translation->$attributeName : null;
                                $this->setLangAttribute($attribute . '_' . $lang, $attributeValue);

                            }
                        }
                    }
                }
            }
        } elseif ($owner->isRelationPopulated('translation')) {
            $related = $owner->getRelatedRecords();

            if ($related['translation']) {
                $translation = $related['translation'][0];

                foreach ($this->attributes as $attribute) {
                    $attribute_name = $this->localizedPrefix . $attribute;
                    if ($translation->$attribute_name || $this->forceOverwrite) {
                        $owner->setAttribute($attribute, $translation->$attribute_name);
                        $owner->setOldAttribute($attribute, $translation->$attribute_name);
                    }
                }
            }
        }
    }

    /**
     * Handle 'afterInsert' event of the owner.
     */
    public function afterInsert()
    {
        $this->saveTranslations();
    }

    /**
     * Handle 'afterUpdate' event of the owner.
     */
    public function afterUpdate()
    {
        /** @var ActiveRecord $owner */
        $owner = $this->owner;

        if ($owner->isRelationPopulated('translations')) {
            $translations = $this->indexByLanguage($owner->getRelatedRecords()['translations']);
            $this->saveTranslations($translations);
        }
    }

    /**
     * Handle 'afterDelete' event of the owner.
     */
    public function afterDelete()
    {
        if ($this->forceDelete) {
            /** @var ActiveRecord $owner */
            $owner = $this->owner;
            $owner->unlinkAll('translations', true);
        }
    }

    /**
     * @param array $translations
     */
    private function saveTranslations($translations = [])
    {
        /** @var ActiveRecord $owner */
        $owner = $this->owner;

        foreach ($this->languages as $lang) {
            $defaultLanguage = $lang == $this->defaultLanguage;
            if (!isset($translations[$lang])) {
                $translation = new $this->langClassName;
                $translation->{$this->languageField} = $lang;
                $translation->{$this->langForeignKey} = $owner->getPrimaryKey();
            } else {
                $translation = $translations[$lang];
            }
            foreach ($this->attributes as $attribute) {
                if ($defaultLanguage)
                    $value = $owner->$attribute;
                else
                    $value = $this->getLangAttribute($attribute . "_" . $lang);

                if ($value !== null) {
                    $field = $this->localizedPrefix . $attribute;
                    $translation->$field = $value;
                }
            }
            $translation->save(false);
        }
    }

    /**
     * @inheritdoc
     */
    public function canGetProperty($name, $checkVars = true)
    {
        return method_exists($this, 'get' . $name) || $checkVars && property_exists($this, $name)
        || $this->hasLangAttribute($name);
    }

    /**
     * @inheritdoc
     */
    public function canSetProperty($name, $checkVars = true)
    {
        return $this->hasLangAttribute($name);
    }

    /**
     * @inheritdoc
     */
    public function __get($name)
    {
        try {
            return parent::__get($name);
        } catch (UnknownPropertyException $e) {
            if ($this->hasLangAttribute($name)) return $this->getLangAttribute($name);
            // @codeCoverageIgnoreStart
            else throw $e;
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        try {
            parent::__set($name, $value);
        } catch (UnknownPropertyException $e) {
            if ($this->hasLangAttribute($name)) $this->setLangAttribute($name, $value);
            // @codeCoverageIgnoreStart
            else throw $e;
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * @inheritdoc
     * @codeCoverageIgnore
     */
    public function __isset($name)
    {
        if (!parent::__isset($name)) {
            return $this->hasLangAttribute($name);
        } else {
            return true;
        }
    }

    /**
     * Whether an attribute exists
     * @param string $name the name of the attribute
     * @return boolean
     */
    public function hasLangAttribute($name)
    {
        return array_key_exists($name, $this->_langAttributes);
    }

    /**
     * @param string $name the name of the attribute
     * @return string the attribute value
     */
    public function getLangAttribute($name)
    {
        return $this->hasLangAttribute($name) ? $this->_langAttributes[$name] : null;
    }

    /**
     * @param string $name the name of the attribute
     * @param string $value the value of the attribute
     */
    public function setLangAttribute($name, $value)
    {
        $this->_langAttributes[$name] = $value;
    }

    /**
     * @param $records
     * @return array
     */
    protected function indexByLanguage($records)
    {
        $sorted = array();
        foreach ($records as $record) {
            $sorted[$record->{$this->languageField}] = $record;
        }
        unset($records);
        return $sorted;
    }

    /**
     * @param $language
     * @return string
     */
    private function getLanguageBaseName($language)
    {
        return substr($language, 0, 2);
    }

    /**
     * @param $className
     * @return string
     */
    private function getShortClassName($className)
    {
        return substr($className, strrpos($className, '\\') + 1);
    }
}