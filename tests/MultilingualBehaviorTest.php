<?php
namespace tests;

use tests\models\PostForce;
use Yii;
use PHPUnit_Extensions_Database_DataSet_ReplacementDataSet;
use yii\db\Connection;
use yii\base\InvalidConfigException;
use tests\models\Post;
use omgdef\multilingual\MultilingualBehavior;

class MultilingualBehaviorTest extends DatabaseTestCase
{
    CONST NULL_KEY = '##NULL##';

    public function testConfiguration()
    {
        $post = new Post();
        $post->detachBehavior('ml');

        try {
            $post->attachBehavior('ml', [
                'class' => MultilingualBehavior::className(),
                'languages' => [],
            ]);
            $this->fail("Expected exception not thrown");
        } catch (InvalidConfigException $e) {
            $this->assertEquals(101, $e->getCode());
        }

        try {
            $post->detachBehavior('ml');
            $post->attachBehavior('ml', [
                'class' => MultilingualBehavior::className(),
                'languages' => 'Some value',
            ]);
            $this->fail("Expected exception not thrown");
        } catch (InvalidConfigException $e) {
            $this->assertEquals(101, $e->getCode());
        }

        try {
            $post->detachBehavior('ml');
            $post->attachBehavior('ml', [
                'class' => MultilingualBehavior::className(),
                'languages' => [
                    'ru' => 'Russian',
                    'en-US' => 'English',
                ],
                'langForeignKey' => 'post_id',
                'tableName' => "{{%postLang}}",
            ]);
            $this->fail("Expected exception not thrown");
        } catch (InvalidConfigException $e) {
            $this->assertEquals(103, $e->getCode());
        }

        try {
            $post->detachBehavior('ml');
            $post->attachBehavior('ml', [
                'class' => MultilingualBehavior::className(),
                'languages' => [
                    'ru' => 'Russian',
                    'en-US' => 'English',
                ],
                'attributes' => [
                    'title', 'body',
                ]
            ]);
            $this->fail("Expected exception not thrown");
        } catch (InvalidConfigException $e) {
            $this->assertEquals(105, $e->getCode());
        }

        $post->detachBehavior('ml');
        $post->attachBehavior('ml', [
            'class' => MultilingualBehavior::className(),
            'languages' => [
                'ru' => 'Russian',
                'en-US' => 'English',
            ],
            'langForeignKey' => 'post_id',
            'attributes' => [
                'title', 'body',
            ]
        ]);

        $this->assertNotNull($post->defaultLanguage);
    }

    public function testFindPosts()
    {
        $data = [];
        $models = Post::find()->multilingual()->all();
        foreach ($models as $model) {
            $data[] = $model->toArray([], ['translations']);
        }

        $this->assertEquals(require(__DIR__ . '/data/test-find-posts.php'), $data);
    }

    public function testCreatePost()
    {
        $post = new Post([
            'title' => 'New post title',
            'body' => 'New post body',
        ]);
        $this->assertTrue($post->save());
        $dataSet = $this->getConnection()->createDataSet(['post', 'postLang']);
        $expectedDataSet = $this->createFlatXMLDataSet(__DIR__ . '/data/test-create-post.xml');
        $rds = new PHPUnit_Extensions_Database_DataSet_ReplacementDataSet($expectedDataSet);
        $rds->addFullReplacement(self::NULL_KEY, null);
        $this->assertDataSetsEqual($rds, $dataSet);
    }

    public function testCreatePostSetTranslations()
    {
        $post = new Post([
            'title' => 'New post title',
            'body' => 'New post body',
            'title_en' => 'New post title en',
            'body_en' => 'New post body en',
            'title_ru' => 'New post title ru', //this value should be overwritten by default language value
            'body_ru' => 'New post body ru',
        ]);
        $this->assertTrue($post->save());
        $dataSet = $this->getConnection()->createDataSet(['post', 'postLang']);
        $expectedDataSet = $this->createFlatXMLDataSet(__DIR__ . '/data/test-create-post-set-translations.xml');
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testUpdateNotPopulatedPost()
    {
        $post = Post::findOne(2);
        $post->setAttributes([
            'title' => 'Updated post title 2',
            'body' => 'Updated post body 2',
            'title_en' => 'Updated post title 2 en',
            'body_en' => 'Updated post title 2 en',
        ]);

        $this->assertTrue($post->save());
        $dataSet = $this->getConnection()->createDataSet(['post', 'postLang']);
        $expectedDataSet = $this->createFlatXMLDataSet(__DIR__ . '/data/test-update-not-populated-post.xml');
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testUpdatePopulatedPost()
    {
        $post = Post::find()->multilingual()->where(['id' => 2])->one();
        $post->setAttributes([
            'title' => 'Updated post title 2',
            'body' => 'Updated post body 2',
            'title_en' => 'Updated post title 2 en',
            'body_en' => 'Updated post body 2 en',
        ]);

        $this->assertTrue($post->save());
        $dataSet = $this->getConnection()->createDataSet(['post', 'postLang']);
        $expectedDataSet = $this->createFlatXMLDataSet(__DIR__ . '/data/test-update-populated-post.xml');
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testLocalized()
    {
        $post = Post::find()->localized()->where(['id' => 2])->one();
        $this->assertEquals(require(__DIR__ . '/data/test-localized-en.php'), $post->getAttributes());

        $post = Post::find()->localized('ru')->where(['id' => 2])->one();
        $this->assertEquals(require(__DIR__ . '/data/test-localized-ru.php'), $post->getAttributes());
    }

    public function testForceOverwriteSave()
    {
        $post = PostForce::find()->multilingual()->where(['id' => 3])->one();
        $post->setAttributes([
            'title' => 'Updated post title 2',
            'body' => 'Updated post body 2',
            'title_en' => 'Updated post title 2 en',
            'body_en' => '',
        ]);
        $this->assertFalse($post->save());
        $this->assertArrayHasKey('body_en', $post->getErrors());

        $post->body_en = 'Some text';
        $this->assertTrue($post->save());
    }

    public function testDeletePost()
    {
        $post = Post::findOne(2);
        $this->assertEquals(1, $post->delete());
        $dataSet = $this->getConnection()->createDataSet(['post', 'postLang']);
        $expectedDataSet = $this->createFlatXMLDataSet(__DIR__ . '/data/test-delete-post.xml');
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
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