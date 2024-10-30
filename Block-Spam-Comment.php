<?php
/*
Plugin Name: Block Spam Comment
Plugin URI: https://wordpress.org/plugins/block-spam-comment
Description: Block spam comments definitely without settings or Captcha.
Version: 1.1.0
Author: poku
Author URI: https://xn--pckxe.jp/poku
Text Domain: Block-Spam-Comment
License: GPL2
*/

//直接アクセス防止
if (!defined('ABSPATH')) exit;

//コメント入力時の動作
add_action('pre_comment_on_post',function($post_id) {

    //非ログインユーザーのみ
    if (!is_user_logged_in()) {
        //キーを取得
        $bsc_key = get_option('bsc_key', null);
        $name_key = get_option('bsc_name_key', null);

        //post されてない場合
        if (empty($_POST['bsc_spam_check_'.$name_key.'_1']) /*|| empty($_POST['bsc_spam_check_'.$name_key.'_2'])*/) {
            $error_message = bsc_update_log(1, $post_id);
            wp_die($error_message);
        }
        //ページ読み込み後10秒未満に投稿された場合
        if ($_POST['bsc_spam_check_'.$name_key.'_1'] !== $bsc_key) {
            $error_message = bsc_update_log(2, $post_id);
            wp_die($error_message);
        }
        //アクションがない場合(停止中)
        /*if (!wp_verify_nonce( $_POST['bsc_spam_check_'.$name_key.'_2'], 'bsc_check' ) ) {
            $error_message = bsc_update_log(3, $post_id);
            wp_die($error_message);
        }*/
        //文字が入力されていた場合
        if (!empty($_POST['bsc_spam_check_'.$name_key.'_3'])) {
            $error_message = bsc_update_log(4, $post_id);
            wp_die($error_message);
        }

    }

}, 0 );


