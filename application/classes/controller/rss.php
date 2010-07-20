<?php

defined('SYSPATH') or die('No direct script access.');

class Controller_Rss extends Controller {

    public function action_index() {
        $config = Kohana::config('default.rss');
        $info = array(
            'title' => 'AutoTvToSab RSS ',// . Request::factory('rss/update')->execute()->response,
            'link' => URL::base(true, true),
            'description' => sprintf('AutoTvToSab shows the %d last aired episodes', $config['numberOfResults']),
        );

        $items = array();
        foreach (ORM::factory('rss')->find_all() as $rss) {
            $item = array();
            $item['title'] = $rss->title;
            $item['link'] = htmlspecialchars($rss->link);
            $item['guid'] = htmlspecialchars($rss->guid);
            $item['description'] = $rss->description;
            $item['pubDate'] = $rss->pubDate;
            $item['category'] = $rss->category;
            $item['enclosure'] = unserialize($rss->enclosure);

            $items[] = $item;
        }

//        var_dump($info);
//        var_dump($items);
//        echo '<pre>';
//        $this->request->response = Rss::create($info, $items)->__toString();

        $this->request->headers['Content-Type'] = 'application/xml; charset=UTF-8';
        $this->request->response = Rss::create($info, $items)->__toString();
    }

    public function action_update() {
        $config = Kohana::config('default');
        $rss = ORM::factory('rss');
        if ($this->request != Request::instance()) {
            $expr = 'DATE_SUB(NOW(),INTERVAL ' . Inflector::singular(ltrim($config->rss['howOld'], '-')) . ')';

            $result = $rss->where(DB::expr($expr), '>=', DB::expr('updated'));

            if ($result->count_all() <= 0) {
                if ($rss->count_all() == $config->rss['numberOfResults']) {
                    $this->request->response = __('Already updated');
                    return;
                }
            }
        }
        set_time_limit(0);

        $rss->truncate();

        $matrix = new NzbMatrix_Rss($config->default['NzbMatrix_api_key']);
        $series = Model_SortFirstAired::getSeries();

//        echo '<pre>';
        
        $i = 0;
        $secToSleep = 10;
        foreach ($series as $ep) {
            if (strtotime($ep->first_aired) < strtotime($config->rss['howOld']) && $ep->season > 0) {
                $search = sprintf('%s S%02dE%02d', $ep->series_name, $ep->season, $ep->episode);

                if (!$rss->alreadySaved($search)) {
                    $result = $matrix->search($search, $ep->matrix_cat);
                    $time = time();
                    
//                    echo("/******** New Search *********/ \n");
//                    var_dump($search);

//                    var_dump($result);
//                    var_dump($ep);
//                    break;

                    if (isset($result[0]['error'])) {

//                        echo("/******** ERROR *********/ \n");
//                        var_dump($result[0]['error']);
//                        echo("/******** END ERROR *********/ \n");
                        
                        if (preg_match('#^(.*)_(?P<num>\d{1,2})$#', $result[0]['error'], $matches)) {
                            $secToSleep = $matches['num'];                            
                        } else {
                            $search = sprintf('%s %02dx%02d', $ep->series_name, $ep->season, $ep->episode);
                        }

                        sleep($secToSleep + 3);
                        $result = $matrix->search($search, $ep->matrix_cat);
                        $time = time();

//                        echo("/******** New Search *********/ \n");
//                        var_dump($search);

                        if (isset($result[0]['error'])) {

//                            echo("/******** ERROR *********/ \n");
//                            var_dump($result[0]['error']);
//                            echo("/******** END ERROR Continue!!!! *********/ \n");
                            
                            continue;
                        }
                    }
                    
                    $this->handleResult($search, $result, $ep, $i);
                    if ($i >= $config->rss['numberOfResults']) {
                        break;
                    }

                    //$seconds = $secToSleep - (time() - $time);
                    $seconds = $secToSleep;
                    sleep($seconds);

                    echo("/******** Search END *********/ \n");
                }
            }
        }

        Cache::instance('default')->delete('series');

        $this->request->response = __('Updated');
    }
    
