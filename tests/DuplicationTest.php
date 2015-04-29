<?php
namespace tests;

use Yii;
use tests\models\PostAbridge as Post;
use omgdef\multilingual\MultilingualBehavior;

class DuplicationTest extends DatabaseTestCase
{
    protected static $migrationFileName = 'sqlite_with_dublication.sql';

    public function testFindAndSave()
    {
        $post = Post::findOne(4);
        $this->assertNotNull($post->title);

        $post = Post::findOne(1);
        $this->assertNotNull($post->title);

        $post = Post::find()->multilingual()->where(['id' => 1])->one();
        $this->assertNotNull($post->title);

        $testString = 'TestString';
        $post->title = $testString;
        $this->assertTrue($post->save());

        $post = Post::find()->localized('ru')->where(['id' => $post->id])->one();
        $this->assertEquals($testString, $post->title);

        $dataSet = $this->getConnection()->createDataSet(['post', 'postLang']);
        $expectedDataSet = $this->createFlatXMLDataSet(__DIR__ . '/data/test-update-populated-post-dublication.xml');
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testCreate()
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
        $expectedDataSet = $this->createFlatXMLDataSet(__DIR__ . '/data/test-create-post-set-translations-dublication.xml');
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);

        $post = new Post([
            'title' => 'New post title',
            'body' => 'New post body',
        ]);

        $this->assertTrue($post->save());
        $post = Post::findOne($post->id);
        $this->assertNotNull($post->title);
    }

    /**
     * @inheritdoc
     */
    public function getDataSet()
    {
        return $this->createFlatXMLDataSet(__DIR__ . '/data/test-dublication.xml');
    }
}
