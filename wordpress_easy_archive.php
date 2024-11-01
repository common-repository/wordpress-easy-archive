<?php
/*
Plugin Name: WordPress Easy Archive
Version: 1.2
Plugin URI: http://crispijnverkade.nl/blog/wordpress-easy-archive
Description: WordPress Easy Archive will create an image based archive. All the images are stored in the database to keep the speed as you used to.
Author: Crispijn Verkade
Author URI: http://crispijnverkade.nl/

Copyright (c) 2009
Released under the GPL license
http://www.gnu.org/licenses/gpl.txt

    This file is part of WordPress.
    WordPress is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

	INSTALL: 
	Just install the plugin in your blog and activate
*/ 

/**
* Add some style to the archive
*/
function wpea_header(){
	echo '<link rel="stylesheet" type="text/css" media="screen" href="'.get_settings('siteurl').'/wp-content/plugins/wordpress-easy-archive/wp_easy_archive.css" />'.PHP_EOL;
}

/**
* Get the folder where wordpress is installed
*/
function wpea_get_wpfolder(){
	$blog_url = stripslashes(get_option('siteurl'));
	
	$domain = 'http://'.$_SERVER['HTTP_HOST'].'/';
	$folder =  str_replace($domain,'',$blog_url);	
	
	if($folder != $blog_url){
		return $folder.'/';
	}else{
		return false;
	}
}

/**
* Get the image
*/
function wp_get_image($post_id){
	global $wpdb, $table_prefix;
	
	$sql = "SELECT
				post_name,
				post_title,
				guid			
			FROM ".$table_prefix."posts
			WHERE post_type = 'easy_preview'
			AND post_parent = ".(int) $post_id;
	$res = $wpdb->get_results($sql);
	
	if(!empty($res)){
		foreach($res as $row){
			return '<a href="'.get_permalink($post_id).'"><img src="'.$row->guid.'" alt="'.$row->post_name.'" title="'.$row->post_title.'" class="wpea_image" /></a>';
		}		
	}else{
		return false;
	}			
}

/**
* Create an archive image if it is not existing
*/
function wp_create_image($post_id){
	global $wpdb, $table_prefix;
	$blog_url = stripslashes(get_option('siteurl'));
	$folder = wpea_get_wpfolder();
	
	$sql = "SELECT
				post_content
			FROM
				".$table_prefix."posts
			WHERE
				ID = ".(int) $post_id;
	$res = $wpdb->get_results($sql);
	
	if(empty($res)){
		return false;
	}else{
		foreach($res as $row){
			$img = preg_match_all('/(src|alt|title)=("[^"]*")/i', $row->post_content, $matches);
		}
		
		if(empty($matches)){
			//no match
			return false;
		}else{
			$post_title = explode('"',$matches[0][0]);  //title
			$guid = explode('"',$matches[0][1]); 		//src
			$post_name = explode('"',$matches[0][2]); 	//alt
			
			$guid = str_replace($blog_url,'',$guid[1]);
			
			if(wpea_get_wpfolder()){
				$filepath = $_SERVER['DOCUMENT_ROOT'].'/'.str_replace('/','',$folder).$guid;
			}else{
				$filepath = $_SERVER['DOCUMENT_ROOT'].$guid;
			}
			
			if(!file_exists($filepath) || empty($guid)){
				return false;
			}else{
				require_once('scripts/class_upload.php');
					
				$handle = new upload($filepath);
				
				switch (get_option('wpea_immod')) {
					case 'resize':
						//only resize the image and use the max width
						$handle->image_resize           = true;
						$handle->image_x 				= get_option('wpea_width');
						$handle->image_ratio_y        	= true;
						$handle->jpeg_quality			= get_option('wpea_quality');
						break;
					case 'fill':
						//resize and fill the image
						$handle->image_resize           = true;
						$handle->image_ratio_fill      	= get_option('wpea_ratiofill');
						$handle->image_x				= get_option('wpea_width');
						$handle->image_y				= get_option('wpea_height');
						$handle->jpeg_quality			= get_option('wpea_quality');
						break;
					case 'crop':
						//just crop the image
						$handle->image_resize           = true;
						$handle->image_ratio_crop 		= true;
						$handle->image_x				= get_option('wpea_width');
						$handle->image_y				= get_option('wpea_height');
						break;
				}
				
				//specify the destination folder
				$handle->Process($_SERVER['DOCUMENT_ROOT'].'/'.$folder.'/wp-content/'.get_option('wpea_folder').'/');
				
				if(!$handle->processed){
					echo 'An error occured while processing the large image';
				}else{
					$new_file = $handle->file_dst_name;
					
					$sql = "INSERT INTO
								".$table_prefix."posts
							(
								post_author,
								post_date,
								post_date_gmt,
								post_title,
								post_status,
								post_name,
								post_modified,
								post_modified_gmt,
								guid,
								post_type,
								post_mime_type,
								post_parent
							) VALUES (
								1,
								NOW(),
								NOW(),
								'".$post_title[1]."',
								'inheret',
								'".$post_title[1]."',
								NOW(),
								NOW(),
								'".$blog_url.'/wp-content/'.get_option('wpea_folder').'/'.$new_file."',
								'easy_preview',
								'".$handle->file_src_mime."',
								".(int) $post_id."
							)";
					$res = $wpdb->query($sql);

					if(!$res){
						return $sql;	
					}else{
						return '<a href="'.get_permalink($post_id).'"><img src="'.$blog_url.'/wp-content/'.get_option('wpea_folder').'/'.$new_file.'" alt="'.$post_name[1].'" title="'.$post_title[1].'" class="wpea_image" /></a>';
					}
			}
			}
		}
	}
}

