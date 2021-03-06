<?php defined('SYSPATH') or die('No direct script access.'); ?>
 <!--header -->
<div id="header">
    <div id="logo"><?php echo HTML::anchor('',HTML::image('images/black/logo.gif', array('alt' => 'logo')))?></div>

    <div id="nav">
        <ul>
            <li id="welcome_li"><?php echo HTML::anchor('', __('Home'), array('id' => 'welcome_link'))?></li>
            <li id="series_li"><?php echo HTML::anchor('series/add', __('New'), array('id' => 'series_link'))?></li>
            <li id="download_li"><?php echo HTML::anchor('download/listAll', __('Downloads'), array('id' => 'download_link'))?></li>
            <li id="search_li"><?php echo HTML::anchor('search/index', __('Search'), array('id' => 'search_link'))?></li>
            <li id="queue_li"><?php echo HTML::anchor('queue/index', __('Queue'), array('id' => 'queue_link'))?></li>
            <li id="update_li"><?php echo HTML::anchor('update/all', __('Update'), array('id' => 'update_link'))?></li>
            <li id="config_li"><?php echo HTML::anchor('config/index', __('Config'), array('id' => 'config_link'))?></li>
            <li id="movie_li"><?php echo HTML::anchor('movie/list/index', __('Movie'), array('id' => 'movie_link'))?></li>
            <li id="last"><?php echo HTML::anchor('rss/index', __('RSS'), array('id' => 'rss_link'))?></li>
        </ul>
    </div>
    <!--header ends-->
</div>

