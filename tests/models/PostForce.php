<?php

namespace tests\models;

use \omgdef\multilingual\MultilingualBehavior;

/**
 * Post
 *
 * @property integer $id
 * @property string $title
 * @property string $body
 *
 */
class PostForce extends Post
{
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
                'forceOverwrite' => true,
                'attributes' => [
                    'title', 'body',
                ]
            ],
        ];
    }
}