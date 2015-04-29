<?php
namespace tests;

use tests\models\PostRequired;
use Yii;
use tests\models\Post;
use omgdef\multilingual\MultilingualBehavior;

class MultilingualBehaviorTest extends DatabaseTestCase
{
    public function testFindPosts()
    {
        $data = [];
        $models = Post::find()->multilingual()->all();
        foreach ($models as $model) {
            $this->assertEquals($model->title, $model->title_ru);
            $this->assertEquals($model->body, $model->body_ru);
            $this->assertNotNull($model->title_en_us);
            $this->assertNotNull($model->body_en_us);
            $data[] = $model->toArray([], ['translations']);
        }

        $this->assertEquals(require(__DIR__ . '/data/test-find-posts-na.php'), $data);
    }

    public function testCreatePost()
    {
        $post = new Post([
            'title' => 'New post title',
            'body' => 'New post body',
        ]);

        $this->assertTrue($post->save());

        $dataSet = $this->getConnection()->createDataSet(['post', 'postLang']);
        $expectedDataSet = $this->createFlatXMLDataSet(__DIR__ . '/data/test-create-post-na.xml');

        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testCreatePostSetTranslations()
    {
        $post = new Post();
        $data = [
            'title' => 'New post title',
            'body' => 'New post body',
            'title_en_us' => 'New post title en',
            'body_en_us' => 'New post body en',
            'title_ru' => 'New post title ru', //this value should be overwritten by default language value
            'body_ru' => 'New post body ru',
        ];
        $formName = $post->formName();
        if (!empty($formName)) {
            $data = [$formName => $data];
        }
        $post->load($data);

        $this->assertTrue($post->save());
        $dataSet = $this->getConnection()->createDataSet(['post', 'postLang']);
        $expectedDataSet = $this->createFlatXMLDataSet(__DIR__ . '/data/test-create-post-set-translations-na.xml');
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testUpdateNotPopulatedPost()
    {
        $post = Post::findOne(2);
        $post->setAttributes([
            'title' => 'Updated post title 2',
            'body' => 'Updated post body 2',
            'title_en_us' => 'Updated post title 2 en',
            'body_en_us' => 'Updated post title 2 en',
        ]);

        $this->assertTrue($post->save());
        $dataSet = $this->getConnection()->createDataSet(['post', 'postLang']);
        $expectedDataSet = $this->createFlatXMLDataSet(__DIR__ . '/data/test-update-not-populated-post-na.xml');
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testUpdatePopulatedPost()
    {
        $post = Post::find()->multilingual()->where(['id' => 2])->one();
        $post->setAttributes([
            'title' => 'Updated post title 2',
            'body' => 'Updated post body 2',
            'title_en_us' => 'Updated post title 2 en',
            'body_en_us' => 'Updated post body 2 en',
        ]);

        $this->assertTrue($post->save());
        $dataSet = $this->getConnection()->createDataSet(['post', 'postLang']);
        $expectedDataSet = $this->createFlatXMLDataSet(__DIR__ . '/data/test-update-populated-post-na.xml');
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testLocalized()
    {
        $post = Post::find()->localized(null, false)->where(['id' => 2])->one();
        $this->assertEquals(require(__DIR__ . '/data/test-localized-en.php'), [
            'id' => $post->id,
            'title' => $post->title,
            'body' => $post->body,
        ]);

        $post = Post::find()->localized('ru', false)->where(['id' => 2])->one();
        $this->assertEquals(require(__DIR__ . '/data/test-localized-ru.php'), [
            'id' => $post->id,
            'title' => $post->title,
            'body' => $post->body,
        ]);
    }

    public function testDeletePost()
    {
        $post = Post::findOne(2);
        $this->assertEquals(1, $post->delete());
        $dataSet = $this->getConnection()->createDataSet(['post', 'postLang']);
        $expectedDataSet = $this->createFlatXMLDataSet(__DIR__ . '/data/test-delete-post-na.xml');
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    /**
     * @inheritdoc
     */
    public function getDataSet()
    {
        return $this->createFlatXMLDataSet(__DIR__ . '/data/test-na.xml');
    }
}
