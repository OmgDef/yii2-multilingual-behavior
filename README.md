Yii2 multilingual behavior
==========================
Yii2 port of the [yii-multilingual-behavior](https://github.com/belerophon/yii-multilingual-behavior).

[![Latest Stable Version](https://poser.pugx.org/omgdef/yii2-multilingual-behavior/v/stable.svg)](https://packagist.org/packages/omgdef/yii2-multilingual-behavior) [![Total Downloads](https://poser.pugx.org/omgdef/yii2-multilingual-behavior/downloads.svg)](https://packagist.org/packages/omgdef/yii2-multilingual-behavior) [![Latest Unstable Version](https://poser.pugx.org/omgdef/yii2-multilingual-behavior/v/unstable.svg)](https://packagist.org/packages/omgdef/yii2-multilingual-behavior) [![License](https://poser.pugx.org/omgdef/yii2-multilingual-behavior/license.svg)](https://packagist.org/packages/omgdef/yii2-multilingual-behavior)

This behavior allows you to create multilingual models and almost use them as normal models. Translations are stored in a separate table in the database (ex: PostLang or ProductLang) for each model, so you can add or remove a language easily, without modifying your database.

Examples
--------

Example #1: current language translations are inserted to the model as normal attributes by default.

```php
//Assuming current language is english

$model = Post::findOne(1);
echo $model->title; //echo "English title"

//Now let's imagine current language is french 
$model = Post::findOne(1);
echo $model->title; //echo "Titre en Français"

$model = Post::find()->localized('en')->one();
echo $model->title; //echo "English title"

//Current language is still french here
```

Example #2: if you use `multilang()` in a `find()` query, every model translation is loaded as virtual attributes (title_en, title_fr, title_de, ...).

```php
$model = Post::find()->multilang()->one();
echo $model->title_en; //echo "English title"
echo $model->title_fr; //echo "Titre en Français"
```

Installation
------------

Preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist omgdef/yii2-multilingual-behavior "*"
```

or add

```
"omgdef/yii2-multilingual-behavior": "*"
```

to the require section of your `composer.json` file.


Usage
-----

Here an example of base 'post' table :

```sql
CREATE TABLE IF NOT EXISTS `post` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `title` varchar(255) NOT NULL,
    `content` TEXT NOT NULL,
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL,
    `enabled` tinyint(1) NOT NULL DEFAULT '1',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

And its associated translation table (configured as default), assuming translated fields are 'title' and 'content':

```sql
CREATE TABLE IF NOT EXISTS `postLang` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `post_id` int(11) NOT NULL,
    `language` varchar(6) NOT NULL,
    `title` varchar(255) NOT NULL,
    `content` TEXT NOT NULL,
    PRIMARY KEY (`id`),
    KEY `post_id` (`post_id`),
    KEY `language` (`language`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

ALTER TABLE `postLang`
ADD CONSTRAINT `postlang_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `post` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
```

Attaching this behavior to the model (Post in the example). Commented fields have default values.

```php
public function behaviors()
{
    return [
        'ml' => [
            'class' => MultilingualBehavior::className(),
            'languages' => [
                'ru' => 'Russian',
                'en-US' => 'English',
            ],
            //'languageField' => 'language',
            //'localizedPrefix' => '',
            //'forceOverwrite' => false',
            //'dynamicLangClass' => true',
            //'langClassName' => PostLang::className(), // or namespace/for/a/class/PostLang
            'defaultLanguage' => 'ru',
            'langForeignKey' => 'post_id',
            'tableName' => "{{%postLang}}",
            'attributes' => [
                'title', 'content',
            ]
        ],
    ];
}
```

Behavior attributes:
* languageField The name of the language field of the translation table. Default is 'language'.
* localizedPrefix The prefix of the localized attributes in the lang table. Is used to avoid collisions in queries. The columns in the translation table corresponding to the localized attributes have to be name like this: '[prefix]_[name of the attribute]' and the id column (primary key) like this : '[prefix]_id'
* forceOverwrite Whether to force overwrite of the default language value with translated value even if it is empty.
* dynamicLangClass Whether to dynamically create translation model class. If true, the translation model class will be generated on runtime with the use of the eval() function so no additionnal php file is needed.
* langClassName The name of translation model class. (required if dynamicLangClass === false)
* languages Available languages. It can be a simple array: array('fr', 'en') or an associative array: array('fr' => 'Français', 'en' => 'English') (required)
* defaultLanguage The default language. (required)
* langForeignKey The name of the foreign key field of the translation table related to base model table. (required)
* tableName The name of the translation table (required)
* attributes Multilingual attributes (required)

Then you have to overwrite the `find()` method in your model

```php
    public static function find()
    {
        $q = new MultilingualQuery(get_called_class());
        return $q;
    }
```

Add this function to the model class to retrieve translated models by default:
```php
    public static function find()
    {
        $q = new MultilingualQuery(get_called_class());
        $q->localized();
        return $q;
    }
```

As this behavior has ```MultilingualTrait```, you can use it in your query classes

```php
namespace app\models;

use yii\db\ActiveQuery;

class MultilingualQuery extends ActiveQuery
{
    use MultilingualTrait;
}
```

Form example:
```php
//title will be saved to model table and as translation for default language
$form->field($model, 'title')->textInput(['maxlength' => 255]);
$form->field($model, 'title_en')->textInput(['maxlength' => 255]);
```
