<?php

namespace app\components\parser\news;

use app\components\helper\metallizzer\Parser;
use app\components\parser\NewsPost;
use app\components\parser\NewsPostItem;
use app\components\parser\ParserInterface;
use DateTime;
use DateTimeZone;
use Exception;
use Symfony\Component\DomCrawler\Crawler;

class InzaraiskRuParser implements ParserInterface
{
    use \app\components\helper\metallizzer\Cacheable;

    const USER_ID  = 2;
    const FEED_ID  = 2;
    const SITE_URL = 'http://inzaraisk.ru/';

    protected static $posts = [];

    public static function run(): array
    {
        $html = self::request(self::SITE_URL.'novosti');

        if (!$html) {
            throw new Exception('Не удалось загрузить сайт.');
        }

        $crawler = new Crawler($html, self::SITE_URL.'novosti');

        $crawler->filter('.news-itm__title a')->each(function ($node) {
            self::loadPost($node->link()->getUri());
        });

        return static::$posts;
    }

    protected static function loadPost($url)
    {
        if (!$html = self::request($url)) {
            throw new Exception("Не удалось загрузить страницу '{$url}'.");
        }

        $crawler = new Crawler($html, $url);

        $date  = self::parseDate($crawler->filter('.b-page__single-date')->first()->text());
        $image = $crawler->filter('.b-page__image img')->first();

        $tz = new DateTimeZone('Europe/Moscow');
        $dt = new DateTime($date, $tz);

        $post = new NewsPost(
            self::class,
            html_entity_decode($crawler->filter('title')->first()->text()),
            html_entity_decode($crawler->filter('meta[name="description"]')->first()->attr('content')),
            $dt->setTimezone(new DateTimeZone('UTC'))->format('c'),
            $url,
            $image->count() ? $image->image()->getUri() : null,
        );

        $items = (new Parser())->parseMany($crawler->filterXpath('//div[@class="b-page__content"]/node()'));

        foreach ($items as $item) {
            if (!$post->image && $item['type'] === NewsPostItem::TYPE_IMAGE) {
                $post->image = $item['image'];

                continue;
            }

            $post->addItem(new NewsPostItem(...array_values($item)));
        }

        self::$posts[] = $post;
    }

    protected static function parseDate($string)
    {
        $re = '/^(?<day>\d+) (?<month>[^ ]+) (?<year>\d{4}) г\., (?<hours>\d{2}):(?<minutes>\d{2})$/';

        if (!preg_match($re, trim($string), $m)) {
            throw new Exception('Не удалось разобрать дату');
        }

        $months = ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];

        foreach ($months as $key => $name) {
            if (strpos($m['month'], $name) === 0) {
                $month = sprintf('%02d', $key + 1);

                break;
            }
        }

        return sprintf('%d-%d-%d %s:%s:00',
            $m['year'], $month, $m['day'], $m['hours'], $m['minutes']
        );
    }
}