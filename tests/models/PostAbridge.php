<?php

namespace tests\models;

use \omgdef\multilingual\MultilingualBehavior;
use \omgdef\multilingual\MultilingualQuery;

/**
 * Post
 *
 * @property integer $id
 * @property string $title
 * @property string $body
 *
 */
class PostAbridge extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'post';
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'ml' => [
                'class' => MultilingualBehavior::className(),
                'languages' => [
                    'ru' => 'Russian',
                    'en-US' => 'English',
                ],
                'defaultLanguage' => 'ru',
                'langForeignKey' => 'post_id',
                'tableName' => "{{%postLang}}",
                'attributes' => [
                    'title', 'body',
                ]
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['title', 'body'], 'required'],
            ['title', 'string'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function transactions()
    {
        return [
            self::SCENARIO_DEFAULT => self::OP_ALL,
        ];
    }

    /**
     * @inheritdoc
     */
    public static function find()
    {
        return new MultilingualQuery(get_called_class());
    }
}