/**
* Place the archive into the content
*/
function wp_get_archive($content){
	global $wpdb, $post, $table_prefix;

	if(!preg_match('|<!--archive-->|', $content)) {
		return $content;
	}else{
		$sql = "SELECT
					p.post_title,
					p.ID
				FROM ".$table_prefix."posts AS p
				WHERE p.post_type = 'post'
				AND post_status = 'publish'
				ORDER BY p.post_date DESC";
		$res = $wpdb->get_results($sql);
		
		if(!$res){
			$archive = '<p><strong>An error occured processing the archive from the database</strong></p>';
		}else{
			$archive =  '<div id="wpea_container">';
			
			foreach($res as $row){
				$image = wp_get_image($row->ID);
				
				if(!$image){
					$create = wp_create_image($row->ID);
					
					if(!empty($create)){
						$archive .=  $create;
					}else{
						$archive .=  '<a href="'.get_permalink($row->ID).'"><img src="'.get_option('siteurl').'/wp-content/plugins/wordpress-easy-archive/images/no-image.jpg" alt="No Image" title="'.$row->post_title.'" width="'.get_option('wpea_width').'" height="'.get_option('wpea_height').'" class="wpea_image" /></a>';
					}
				}else{
					$archive .= $image;
				}
			} //end foreach
				$archive .= '<div class="clear"></div>';
			$archive .= '</div>';
		}
		
		return str_replace('<!--archive-->', $archive, $content);
	}
}

/**
* Remove all the images and database records
*/
function wpea_reset_archive(){
	global $wpdb, $post, $table_prefix;
	
	$blog_url = stripslashes(get_option('siteurl'));
	$folder = wpea_get_wpfolder();
	
	$sql = "SELECT
				ID,
				guid
			FROM ".$table_prefix."posts
			WHERE post_type = 'easy_preview'";
	$res = $wpdb->get_results($sql);
	
	if($res){
		foreach($res as $row){
			$filepath = $_SERVER['DOCUMENT_ROOT'].'/'.$folder.str_replace($blog_url,'',$row->guid);
			
			if(file_exists($filepath)){
				if(unlink($filepath)){
					$sql = "DELETE FROM ".$table_prefix."posts
							WHERE id = ".$row->ID."
							LIMIT 1";
					$res = $wpdb->query($sql);
					
					if(!$res){
						echo $sql;
					}
				}
			}
		} //end foreach
	}
}

/**
* Remove the plugin from the active plugin list
*/
function wpea_remove_plugin(){
	global $wpdb;
	
	/* Delete al the rows from the posts table */
	$sql = "DELETE FROM design_posts WHERE post_type = 'easy_preview'";
	$res = $wpdb->query($sql);
}


