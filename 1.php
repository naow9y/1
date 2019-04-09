<?php
/*
Plugin Name: BF Click Counter
Author: Taichi MARUYAMA
Plugin URI: http://maruyama.breadfish.jp/
Description: シンプルなクリックカウンターです。
Version: 0.11
Author URI: http://maruyama.breadfish.jp/
Text Domain: bf-click-counter
*/

global $wpdb;
global $bf_click_counter;		// IDをキーにしてカウント数を格納
global $bf_click_ip;			// IDをキーにしてIPアドレスを格納

/**
 * DBで使うテーブル名を返す
 * 
 * @access public
 * @return void
 */
function bf_click_counter_get_table_name() {

	global $wpdb;
	return $wpdb->prefix . "bf_click_counter";

}

/**
 * アクティベーション。テーブルの作成を行う。
 * 
 * @access public
 * @return void
 */
function bf_click_counter_activation() {

	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	$table_name = bf_click_counter_get_table_name();	

	$sql = "CREATE TABLE $table_name (
	  id mediumint(9) NOT NULL AUTO_INCREMENT,
	  keyname text NOT NULL,
	  count int NOT NULL,
	  ipaddress text NOT NULL,
	  register_datetime datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
	  update_datetime datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
	  UNIQUE KEY id (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );	

}

register_activation_hook(__FILE__, 'bf_click_counter_activation');

/**
 * ロード時に変数を初期化する.
 * 
 * @access public
 * @return void
 */
function bf_click_counter_initialize() {
	global $wpdb, $bf_click_counter, $bf_click_ip;
	$bf_click_counter = array();
	$table_name = bf_click_counter_get_table_name();	

	$results = $wpdb->get_results("SELECT * FROM $table_name");
	foreach($results as $one) {
		$bf_click_counter[$one->keyname] = $one->count;
		$bf_click_ip[$one->keyname] = $one->ipaddress;
	}
}
add_action('init', 'bf_click_counter_initialize');

/**
 * ショートコードの処理。ボタン（カウント数）を表示する
 * 
 * @access public
 * @param mixed $atts
 * @return void
 */
function bf_click_counter_display($atts) {
	global $bf_click_counter;

    extract(shortcode_atts(array(
        'id' => '0',
    ), $atts));
  
	// カウンターがすでにある場合
	if (array_key_exists($id, $bf_click_counter)) {
		return '<a href="javascript:void(0);" class="btn btn-default bf-click-counter" data-id="' . $id . '">いいね(<span class="count">' . $bf_click_counter[$id] . '</span>)</a>';
	}

	// カウンターがない場合はカウント数ゼロでボタンを表示
	return '<a href="javascript:void(0);" class="btn btn-default bf-click-counter" data-id="' . $id . '">いいね(<span class="count">0</span>)</a>';
}
add_shortcode('bfcc', 'bf_click_counter_display');

/**
 * JavaScript(Ajax)の出力（いいねボタンの押下を受け付ける）
 * 
 * @access public
 * @return void
 */
function bf_click_counter_ajax() {
?>
    <script>
        var bf_ajaxurl = '<?php echo admin_url( 'admin-ajax.php'); ?>';

		jQuery(function() {
			jQuery('.bf-click-counter').click(function() {
				var self = this;
			    jQuery.ajax({
			        type: 'POST',
			        url: bf_ajaxurl,
			        data: {
			            'id' : jQuery(this).attr('data-id'),
						'action' : 'bf_click_counter_countup',
			        },
			        success: function( response ){
			         	jQuery(self).find('.count').html(response);   
			        }
			    });				

				return false;
			});
		})

    </script>
<?php
}
add_action( 'wp_head', 'bf_click_counter_ajax');

/**
 * Ajaxの受付処理
 * 
 * @access public
 * @return void
 */
function bf_click_counter_countup(){
	bf_click_counter_initialize();
	global $wpdb, $bf_click_counter, $bf_click_ip;
	$id = $_POST['id'];
	$ipaddr = $_SERVER["REMOTE_ADDR"];
	$nowdate = date('Y-m-d h:m:s');	// 登録日付

	// カウンターがすでにある場合、インクリメントしてDBをアップデート
	if (array_key_exists($id, $bf_click_counter)) {
		// 同じIPからの連続いいねは阻止
		if ($bf_click_ip[$id] != $ipaddr) {
			$bf_click_counter[$id]++;
			$wpdb->update(bf_click_counter_get_table_name(), array('count' => $bf_click_counter[$id], 'ipaddress' => $ipaddr, 'update_datetime' => $nowdate), array('keyname' => $id));
		}

	// カウンターがない場合、DBにインサート
	} else {
		$bf_click_counter[$id] = 1;		// 初期値は1
		$wpdb->insert(bf_click_counter_get_table_name(), array('keyname' => $id, 'count' => 1, 'ipaddress' => $ipaddr, 'register_datetime' => $nowdate));
	}
	echo $bf_click_counter[$id];
    die();
}
add_action( 'wp_ajax_bf_click_counter_countup', 'bf_click_counter_countup' );
add_action( 'wp_ajax_nopriv_bf_click_counter_countup', 'bf_click_counter_countup' );