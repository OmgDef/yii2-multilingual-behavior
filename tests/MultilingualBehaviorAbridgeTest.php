<?php
namespace tests;

use tests\models\PostRequired;
use Yii;
use yii\base\InvalidConfigException;
use tests\models\PostAbridge as Post;
use omgdef\multilingual\MultilingualBehavior;

class MultilingualBehaviorAbridgeTest extends DatabaseTestCase
{
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
            $this->assertEquals($model->title, $model->title_ru);
            $this->assertEquals($model->body, $model->body_ru);
            $this->assertNotNull($model->title_en);
            $this->assertNotNull($model->body_en);
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

        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testCreatePostSetTranslations()
    {
        $post = new Post();
        $data = [
            'title' => 'New post title',
            'body' => 'New post body',
            'title_en' => 'New post title en',
            'body_en' => 'New post body en',
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
        $this->assertEquals(require(__DIR__ . '/data/test-localized-en.php'), [
            'id' => $post->id,
            'title' => $post->title,
            'body' => $post->body,
        ]);

        $post = Post::find()->localized('ru')->where(['id' => 2])->one();
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
        $expectedDataSet = $this->createFlatXMLDataSet(__DIR__ . '/data/test-delete-post.xml');
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testLocalizedAndMultilingual()
    {
        $post = Post::find()->localized()->multilingual()->limit(1)->one();
        $this->assertTrue($post->isRelationPopulated('translations'));
        $this->assertFalse($post->isRelationPopulated('translation'));
    }

    public function testRequired()
    {
        $post = new PostRequired([
            'title' => 'rus',
            'body' => 'rus',
        ]);

        $post->validate();
        $this->assertArrayNotHasKey('title_ru', $post->errors);
        $this->assertArrayNotHasKey('body_ru', $post->errors);
        $this->assertArrayHasKey('title_en', $post->errors);
        $this->assertArrayHasKey('body_en', $post->errors);
    }
}
