<?php
/*
Plugin Name: Purge blackHat SEO
Description: Clean blackHat SEO infection
Plugin URI: https://github.com/EazyServer/purge_blackhat_seo
Version: 1.0.0
Author: EazyServer
Author URI:
License: GPL3
*/


add_action('admin_menu', 'purge_menu');
function purge_menu() {
	add_menu_page('Purge BH SEO Settings',
		'Purge BH SEO',
		'administrator',
		'purge-plugin-settings',
		'purge_settings_page',
		'dashicons-admin-generic');
}

function purge_settings_page() {
	?>
	<h1>Purge BlackHat SEO</h1>

	<table class="form-table" style="width: 100%;">
		<tr valign="top">
			<th scope="row">Infection signature</th>
			<td><input type="text" id="div_signature" style="width: 100%" placeholder="e.g: style='left: -461500px; position: absolute; top: -522100px'"/>
			(The more the specific signature the more targeted purge results.)</td>
		</tr>

		<tr valign="top">
			<th>Example Infection signature</th>
			<td><img src="<?php echo  plugin_dir_url( __FILE__ ).'/img/src2.png'; ?>"></td>
		</tr>

		<tr valign="top">
			<th scope="row">Dry run</th>
			<td><input type="checkbox" id="dry_run" checked /> Run without purging.</td>
		</tr>

		<tr valign="top">
			<th scope="row">Post Types</th>
			<td><input type="checkbox" id="posts" checked />Posts &nbsp;<input type="checkbox" id="pages" checked/>Pages</td>
		</tr>

		<tr valign="top">
			<th scope="row">Post Status</th>
			<td>
				<input type="checkbox" id="publish" checked />Publish &nbsp;
				<input type="checkbox" id="pending_" checked />Pending &nbsp;
				<input type="checkbox" id="draft" checked />Draft &nbsp;
				<input type="checkbox" id="autodraft" checked />Auto-draft &nbsp;
				<input type="checkbox" id="future" checked />Future &nbsp;
				<input type="checkbox" id="private_" checked />Private &nbsp;
				<input type="checkbox" id="inherit" checked />Inherit &nbsp;
				<input type="checkbox" id="trash" checked />Trash &nbsp;
			</td>
		</tr>


		<tr valign="top">
			<td colspan="2">
				<div id="notif">
					<ul>
						<li>Total Posts:</li>
						<li>Matched Posts :</li>
						<li>Cured Posts :</li>
					</ul>
				</div>
			</td>
		</tr>

	</table>
	<font color="red">*Always make a backup of the database (Un-tick "Dry run" to start purging).</font>
	<?php submit_button('Run'); ?>
	<script>
	jQuery(document).ready(function(){
		jQuery('#submit').click(function () {

			var signature = getSignature();

			if(signature)
			{
				jQuery('#submit').prop('disabled', true).val('Processing...');

				var dry_run = jQuery('#dry_run').is(':checked')?1:0;

				var posts_types = {
					posts:jQuery('#posts').is(':checked')?1:0,
					pages:jQuery('#pages').is(':checked')?1:0
				};

				var posts_status = {
					publish:jQuery('#publish').is(':checked')?1:0,
					pending:jQuery('#pending_').is(':checked')?1:0,
					draft:jQuery('#draft').is(':checked')?1:0,
					'auto-draft':jQuery('#autodraft').is(':checked')?1:0,
					future:jQuery('#future').is(':checked')?1:0,
					'private':jQuery('#private_').is(':checked')?1:0,
					inherit:jQuery('#inherit').is(':checked')?1:0,
					trash:jQuery('#trash').is(':checked')?1:0
				};

				var data = {
					'action': 'purge_blackhat_seo',
					'signature': signature,
					'dry_run':dry_run,
					'nonce':'<?php echo wp_create_nonce("Purge_blackHat_SEO_call"); ?>',
					'posts_types': JSON.stringify(posts_types),
					'posts_status': JSON.stringify(posts_status)
				};

				jQuery.post(ajaxurl, data, function(response) {

					var responseObject  = JSON.parse(response);
					console.log(responseObject);
					jQuery('#notif').html('<ul><li> Total: '+responseObject.total
						+'</li><li>Matched: <b>'+responseObject.matched
						+'</b></li><li>Cured: <b>'+responseObject.cleaned
						+'</b></li></ul>');

					jQuery('#submit').prop('disabled', false).val('Run');

				});
			}
		});

		function getSignature()
		{
			var sign = jQuery('#div_signature').val();
			if(!sign)
			{
				alert('Please enter the infected div signature!');
			}
			return sign||false;
		}
	});
	</script>
	<?php
}

add_action( 'wp_ajax_purge_blackhat_seo', '_purge_blackhat_seo_callback' );

function _purge_blackhat_seo_callback() {

	if ( !wp_verify_nonce( $_POST['nonce'], "Purge_blackHat_SEO_call")) {
		exit("No dodgy business please");
	}

	if(is_admin())
	{
		$signature = stripcslashes($_POST['signature']);
		$dry_run = stripcslashes($_POST['dry_run']);
		$posts_types_raw = json_decode(stripcslashes($_POST['posts_types']),true);
		$posts_status_raw = json_decode(stripcslashes($_POST['posts_status']));
		$pattern = '/<div '.$signature.'.*?<\/div>/is';

		foreach($posts_status_raw as $k=>$flag)
		{
			if($flag==1)
			{
				$posts_status[] = $k;
			}
		}

		$posts_array = array();

		if($posts_types_raw['posts']==1)
		{
			$posts_array = get_posts(array(
				                         'posts_per_page' => -1,
				                         'posts_type' => 'post',
				                         'post_status' => $posts_status,
			                         ));// get all
		}

		$pages_array = array();

		if($posts_types_raw['pages']==1)
		{
			$pages_array = get_pages(array(
				                         'posts_per_page' => -1,
				                         'posts_type' => 'page',
				                         'post_status' => $posts_status,
			                         ));// get all
		}

		$cleaned = 0;
		$matched = 0;

		$total_array = array_merge($posts_array, $pages_array);

		if(is_array($total_array))
			foreach($total_array as $post)
			{
				if(preg_match($pattern, $post->post_content))
				{
					if($dry_run != 1)
					{
						wp_update_post(array(
							               'ID' => $post->ID,
							               'post_content' => preg_replace($pattern, '', $post->post_content)
						               ));
						$cleaned++;
					}
					$matched++;
				}
			}
		else
		{
			$total_array = array();
		}

		$total_posts = count($total_array);

		echo json_encode(array(
			                 'total' =>$total_posts,
			                 'matched' =>$matched ,
			                 'cleaned' => $cleaned
		                 ));
	}
	else
	{
		wp_die('This request is not authorised');
	}

	wp_die();
}
