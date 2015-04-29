<?php

namespace tests\models;

use \omgdef\multilingual\MultilingualBehavior;
use \omgdef\multilingual\MultilingualQuery;

class Post extends PostAbridge
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['ml']['abridge'] = false;
        return $behaviors;
    }
}