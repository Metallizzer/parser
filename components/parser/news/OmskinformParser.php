<?php
/**
 *
 * @author MediaSfera <info@media-sfera.com>
 * @author FingliGroup <info@fingli.ru>
 * @author Vitaliy Moskalyuk <flanker@bk.ru>
 *
 * @note Данный код предоставлен в рамках оказания услуг, для выполнения поставленных задач по сбору и обработке данных. Переработка, адаптация и модификация ПО без разрешения правообладателя является нарушением исключительных прав.
 *
 */

namespace app\components\parser\news;


use app\components\mediasfera\MediasferaNewsParser;
use app\components\mediasfera\NewsPostWrapper;
use app\components\parser\NewsPostItem;
use app\components\parser\ParserInterface;
use Symfony\Component\DomCrawler\Crawler;

class OmskinformParser extends MediasferaNewsParser implements ParserInterface
{
    public const USER_ID = 2;
    public const FEED_ID = 2;

    public const NEWS_LIMIT = 100;

    public const SITE_URL = 'https://www.omskinform.ru/';
    public const NEWSLIST_URL = 'https://www.omskinform.ru/rss/news.rss';

    //    public const TIMEZONE = '+0000';
    public const DATEFORMAT = 'D, d M Y H:i:s O';

    public const NEWSLIST_POST = '//rss/channel/item';
    public const NEWSLIST_TITLE = '//title';
    public const NEWSLIST_LINK = '//link';
    public const NEWSLIST_DATE = '//pubDate';
    public const NEWSLIST_DESC = '//description';
    public const NEWSLIST_IMG = '//enclosure';

    public const ARTICLE_HEADER = '.n_news .n_cap_lnk_one h1';
//    public const ARTICLE_IMAGE =  'article .article__thumbnail img';
    public const ARTICLE_TEXT =   'article div[itemtype="http://schema.org/NewsArticle"] .n_text_lnk';

    public const ARTICLE_BREAKPOINTS = [];

    protected static NewsPostWrapper $post;

    public static function run(): array
    {
        $posts = [];

        $listContent = self::getPage(self::NEWSLIST_URL);

        $listCrawler = new Crawler($listContent);

        $listCrawler->filterXPath(self::NEWSLIST_POST)->slice(0, self::NEWS_LIMIT)->each(function (Crawler $node) use (&$posts) {

            self::$post = new NewsPostWrapper();

            self::$post->title = self::getNodeData('text', $node, self::NEWSLIST_TITLE);
            self::$post->original = self::getNodeData('text', $node, self::NEWSLIST_LINK);
            self::$post->createDate = self::getNodeDate('text', $node, self::NEWSLIST_DATE);
            self::$post->description = self::getNodeData('text', $node, self::NEWSLIST_DESC);
            self::$post->image = self::getNodeData('url', $node, self::NEWSLIST_IMG);

            $articleContent = self::getPage(self::$post->original);

            if (!empty($articleContent)) {

                $articleCrawler = new Crawler($articleContent);

                self::$post->itemHeader = [self::getNodeData('text', $articleCrawler, self::ARTICLE_HEADER), 1];

                $contentNode = $articleCrawler->filter(self::ARTICLE_TEXT);

                /**
                 * агрегация новости переадресация на другой источник
                 * @see https://www.omskinform.ru/news/147053
                 */
                if(!$contentNode->count()) {
                    return;
                }

                $contentCrawler = new Crawler('<body><div>' . $contentNode->html() . '</div></body>');
                self::parse($contentCrawler->filter('body > div'));
            }

            $posts[] = self::$post->getNewsPost();
        });

        return $posts;
    }
}
