<?php

namespace tests\models;

use \omgdef\multilingual\MultilingualBehavior;
use \omgdef\multilingual\MultilingualQuery;

class PostRequired extends PostAbridge
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['ml']['requireTranslations'] = true;
        return $behaviors;
    }
}