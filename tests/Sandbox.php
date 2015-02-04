<?php
/**
 * Created by PhpStorm.
 * User: def
 * Date: 26.01.15
 * Time: 21:32
 */

namespace tests;

use Yii;
use yii\db\Connection;
use tests\models\Post;

class Sandbox extends DatabaseTestCase
{

    public function testIndex()
    {
        $data = [];
        $models = Post::find()->multilingual()->all();
        foreach ($models as $model) {
            $data[] = $model->toArray([], ['translations']);
        }
        print_r($data);
    }

    /**
     * @inheritdoc
     */
    public static function setUpBeforeClass()
    {
        try {
            Yii::$app->set('db', [
                'class' => Connection::className(),
                'dsn' => 'sqlite::memory:',
            ]);
            Yii::$app->getDb()->open();
            $lines = explode(';', file_get_contents(__DIR__ . '/migrations/sqlite.sql'));
            foreach ($lines as $line) {
                if (trim($line) !== '') {
                    Yii::$app->getDb()->pdo->exec($line);
                }
            }
        } catch (\Exception $e) {
            Yii::$app->clear('db');
        }
    }
}