//ログの取得と登録
function bsc_update_log($cause = 0, $post_id = null) {
    //ログ取得
    $bsc_log = get_option('bsc_log', array());
    $bsc_log_number = get_option('bsc_log_number', 0);
    //正しいipを取得
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipArray = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = $ipArray[0];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    //値を代入
    $bsc_log[] = array(
        'time' => $_SERVER['REQUEST_TIME'],
        'name' => mb_strimwidth(htmlentities($_POST['author']), 0, 50, '…', 'UTF-8'),
        'ip' => htmlentities($ip),
        'agent' => mb_strimwidth(htmlentities($_SERVER['HTTP_USER_AGENT']), 0, 200, '…', 'UTF-8'),
        'cause' => $cause,
        'post' => $post_id,
        'comment' => mb_strimwidth(htmlentities($_POST['comment']), 0, 200, '…', 'UTF-8')
    );
    //多い場合は削除
    if(count($bsc_log) > 10000) {
        $bsc_log_deleted = array_shift($bsc_log);
    }
    //カウント足す
    ++$bsc_log_number;
    //保存
    update_option('bsc_log', $bsc_log, false);
    update_option('bsc_log_number', $bsc_log_number, false);
    load_plugin_textdomain('Block-Spam-Comment', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    return __("<h3>ERROR</h3><p>Our systems have detected unusual traffic from your computer network. Please enable JavaScript and try to post your comment again.</p>",'Block-Spam-Comment');
}

//コメント欄下
add_action('comment_form_after_fields', function() {
    //非ログインユーザーのみ
    if (!is_user_logged_in()) {
        //キーを取得
        $key = get_option('bsc_key', null);
        if (!isset($key)) {
            $key = substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyz'), 0, 5);
            add_option('bsc_key', $key);
        }
        $name_key = get_option('bsc_name_key', null);
        if (!isset($name_key)) {
            $name_key = substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyz'), 0, 5);
            add_option('bsc_name_key', $name_key);
        }
        //投稿時間の検出用隠し入力欄
        echo '<p class="bsc_spam_check" style="display:none">';
        echo '<input type="text" id="bsc_spam_check_1" name="bsc_spam_check_'.$name_key.'_1" value="">';
        //echo '<input type="text" id="bsc_spam_check_2" name="bsc_spam_check_'.$name_key.'_2" value="">';
        echo '<input type="text" name="bsc_spam_check_'.$name_key.'_3" value="">';
        echo '</p>';
        //投稿時間の検出用JSのフッターに挿入
        add_action( 'wp_footer', 'bsc_spam_check_js', 99 );
    }
} );
add_action('comment_form_after', function() {
    //非ログインユーザーのみ
    if (!is_user_logged_in()) {
        echo '<noscript><p id="bsc_announcement">Please enable JavaScript to post your comment.</p></noscript>';
    }
} );

//JS
function bsc_spam_check_js()  {
    echo '<script async defer>';
    echo 'var bsc_spam=function(){document.getElementById("bsc_spam_check_1").value = "'.get_option('bsc_key').'";};setTimeout(bsc_spam, 10000);';
    //echo 'document.getElementById("commentform").onsubmit=function(){document.getElementById("bsc_spam_check_2").value="'.wp_create_nonce('bsc_check').'"};';
    echo '</script>';
}

//プラグイン下リンク
add_filter('plugin_action_links_'.plugin_basename(__FILE__), function ( $links ) {
    $add_bsc_link = '<a href="'.admin_url('edit-comments.php?page=block-spam-comment').'">'.__('Logs','Block-Spam-Comment').'</a>';
    array_unshift($links, $add_bsc_link);
    return $links;
});
//管理画面
add_action('admin_menu', function() {
    add_comments_page(
        'Block Spam Comment',
        'Block Spam Comment',
        'administrator',
        'block-spam-comment',
        'bsc_admin_page'
    );
});
function bsc_admin_page() {
    
    //タイムゾーンの設定
    date_default_timezone_set(get_option('timezone_string'));

    $logs = get_option('bsc_log');
    $log_number = get_option('bsc_log_number', 0);
    if (!is_array($logs) || $log_number === 0) {

        $list = "<tr><td colspan='4'>".__('No data','Block-Spam-Comment')."</td></tr>\n";
        $log_number = 0;
        $log_number_24 = 0;
        $begin_logs = '';

    } else {
        
        $logs = array_reverse($logs);
        $list = "";
        $time_now = $_SERVER['REQUEST_TIME'];
        $count = 0;
        $log_number_24 = 0;
        
        //scvデータダウンロード
        $csv_date = fopen( plugin_dir_path( __FILE__ ).'bsc_spam_log.csv', 'w+');

        //ログをループ
        foreach($logs as $log){
            //csvデータ保存
            fputcsv($csv_date, $log);
            //100個まで表に格納
            if($count < 100){
            ++$count;
            $list .= "<tr>";
            $list .= "<td>".date_i18n('m-d-Y H:i',$log['time'])."</td>";
            $list .= "<td>".$log['name']."</td>";
            $list .= "<td>".$log['ip']."</td>";
            $list .= "<td>".$log['agent']."</td>";
            $list .= "</tr>\n";
            }
            //直近24時間を数える
            if (($time_now - $log['time']) < 86400 ) {
                ++$log_number_24;
            }
        }
        //rewind($csv_date);
        //$csv_date_base64 = base64_encode(stream_get_contents($csv_date));
        fclose($csv_date);
    }

    //文章
    $h3_number_comments = __('The number of blocked Comments','Block-Spam-Comment');
    $span_24hours = __('Last 24 hours','Block-Spam-Comment');
    $span_all = __('ALL','Block-Spam-Comment');
    $h3_list_comments = __('The list of blocked Comments','Block-Spam-Comment');
    $thead_list = __('<tr><th>time</th><th>author</th><th>IP</th><th>user agent</th></tr>','Block-Spam-Comment');
    $p_max = __('The maximum number of saved logs is 9999. The older logs are deleted automatically.','Block-Spam-Comment');
    $a_download_csv = __('Download all data','Block-Spam-Comment');
    $p_download_csv = __('You can download a csv file with data of blocked comments.','Block-Spam-Comment');

?>
<div class="wrap">
    <h2>Block Spam Comment</h2>
    <h3><?php echo $h3_number_comments; ?></h3>
    <p class="postbox" style="padding: 15px;">
        <span style="color: #444;margin-right: 5px;"><?php echo $span_all; ?></span><span style="font-size: 24px;margin-right: 10px;"><?php echo $log_number; ?></span>
        <span style="color: #444;margin-right: 5px;"><?php echo $span_24hours; ?></span><span style="font-size: 24px;margin-right: 20px;"><?php echo $log_number_24; ?></span>
    </p>
    <h3><?php echo $h3_list_comments; ?></h3>
    <p><?php echo $p_max; ?></p>
    <table id="bsc-widget-table" class="wp-list-table striped widefat">
        <thead><?php echo $thead_list; ?></thead>
        <tbody>
            <?php echo $list; ?>
        </tbody>
    </table>
    <?php if ($log_number) { ?>
    <p>
        <a href="<?php echo plugins_url( 'bsc_spam_log.csv', __FILE__ );?>" class="button-secondary" style="margin-bottom: 5px;"><?php echo $a_download_csv; ?></a>
        <br><?php echo $p_download_csv; ?>
    </p>
    <?php } ?>
</div>
<?php
}

//管理画面のみ翻訳対応
if ( is_admin() ) {
    load_plugin_textdomain('Block-Spam-Comment', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}