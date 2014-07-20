<?php
namespace omgdef\multilingual;

use Yii;
use yii\base\Behavior;
use yii\base\Exception;
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
     * @var boolean wether to force overwrite of the default language value with translated value even if it is empty.
     * Used only for {@link localizedRelation}.
     * Default to false.
     */
    public $forceOverwrite = false;

    private $_currentLanguage;

    private $_ownerClassName;

    private $_ownerPrimaryKey;

    private $_langAttributes = array();

    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_FIND => 'afterFind',
            ActiveRecord::EVENT_INIT => 'afterInit',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterUpdate',
            ActiveRecord::EVENT_AFTER_INSERT => 'afterInsert',
            ActiveRecord::EVENT_BEFORE_VALIDATE => 'beforeValidate',
        ];
    }

    /**
     * @inheritdoc
     */
    public function attach($owner)
    {
        parent::attach($owner);
        $this->configure();
    }

    /**
     * Init behaviour configuration
     */
    public function configure()
    {
        if (!$this->languages) {
            throw new Exception('Please specify array of available languages for the ' . get_class($this) . ' in the '
                . get_class($this->owner) . ' or in the application parameters');
        } elseif (array_values($this->languages) !== $this->languages) { //associative array
            $this->languages = array_keys($this->languages);
        }

        if (!$this->defaultLanguage) {
            $language = isset(Yii::$app->params['defaultLanguage']) && Yii::$app->params['defaultLanguage'] ?
                Yii::$app->params['defaultLanguage'] : substr(Yii::$app->language, 0, 2);
            $this->defaultLanguage = $language;
        }

        if (!$this->_currentLanguage) {
            $this->_currentLanguage = substr(Yii::$app->language, 0, 2);
        }

        if (!$this->attributes) {
            throw new Exception('Please specify multilingual attributes for the ' . get_class($this) . ' in the '
                . get_class($this->owner));
        }

        if (!$this->langClassName || !class_exists($this->langClassName)) {
            throw new Exception('Please specify langClassName for the ' . get_class($this) . ' in the '
                . get_class($this->owner));
        }

        $this->_ownerClassName = get_class($this->owner);

        /** @var ActiveRecord $className */
        $className = $this->_ownerClassName;
        $ownerPrimaryKey = $className::primaryKey();
        if (!isset($ownerPrimaryKey[0])) {
            throw new InvalidConfigException($this->_ownerClassName . ' must have a primary key.');
        }
        $this->_ownerPrimaryKey = $ownerPrimaryKey[0];

        if (!isset($this->langForeignKey)) {
            throw new Exception('Please specify langForeignKey for the ' . get_class($this) . ' in the '
                . get_class($this->owner));
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
                        $rule_attributes = array_map('trim', explode(',', $rule[0]));

                    if (in_array($attribute, $rule_attributes)) {
                        if ($rule[1] !== 'required' || $this->forceOverwrite) {
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
            ->where($this->languageField . '=:language', [':language' => $language]);
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
                            if ($translation->language == $lang) {
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
                    if ($translation->$attribute || $this->forceOverwrite) {
                        $owner->setAttribute($attribute, $translation->$attribute);
                        $owner->setOldAttribute($attribute, $translation->$attribute);
                    }
                }
            }
        }
    }

    /**
     * Handle 'afterInit' event of the owner.
     */
    public function afterInit()
    {
        if ($this->owner->isNewRecord) {
            $owner = new $this->langClassName;
            foreach ($this->languages as $lang) {
                foreach ($this->attributes as $attribute) {
                    $ownerFiled = $this->localizedPrefix . $attribute;
                    $this->setLangAttribute($attribute . '_' . $lang, $owner->{$ownerFiled});
                }
            }
        }
    }

    /**
     * Handle 'beforeValidate' event of the owner.
     */
    public function beforeValidate()
    {
        if ($this->owner->isNewRecord && $this->forceOverwrite) {
            foreach ($this->attributes as $attribute) {
                $lAttr = $attribute . "_" . $this->defaultLanguage;
                $this->owner->$lAttr = $this->owner->$attribute;
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

        $translations = [];
        if ($owner->isRelationPopulated('translations'))
            $translations = $this->indexByLanguage($owner->getRelatedRecords()['translations']);

        $this->saveTranslations($translations);
    }

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
        if (method_exists($this, 'set' . $name) || $checkVars && property_exists($this, $name)) {
            return true;
        } else {
            foreach ($this->languages as $lang) {
                foreach ($this->attributes as $attribute) {
                    if ($name == $attribute . '_' . $lang)
                        return true;
                }
            }
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function __get($name)
    {
        try {
            return parent::__get($name);
        } catch (Exception $e) {
            if ($this->hasLangAttribute($name)) return $this->getLangAttribute($name);
            else throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        try {
            parent::__set($name, $value);
        } catch (Exception $e) {
            if ($this->hasLangAttribute($name)) $this->setLangAttribute($name, $value);
            else throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function __isset($name)
    {
        if (!parent::__isset($name)) {
            return ($this->hasLangAttribute($name));
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
    private function indexByLanguage($records)
    {
        $sorted = array();
        foreach ($records as $record) {
            $sorted[$record->{$this->languageField}] = $record;
        }
        unset($records);
        return $sorted;
    }
}