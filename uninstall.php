<?php

//チェック
if(!defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN')) exit();

//データベースの削除
delete_option('bsc_key');
delete_option('bsc_name_key');
delete_option('bsc_log');
delete_option('bsc_log_number');