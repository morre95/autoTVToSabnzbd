<?php defined('SYSPATH') or die('No direct script access.');


class Controller_Movie_Add extends Controller_Movie_Page {
    
    protected $_movieToSavePath = '';

    public function before() {
        parent::before();

        $this->_movieToSavePath = APPPATH . 'cache/' . get_class($this) . '.cache';
    }


    public function action_index() {
        $this->template->scripts['jquery autocomplete js'] = 'js/jquery.autocomplete.pack.js';
        $this->template->styles['css/jquery.autocomplete.css'] = 'screen';

        $view = View::factory('movie/add/add');
        $this->template->title = __('Add movie');
        $view->set('title', __('Add movie'))
                ->set('matrixCat', Session::instance()->get('matrixCat', 'movies-all'));

        $this->template->content = $view;
    }

    public function action_doAdd() {
        if (empty($_GET['name'])) {
            MsgFlash::set('No name to search for');
            $this->request->redirect('movie/add/index');
        }

        $movie = ORM::factory('movie');
        if ($movie->isAdded($_GET['name'])){
            MsgFlash::set($_GET['name'] . ' ' . __('alredy exists'));
            $this->request->redirect('movie/add/index');
        }

        Session::instance()->set('matrixCat', $_GET['cat']);

        $this->auto_render = false;

        $tmdb = new TmdbApi(Kohana::config('movie.tmdb'));
        $results = $tmdb->search($_GET['name']);

        if ($results[0] != 'Nothing found.') {
            foreach ($results as $result) {
                if (strtolower($result->name) == strtolower($_GET['name']) || strtolower($result->original_name) == strtolower($_GET['name']) || strtolower($result->alternative_name) == strtolower($_GET['name'])) {
                    $isAdded = $movie->isIdAdded($result->id);
                    if ($isAdded) {
                        MsgFlash::set($isAdded . ' ' . __('alredy exists'));
                        $this->request->redirect('movie/add/index');
                    }
                    $result->matrix_cat = $_GET['cat'];
                    file_put_contents($this->_movieToSavePath, serialize($result));

                    Helper::backgroundExec(URL::site('movie/add/saveMovieBack', true));
                    MsgFlash::set('Saving... ' . $_GET['name']);
                    $this->request->redirect('movie/add/index');
                    break;
                }
            }
        }

        MsgFlash::set(__('Nothing Found'));
        $this->request->redirect('movie/add/index');
    }

    public function action_saveMovieBack() {
        ignore_user_abort(true);
        set_time_limit(0);
        
        $this->auto_render = false;
        $filename = $this->_movieToSavePath;
        if (is_readable($filename)) {
            $result = unserialize(file_get_contents($filename));
            unlink($filename);
        }

        try {
            $movie = ORM::factory('movie');
            $movie->score = $result->score;
            $movie->popularity = $result->popularity;
            $movie->translated = $result->translated;
            $movie->adult = $result->adult;
            $movie->language = $result->language;
            $movie->original_name = $result->original_name;
            $movie->name = $result->name;
            $movie->alternative_name = $result->alternative_name;
            $movie->movie_type = $result->movie_type;
            $movie->tmdb_id = $result->id;
            $movie->imdb_id = $result->imdb_id;
            $movie->url = $result->url;
            $movie->votes = $result->votes;
            $movie->rating = $result->rating;
            $movie->certification = $result->certification;
            $movie->overview = $result->overview;
            $movie->released = $result->released;
            $movie->posters = serialize($result->posters);
            $movie->backdrops = serialize($result->backdrops);
            $movie->version = $result->version;
            $movie->last_modified_at = $result->last_modified_at;
            $movie->matrix_cat = $result->matrix_cat;

            $movie->save();
        } catch (Database_Exception $e) {
            MsgFlash::set($result->name . ' saving error: ' . $e->getMessage(), true);
        }

        $postersDL = array();
        foreach ($result->posters as $poster) {
            if (preg_match('#^http:\/\/#', $poster->image->url)) {
                $path = "images/movies/" . $result->name . "/" . $poster->image->type . "/" . $poster->image->size;
                if (!is_dir($path)) {
                    mkdir($path, 0777, true);
                }
                chmod($path, 0777);

                $newFilename = $path . '/' . basename($poster->image->url);
                Posters::save($poster->image->url, $newFilename);
                $poster->image->url = $newFilename;
                $postersDL[] = $poster;
            }
        }

        $movie->posters = serialize($postersDL);
        $movie->save();
        
    }

    public function action_edit($id) {
        $movie = ORM::factory('movie');
        $view = View::factory('movie/add/edit');
        $this->template->title = __('Add movie');
        $view->set('title', __('Add movie'))
                ->set('languages', ORM::factory('language')->find_all())
                ->set('movie', $movie->find($id));

        $this->template->content = $view;
    }
    
    public function action_doEdit($id) {
        $this->auto_render = false;
        $movie = ORM::factory('movie', $id);

        $movie->matrix_cat = $_GET['cat'];
        $movie->save();

        MsgFlash::set($movie->name . ' ' . __('is saved'));
        $this->request->redirect('movie/add/edit/' . $movie->id);
    }

    public function action_ajax_setMatrix($id) {
        $this->auto_render = false;
        $movie = ORM::factory('movie', $id);
        $movie->matrix_cat = $_GET['cat'];
        $movie->save();

        $this->request->response = NzbMatrix::cat2string($movie->matrix_cat);
    }

}

?>