/**
* Add the options button to the sessings list in the admin environment
*/
function wpea_add_options_page(){
	add_options_page('Easy Archive Settings', 'Easy Archive', 8, basename(__FILE__),'wpea_subpanel');
}

/**
* Create the options page
*/
function wpea_subpanel(){
	load_plugin_textdomain('wpea',$path = $wpcf_path);
	$location = get_option('siteurl') . '/wp-admin/options-general.php?page=wordpress_easy_archive.php'; // Form Action URI
	
	/*Lets add some default options if they don't exist */
	add_option('wpea_folder', __('wpea', 'wpea'));
	add_option('wpea_width', __(200, 'wpea'));
	add_option('wpea_height', __(200, 'wpea'));
	add_option('wpea_quality', __(100, 'wpea'));
	add_option('wpea_immod', __('resize', 'wpea'));
	
	/*check form submission and update options */
	if ('process' == $_POST['stage']){
		update_option('wpea_width', $_POST['wpea_width']);
		update_option('wpea_height', $_POST['wpea_height']);
		update_option('wpea_quality', $_POST['wpea_quality']);
		update_option('wpea_immod', $_POST['wpea_immod']);
		
		//reset the archive
		wpea_reset_archive();
	}
	
	/*Get options for form fields */
	$wpea_width = stripslashes(get_option('wpea_width'));
	$wpea_height = stripslashes(get_option('wpea_height'));
	$wpea_quality = stripslashes(get_option('wpea_quality'));
	$wpea_immod = get_option('wpea_immod');
	?>
	
	<div class="wrap" id="leave-a-reply"> 
        <h2><?php _e('Easy Archive Settings', 'wpcf') ?></h2>
        <p>Change the settings for the images for the archive. If you change the settings the archive will be reset and all the images are deleted.</p>
        <form name="form1" method="post" action="<?php echo $location ?>&amp;updated=true">
            <input type="hidden" name="stage" value="process" />
            <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="wpea_width">Image Width</label></th>
                <td><input name="wpea_width" type="text" id="wpea_width" value="<?php echo $wpea_width; ?>" size="10" class="regular-text code" /></td>
            </tr>
            
            <tr valign="top">
                <th scope="row"><label for="wpea_height">Image Height</label></th>
                <td><input name="wpea_height" type="text" id="wpea_height" value="<?php echo $wpea_height; ?>" size="10" class="regular-text code" /></td>
            </tr>
            
            <tr valign="top">
                <th scope="row"><label for="wpea_quality">Image Quality (%)</label></th>
                <td><input name="wpea_quality" type="text" id="wpea_quality" value="<?php echo $wpea_quality; ?>" size="10" class="regular-text code" /></td>
            </tr>

            <tr valign="top">
                <th scope="row"><label for="wpea_immod">Image modification</label></th>
              	<td>
                    <input name="wpea_immod" type="radio" value="resize" <?php if($wpea_immod == 'resize') {?> checked="checked" <?php } ?> /><em> Resize only. The image will be re-sized to the maximum width.</em><br />
                    <input name="wpea_immod" type="radio" value="fill" <?php if($wpea_immod == 'fill') {?> checked="checked" <?php } ?> /><em> Resize the image and and fill it to the max width and height.</em><br />
                	<input name="wpea_immod" type="radio" value="crop" <?php if($wpea_immod == 'crop') {?> checked="checked" <?php } ?> /><em> Crop the image from the center.</em><br />
                    <p>Would you like to see examples of these options? Go to <a href="http://www.crispijnverkade.nl/blog/wordpress-easy-archive">the documentation page</a>.</p>
				</td>
            </tr>

	        </table>
            <p class="submit">
              <input type="submit" name="Submit" value="Update Options &raquo;" class="button-primary" />
            </p>
		</form>
	</div>
<?php
}

/**
* Uninstall function
*/
function wpea_uninstall(){
	//remove the options
	remove_option('wpea_width');
	remove_option('wpea_height');
	remove_option('wpea_ratiofill');
	remove_option('wpea_crop');
	remove_option('wpea_quality');
}

add_action('admin_menu', 'wpea_add_options_page');

add_action('wp_head', 'wpea_header');
add_filter('the_content', 'wp_get_archive', 7);
?>