    protected function handleResult($search, $result, $ep, &$i) {
        foreach ($result as $res) {
            $rss = ORM::factory('rss');

            $parse = new NameParser($res['nzbname']);
            $parsed = $parse->parse();

//            echo("/******** In Result Loop *********/ \n");
//            var_dump($res);
////            var_dump(strtolower(sprintf('%s S%02dE%02d', $parsed['name'], $parsed['season'], $parsed['episode'])) == strtolower($search));
////            var_dump(strtolower(sprintf('%s S%02dE%02d', $parsed['name'], $parsed['season'], $parsed['episode'])));
//            var_dump(sprintf('%02d', $parsed['season']) == sprintf('%02d', $ep->season) &&
//                    sprintf('%02d', $parsed['episode']) == sprintf('%02d', $ep->episode));
//            var_dump(strtolower($parsed['name']) == strtolower($ep->series_name));
//
//            var_dump(strtolower($search));
//            echo("/******** Loop END *********/ \n");

//            if (strtolower(sprintf('%s S%02dE%02d', $parsed['name'], $parsed['season'], $parsed['episode'])) == strtolower($search) &&
            if (sprintf('%02d', $parsed['season']) == sprintf('%02d', $ep->season) &&
                sprintf('%02d', $parsed['episode']) == sprintf('%02d', $ep->episode) &&
                strtolower($parsed['name']) == strtolower($ep->series_name) &&
                $ep->matrix_cat == NzbMatrix::catStr2num($res['category'])) {
                if (!$rss->alreadySaved($search)) {
                    $rss->title = $res['nzbname'];
                    $rss->guid = 'http://nzbmatrix.com/' . $res['link'];
                    $rss->link = 'http://nzbmatrix.com/' . $res['link'];
                    $rss->description = $this->description($res['nzbid'], $res['nzbname'], $res['category'], $res['size'], $res['index_date'], $res['group']);
                    $rss->category = $res['category'];
                    $rss->pubDate = date(DATE_RSS, strtotime($res['usenet_date']));
                    $rss->enclosure = serialize(array(
                                'url' => 'http://nzbmatrix.com/' . $res['link'],
                                'length' => round($res['size']),
                                'type' => 'application/x-nzb')
                            );

                    if ($rss->save()) {
                        $i++;
                    }
                }
            }
        }

        return true;
    }


    /**
     *
     * @param integer $id
     * @param string $name
     * @param string $cat
     * @param string $size
     * @param string $added
     * @param string $group
     * @return string
     * <b>Name:</b> Burn Notice S04E06 HDTV XviD XII<br />
     * <b>Category:</b> TV: Divx/Xvid<br />
     * <b>Size:</b> 395.72 MB<br />
     * <b>Added:</b> 2010-07-16 05:37:24<br />
     * <b>Group:</b> alt.binaries.multimedia <BR />
     * <b>NFO:</b> <a href="http://nzbmatrix.com/viewnfo.php?id=691833">View NFO</a>
     */
    protected function description($id, $name, $cat, $size, $added, $group) {
        $size = Text::bytes($size, 'MB');
        $html = "";
        $html .= "<b>Name:</b> $name <br />";
        $html .= "<b>Category:</b> $cat <br />";
        $html .= "<b>Size:</b> $size <br />";
        $html .= "<b>Added:</b> $added <br />";
        $html .= "<b>Group:</b> $group <br />";
        $html .= "<b>NFO:</b> <a href=\"http://nzbmatrix.com/viewnfo.php?id=$id\">View NFO</a>";
        return $html;
    }

    /*
     *
     public function action_update() {
        $config = Kohana::config('default');

        $matrix = new NzbMatrix_Rss($config->default['NzbMatrix_api_key']);

        $series = Model_SortFirstAired::getSeries();
        $rss = ORM::factory('rss');


        $expr = 'DATE_SUB(NOW(),INTERVAL ' . Inflector::singular(ltrim($config->rss['howOld'], '-')) . ')';

        $result = $rss->where(DB::expr($expr), '>=', DB::expr('updated'));

        if ($result->count_all() <= 0) {
            if ($rss->count_all() == $config->rss['numberOfResults']) {
                $this->request->response = __('Already updated');
                return;
            }
        }

        set_time_limit(0);

        $rss->truncate();
        $i = 0;

        foreach ($series as $ep) {
            if (strtotime($ep->first_aired) < strtotime($config->rss['howOld']) && $ep->season > 0) {
                $search = sprintf('%s S%02dE%02d', $ep->series_name, $ep->season, $ep->episode);

                $rss = ORM::factory('rss');
                if (!$rss->alreadySaved($search)) {
                    $result = $matrix->search($search);
                    foreach ($result->item as $res) {
                        $parse = new NameParser((string) $res->title);
                        $parsed = $parse->parse();
                        if (sprintf('%02d', $parsed['season']) == sprintf('%02d', $ep->season) &&
                            sprintf('%02d', $parsed['episode']) == sprintf('%02d', $ep->episode) &&
                            strtolower($parsed['name']) == strtolower($ep->series_name) &&
                            $ep->matrix_cat == $res->categoryid) {

                            if (!$rss->alreadySaved($search)) {
                                $rss->title = (string) $res->title;
                                $rss->guid = (string) $res->guid;
                                $rss->link = (string) $res->link;
                                $rss->description = (string) $res->description;
                                $rss->category = (string) $res->category;
                                $rss->pubDate = date(DATE_RSS, strtotime($ep->first_aired));
                                $rss->enclosure = serialize(array(
                                            'url' => (string) $res->enclosure['url'],
                                            'length' => (string) $res->enclosure['length'],
                                            'type' => (string) $res->enclosure['type']));

                                $rss->save();
                                $i++;

                                sleep(2);
                            }
                        }
                    }
                    if ($i >= $config->rss['numberOfResults']) {

                        break;
                    }
                }
            }
        }

        Cache::instance('default')->delete('series');

        $this->request->response = __('Updated');
    }
     */

}
?>
