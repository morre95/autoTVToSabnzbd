<?php

defined('SYSPATH') or die('No direct script access.');

class Controller_Page extends Controller_Template {

    public $template = 'autoTvToSab/template';

    protected $_auto_update = true;
    protected $_no_db = true;

    /**
     * The before() method is called before your controller action.
     * In our template controller we override this method so that we can
     * set up default values. These variables are then available to our
     * controllers if they need to be modified.
     */
    public function before() {
        if (Request::$is_ajax) {
            $this->request->action = 'ajax_'.$this->request->action;
        }
        parent::before();

        if ($this->auto_render) {
            // Initialize empty values
            $this->template->title = '';
            $this->template->content = '';

            $this->template->styles = array();
            $this->template->scripts = array();
            $this->template->codes = array();
        }

        $this->template->bodyPage = get_class($this);
//        var_dump(get_class($this));
    }

    /**
     * The after() method is called after your controller action.
     * In our template controller we override this method so that we can
     * make any last minute modifications to the template before anything
     * is rendered.
     */
    public function after() {
        if ($this->auto_render) {
            $styles = array(
//                'screen' => 'css/style.css',
                'css/black.css' => 'screen',
            );

            $scripts = array(
                'jQuery' => 'http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js',
                'functions' => 'js/functions.js',
                'tooltip' => 'js/tooltip.js',
            );

            $codes = array(
                'home path' => 'var baseUrl = "' . URL::base() . '"',
                'ajax path' => 'var ajaxUrl = "' . URL::site() . '"',
            );

            $this->template->styles = array_merge($styles, $this->template->styles);
            $this->template->scripts = array_merge($scripts, $this->template->scripts);
            $this->template->codes = array_merge($codes, $this->template->codes);


            $this->template->menu = View::factory('menu')->__toString();
            $footer = View::factory('footer');

            $denySidbar = array(
                'Controller_Queue',
                'Controller_Download',
                'Controller_Config',
                );



            $showSidebar = !in_array(get_class($this), $denySidbar);
            $footer->set('showSidebar', $showSidebar);

            if ($showSidebar) {
                $footer->set('endedSeries', ORM::factory('series')->findEnded(15));
                $ep = ORM::factory('episode');

                $footer->set('episodes', $ep->where('season', '>', '0')->order_by('first_aired', 'desc')->limit(10)->find_all());
            }

            $this->template->footer = $footer->__toString();
        }

        parent::after();


        if ($this->_auto_update) {
            if ($this->auto_render) {
                header('Refresh: 300; url=' . URL::site(Request::instance()->uri()));
            }

            $config = Kohana::config('default');
            $session = Session::instance();

//            $session->delete('rss_update');

            $rssUpdate = $session->get('rss_update', null);

            if (isset($config->rss)) {
                if (time() >= strtotime($config->rss['howOld'], $rssUpdate)) {
                    $session->set('rss_update', time());
                    Helper::backgroundExec(URL::site('rss/update', true));
                }

                $lastUpdate = Cookie::get('seriesUpdateEvery', null);
                if (is_null($lastUpdate)) {
                    $lastUpdate = time();
                    Cookie::set('seriesUpdateEvery', $lastUpdate);
                }

                if (time() > strtotime($config->update['seriesUpdateEvery'], $lastUpdate)) {
                    Helper::backgroundExec(URL::site('update/doAll', true));
                    Cookie::set('seriesUpdateEvery', time());
                }
            } 
        }

    }

}