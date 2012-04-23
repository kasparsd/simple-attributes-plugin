<?php
/*
 Plugin Name: Simple Attributes
 Plugin URI: 
 Description: Add simple attributes to posts and custom post types
 Version: 1.6.1
 Author: Kaspars Dambis
 Author URI: http://konstruktors.com
 Text Domain: simple-attributes
 */


add_action('admin_menu', 'cpt_atts_submenu_page');

function cpt_atts_submenu_page() {
	$post_types = get_post_types();

    foreach($post_types as $post_type_name) {
        $url = 'edit.php?post_type=' . $post_type_name;

        if ($post_type_name == 'post')
        	$url = 'edit.php';
        	
        if (apply_filters('ctp_atts_enable', true, $post_type_name) == true) {
        	add_submenu_page($url, 'Simple Attributes', 'Simple Attributes', 'add_users', 'cpt_atts_' . $post_type_name, 'cpt_atts_admin');
        	register_setting('cpt_atts_' . $post_type_name, 'cpt_atts_' . $post_type_name, 'cpt_atts_admin_validate');
        }
    }
}


// Enable support for this plugin as a symlink
add_filter('plugins_url', 'plugins_url_symlink_fix', 10, 3);

function plugins_url_symlink_fix($url, $path, $plugin) {
	if (strstr($plugin, basename(__FILE__)))
		return str_replace(dirname(__FILE__), '/' . basename(dirname($plugin)), $url);

	return $url;
}


add_action('admin_enqueue_scripts', 'cpt_atts_scripts');

function cpt_atts_scripts() {
	wp_enqueue_style('thickbox');
	wp_enqueue_style('simple-attributes-css', plugins_url('/sap-admin-css.css', __FILE__));
	wp_enqueue_script('simple-attributes-js', plugins_url('/sap-admin-js.js', __FILE__), array('jquery', 'jquery-ui-sortable', 'media-upload'));
}


add_action('admin_init', 'cpt_atts_git_updater');

function cpt_atts_git_updater() {
	// A modified version of https://github.com/jkudish/WordPress-GitHub-Plugin-Updater
	require_once('updater/updater.php');

	$config = array(
		'slug' => basename(dirname(__FILE__)) . '/' . basename(__FILE__),
		'proper_folder_name' => basename(dirname(__FILE__)),
		'api_url' => 'https://api.github.com/repos/kasparsd/simple-attributes-plugin',
		'raw_url' => 'https://raw.github.com/kasparsd/simple-attributes-plugin/master',
		'github_url' => 'https://github.com/kasparsd/simple-attributes-plugin',
		'zip_url' => 'http://github.com/kasparsd/simple-attributes-plugin/zipball/master',
		'sslverify' => false,
		'requires' => '3.0',
		'tested' => '3.4',
	);

	new WPGitHubUpdater($config);
}


// Disable sslverify for HTTPS plugin updates from github
add_action('http_request_args', 'cpt_disable_sslverify', 10, 2);

function cpt_disable_sslverify($args, $url) {
	if (strstr($url, 'simple-attributes'))
		$args['sslverify'] = false;
	
	return $args;
}


add_action('post_edit_form_tag', 'add_upload_support_to_post_form');

function add_upload_support_to_post_form($form) {
    echo ' enctype="multipart/form-data"';
}


add_image_size('sap_thumb', 60, 60, true);

function cpt_atts_admin() {
	$post_type_id = $_GET['post_type'];
	$post_type = get_post_type_object($post_type_id);

	// if ($post_type == false)
	//	return; // This should never happen

	// Get options
	$option_name = 'cpt_atts_' . $post_type_id;
	$cpt_opts = get_option($option_name);

	if (isset($cpt_opts['atts'])) {
		// MIGRATE from 0.1
		$cpt_opts['groups'] = array('old' => array('atts' => $cpt_opts['atts']));
		unset($cpt_opts['atts']);
	}

	if (empty($cpt_opts['groups']))
		$cpt_opts = array('groups' => array());

	// Make sure there is a dummy attribute which adds the empty attr.
	$cpt_opts['groups'] = array('%group%' => array('atts' => array('%%%' => array()))) + $cpt_opts['groups'];

	$sap_types = array(
		'text' => __('Text'),
		'textarea' => __('Textarea'),
		'dropdown' => __('Dropdown'),
		'checkboxes' => __('Checkboxes'),
		'radioboxes' => __('Radioboxes'),
		'image' => __('Image'),
		'file' => __('File')
	);

	?>

	<div class="wrap">
		<?php screen_icon(); ?> 
		<h2><?php printf(__('Simple Attributes for <strong>%s</strong>'), $post_type->labels->name); ?></h2>
		
		<form id="sap-atts" method="post" action="options.php">

			<?php settings_fields($option_name); ?>
			<input type="hidden" name="<?php echo $option_name; ?>[cpt]" value="<?php echo $option_name; ?>" />

			<ul>
				<?php
				$prev_group = -1;

				// Loop groups
				foreach ($cpt_opts['groups'] as $group => $attrs) :
					$group_prefix = $option_name . '[groups]['. $group .']';
				?>

				<?php if ($group == '%group%') : ?>
					<li class="group groupframe">
						<div class="header">
							<label><?php _e('Group Name'); ?>: <input type="text" name="<?php echo $group_prefix; ?>[name]" value="" /></label>
							<label>Group ID: <input type="text" class="sap-group" name="<?php echo $group_prefix; ?>[_id]" value="%group%" /></label>
							<label><input type="checkbox" name="<?php echo $group_prefix; ?>[multiple]" value="1" /> <?php _e('Multiple'); ?></label>
						</div>
						<ul>
				<?php elseif ($prev_group !== $group) : ?>
						
					<li class="group">
						<div class="header">
							<label>Group Name: <input type="text" name="<?php esc_attr_e($group_prefix); ?>[name]" value="<?php esc_attr_e($attrs['name']); ?>" /></label>
							<label>Group ID: <input type="text" class="sap-group" name="<?php esc_attr_e($group_prefix); ?>[_id]" value="<?php esc_attr_e($attrs['_id']); ?>" /></label>
							<label><input type="checkbox" name="<?php echo $group_prefix; ?>[multiple]" value="1" <?php if ($attrs['multiple']) : ?>checked="checked"<?php endif; ?> /> <?php _e('Multiple'); ?></label>
						</div>
						<ul>
				<?php endif; ?>

				<?php
				// Loop attributes
				if (!isset($attrs['atts']))
					$attrs['atts'] = array();

				foreach ($attrs['atts'] as $i => $atts) : 
					if (empty($atts)) // This is necessary to generate a hidden template
						$i = '%%%';
					
					$input_prefix = $group_prefix .  '[atts][' . $i . ']';
				?>

				<li class="sap-common-wrap" id="sap-attr-<?php echo $group; ?>-<?php echo $i; ?>">
				
				<fieldset>
					<div class="sap-common-options">
						<a class="sap-delete-attr" href="#sap-attr-<?php echo $group; ?>-<?php echo $i; ?>"><?php _e('Delete'); ?></a>
						<label>
							<?php _e('Label:') ?> 
							<input type="text" class="sap-attr-name" name="<?php echo $input_prefix; ?>[name]" value="<?php esc_attr_e($atts['name']) ?>" />
						</label>						
						<label><?php _e('Type:') ?> 
							<select name="<?php echo $input_prefix; ?>[_type]" id="sap-type-<?php echo $i; ?>">
								<?php 
									foreach ($sap_types as $sap_type_val => $sap_type_name) : 
										$checked = '';
										if ($sap_type_val == $atts['_type'])
											$checked = 'selected="selected"';
										printf('<option value="%s" %s>%s</option>', $sap_type_val, $checked, $sap_type_name);
									endforeach; 
								?>
							</select>
						</label>						
						<label>
							<?php _e('ID:') ?>
							<input class="sap-id" type="text" name="<?php echo $input_prefix; ?>[_id]" value="<?php esc_attr_e($atts['_id']) ?>" />
						</label>
						<label class="sap-label-help">
							<?php _e('Help Text:') ?>
							<input class="sap-help" name="<?php echo $input_prefix; ?>[_help]" value="<?php esc_attr_e($atts['_help']) ?>" />
						</label>
					</div>
					
					<div class="sap-adv-type-options sap-type-<?php echo $i; ?> sap-type-<?php echo $i; ?>-dropdown sap-type-<?php echo $i; ?>-checkboxes sap-type-<?php echo $i; ?>-radioboxes">
						<h3><?php _e('Options'); ?></h3>
						<a class="button add-adv-type-options" href="#add-adv-type-options"><?php _e('Add New Option'); ?></a>
						<ul>
							<?php 
								$adv_template = '<li id="adv-type-option-%2$s"><label class="label-adv-label">Label: <input type="text" name="%1$s[freeform][%2$s][name]" value="%3$s" /></label> <label class="label-adv-id">ID: <input class="sap-id" type="text" name="%1$s[freeform][%2$s][_id]" value="%2$s" /></label> <a class="sap-delete-attr" href="#adv-type-option-%2$s">'.  __('Delete') . '</a></li>';
								
								if (!empty($atts['freeform']))
									foreach ($atts['freeform'] as $adv_id => $adv_val)
										printf($adv_template, $input_prefix, esc_attr($adv_id), esc_attr($adv_val['name']));

								// Print the wireframe
								printf(str_replace('<li ', '<li class="frame"', $adv_template), $input_prefix, '%advanced%', '');
							?>
						</ul>
					</div>

				</fieldset>
				</li>

				<?php 
					$prev_group = $group;
				?>

				<?php endforeach; // End items ?>
					</ul>
					<p class="add-attr-wrap">
						<a class="add-attr button" href="#add-attr"><?php _e('Add Attribute'); ?></a>
						<input type="submit" class="button-primary" value="<?php esc_attr_e('Save All'); ?>" />
					</p>
				</li>

				<?php endforeach; // End groups ?>
			</ul>

			<p class="submit">
				<a id="add-group" class="button" href="#add-group"><?php _e('Add Group'); ?></a>
				<!--<input type="submit" class="button-primary" value="<?php esc_attr_e('Save'); ?>" />-->
			</p>

			<div class="advanced">
				<h3>Advanced Tools</h3>
				<h4>Import</h4>
				<textarea name="<?php echo $option_name; ?>[import]"></textarea>
				<h4>Export</h4>
				<textarea><?php unset($cpt_opts['cpt']); echo serialize($cpt_opts); ?></textarea>
			</div>

			<pre style="hidden"><small><?php print_r($cpt_opts); ?></small></pre>
		</form>
	</div>

	<?php	
}


function cpt_atts_admin_validate($input) {
	// IMPORT / EXPORT
	if (!empty($input['import']))
		return unserialize($input['import']);

	if (empty($input['groups']))
		return $input;

	// Move all _id to array keys, create assoc array
	$input['groups'] = create_attr_assoc($input['groups']);

	foreach ($input['groups'] as $group_id => $atts)
		if (!empty($atts['atts']))
			foreach ($atts['atts'] as $att)
				$input['in_group'][$att['_id']] = $group_id;

	return $input;
}


function create_attr_assoc($arr) {
	$out = array();

	if (is_array($arr)) {
		unset($arr['%group%']);
		unset($arr['%%%']);
		unset($arr['%advanced%']);

		if (in_array($arr['_type'], array('text', 'file', 'image', 'textarea')))
			unset($arr['freeform']);
	} else {
		return $arr;
	}

	if (is_array($arr) && empty($arr))
		return null;

	foreach ($arr as $id => $val) {
		if (is_array($val) && isset($val['_id'])) {
			if ($val['_id'] != '') {
				$out[esc_attr($val['_id'])] = create_attr_assoc($val);
			} else {
				if (!empty($val['name']))
					$out[$id] = create_attr_assoc($val);
			}
		} elseif (is_array($val) && !empty($val)) {
			$out[$id] = create_attr_assoc($val);
		} else {
			$out[$id] = $val;
		}
	}

	return $out;
}


add_action('add_meta_boxes', 'cpt_atts_meta_boxes_init');

function cpt_atts_meta_boxes_init() {
	$post_types = get_post_types();

    foreach($post_types as $post_type_name) {
    	$cpt_atts = get_option('cpt_atts_' . $post_type_name);

		if (empty($cpt_atts) || !isset($cpt_atts['groups']))
			continue;

		foreach ($cpt_atts['groups'] as $group_id => $atts) {
			if (empty($atts['atts']))
				continue;

			// Add every group box
			add_meta_box( 
		   		'cpt_atts_meta_box_' . $post_type_name . $group_id,
		    	$atts['name'],
		    	'cpt_atts_meta_box',
		    	$post_type_name,
		    	'normal',
		    	'high',
		    	$atts
			);
    	}

    	// Add the Advanced Tools box
    	add_meta_box( 
	   		'cpt_atts_advanced_metabox_init',
	    	'Simple Attribute Tools',
	    	'cpt_atts_advanced_metabox',
	    	$post_type_name,
	    	'normal',
	    	null,
	    	$atts
		);
    }	
}

function cpt_atts_advanced_metabox($post, $atts) {
	$attr_values = get_post_meta($post->ID, 'cpt_atts', true);
	?>

	<div class="import-export">
		<h4>Export</h4>
		<textarea name="sap-export"><?php echo serialize($attr_values); ?></textarea>

		<h4>Import</h4>
		<textarea name="sap-import"></textarea>
	</div>

	<pre class="hidden">
		<small><?php print_r(array_merge(array('cpt_atts' => $attr_values), $atts)); ?></small>
	</pre>
	
	<?php
}


function cpt_metabox_input_name($items) {
	return esc_attr('cpt_atts[' . implode('][', $items) . ']');
}


function cpt_atts_meta_box($post, $atts) {
	$attr_values = get_post_meta($post->ID, 'cpt_atts', true);

	if (!is_array($attr_values))
		$attr_values = array();	

	$metabox_id = '';
	$group_id = $atts['args']['_id'];
	$is_multiple = $atts['args']['multiple'];

	// Remove the dummy variable which was saved
	//if (isset($attr_values[$group_id]['%%%']))
	//	unset($attr_values[$group_id]['%%%']);

	if (!is_array($attr_values[$group_id]) || empty($attr_values[$group_id]))
		$attr_values[$group_id] = array(array());

	// Add a dummy variable for dynamic Add New field set
	if ($is_multiple)
		$attr_values[$group_id]['%%%'] = array();

	if ($is_multiple && count($attr_values[$group_id]) < 2)
		$attr_values[$group_id][] = array();

	foreach ($attr_values[$group_id] as $multiple_id => $meta) :
		$metabox_id = 'cpt_att-' . $group_id . '-' . $multiple_id;

	?>

	<table id="<?php esc_attr_e($metabox_id); ?>" class="cpt_atts <?php if (!is_numeric($multiple_id)) echo 'frame' ?>">
		<thead>
			<tr class="cpt_tools">
				<td colspan="2">
				<?php if ($is_multiple) : ?>
					<a href="#<?php esc_attr_e($metabox_id); ?>" class="cpt_atts_delete"><?php _e('Delete'); ?></a>
				<?php endif; ?>
				</td>
			</tr>
		</thead>

		<?php
		foreach ($atts['args']['atts'] as $i => $attr) :
			// Add input field attributes
			$input_name = cpt_metabox_input_name(array($group_id, $multiple_id, $attr['_id']));
			$input_id = str_replace(array('[', ']', ']['), '', $input_name);

			$input_attributes = array(
				'name' => $input_name,
				'id' => $input_id
			);
		?>

		<tr>
			<td class="sap-title-column">
				<label><?php echo $attr['name']; ?></label>
				<p class="sap-help"><?php echo $attr['_help']; ?></p>
			</td>
			<td class="sap-input-column sap-input-<?php echo $attr['_type'] ?>">
				<?php
					do_action('sap_metabox-' . $attr['_type'], $attr, $meta[$i], $input_attributes); 
				?>
			</td>
		</tr>
		<?php endforeach; ?>
	</table>

	<?php endforeach; ?>

	<p class="cpt_atts_submit">
		<?php if ($is_multiple) : ?>
			<a href="#add-new-set" class="button cpt_atts_new"><?php _e('Add New'); ?></a>
		<?php endif; ?>

		<input type="submit" class="button-primary" value="<?php esc_attr_e('Update'); ?>" />
	</p>

	<?php
}


add_action('sap_metabox-text', 'cpt_metabox_text', 10, 3);

function cpt_metabox_text($atts, $values, $input_attributes) {
	?>
		<input type="text" name="<?php esc_attr_e($input_attributes['name']); ?>" value="<?php esc_attr_e($values); ?>" />
	<?php
}


add_action('sap_metabox-image', 'cpt_metabox_image', 10, 3);

function cpt_metabox_image($atts, $meta, $input_attributes) {
	?>
		<?php //if (is_array($meta) && is_numeric($meta['file'])) :
			$image_attr = wp_get_attachment_image_src($meta['file'], 'large');
			?>
			<a href="<?php echo $image_attr[0]; ?>" id="<?php esc_attr_e($input_attributes['id']); ?>-image"><?php echo wp_get_attachment_image($meta['file'], 'sap_thumb') ?></a>
			<!--<label>
				<strong>Caption</strong> 
				<input type="text" name="<?php echo $prefix; ?>[title]" value="<?php esc_attr_e($meta['title']); ?>" />
			</label>-->

		<?php // endif; ?>

		<input type="hidden" id="<?php esc_attr_e($input_attributes['id']); ?>" name="<?php esc_attr_e($input_attributes['name']); ?>[file]" value="<?php if (is_array($meta)) esc_attr_e($meta['file']); ?>" />

		<ul class="sap-file-tools">
			<li class="upload">
				<label>
					<strong>Upload New Image</strong>
					<input type="file" name="<?php esc_attr_e($input_attributes['name']); ?>[file]" />
				</label>
			</li>
			<li class="choose">
				<?php 
					printf(__('or <a href="%s" rel="%s" title="Choose from the existing files" class="sap-choose-existing">choose from existing</a>'), 
						esc_url(get_upload_iframe_src('library') . '&tab=library'), 
						esc_attr($input_attributes['id'])); 
				?>
			</li>
			<li class="remove">
				<a id="sap-remove-file" href="#<?php esc_attr_e($input_attributes['id']); ?>"><?php _e('Remove'); ?></a>
			</li>
		</ul>

	<?php
}


add_action('wp_ajax_sap_get_file_preview', 'sap_get_file_preview');

function sap_get_file_preview() {
	die(wp_get_attachment_image($_POST['file_id'], 'sap_thumb'));
}

add_action('sap_metabox-radioboxes', 'cpt_metabox_radioboxes', 10, 3);

function cpt_metabox_radioboxes($atts, $meta, $input_attributes) {
	$input_id = $atts['_id'];

	foreach ($atts['freeform'] as $name => $value) :
		$selected = '';
		if ($name == $meta)
			$selected = 'checked="checked"';
	?>
		<label>
			<input type="radio" name="<?php esc_attr_e($input_attributes['name']); ?>" value="<?php echo $name; ?>" <?php echo $selected; ?> /> 
			<?php echo $value['name']; ?>
		</label>
	<?php 
	endforeach;
}


add_action('sap_metabox-dropdown', 'cpt_metabox_dropdown', 10, 3);

function cpt_metabox_dropdown($atts, $meta, $input_attributes) {
	$input_id = $atts['_id'];
	?>
	<select name="<?php esc_attr_e($input_attributes['name']); ?>">
		<option value=""><?php _e('Options'); ?></option>
		<?php 
			foreach ($atts['freeform'] as $name => $value) :
				$selected = '';
				if ($name == $meta)
					$selected = 'selected="selected"';

		?>
			<option value="<?php echo $name; ?>" <?php echo $selected; ?>><?php esc_attr_e($value['name']); ?></option>
		<?php endforeach; ?>
	</select>
	<?php
}

add_action('sap_metabox-checkboxes', 'cpt_metabox_checkboxes', 10, 3);

function cpt_metabox_checkboxes($atts, $meta, $input_attributes) {
	$input_id = $atts['_id'];

	if (!is_array($meta))
		$meta = array();

	foreach ($atts['freeform'] as $name => $value) :
		$selected = '';
		if (in_array($name, $meta))
			$selected = 'checked="checked"';
	?>
		<label>
			<input type="checkbox" name="<?php esc_attr_e($input_attributes['name']); ?>[]" value="<?php echo $name; ?>" <?php echo $selected; ?> /> 
			<?php echo $value['name']; ?>
		</label>
	<?php 
	endforeach;
}

add_action('save_post', 'cpt_atts_save_meta_box');

function cpt_atts_save_meta_box() {
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) 
		return;

	if ($_POST['_cpt_save_did_run'])
		return;

	$post_id = $_POST['post_ID'];

	if (!current_user_can('edit_post', $post_id))
		return;

	if (!empty($_POST['sap-import'])) {	
		update_post_meta($post_id, 'cpt_atts', unserialize(stripslashes($_POST['sap-import'])));
		return;
	}

	$atts = apply_filters('sap_metabox_save', $_POST['cpt_atts'], $post_id);

	if (!empty($atts))
		update_post_meta($post_id, 'cpt_atts', $atts);

	$_POST['_cpt_save_did_run'] = true;
}


add_filter('sap_metabox_save', 'sap_store_multiples', 10, 2);
function sap_store_multiples($atts, $post_id) {
	return $atts;
}


add_filter('sap_metabox_save', 'sap_store_file_uploads', 10, 2);

function sap_store_file_uploads($atts, $post_id) {
	if (empty($_FILES['cpt_atts']))
		return $atts;
	else
		$files = $_FILES['cpt_atts'];
	
	$files_wp = array();

	// Make a new FILES array which will work with the way WP does "media_handle_upload" 
	foreach ($files as $file_attr_name => $group) {
		foreach ($group as $group_id => $multiple) {
			foreach ($multiple as $multiple_id => $file) {
				foreach ($file as $file_id => $val) {
					$files_wp[$file_id . $multiple_id][$file_attr_name] = $val['file'];
					$files_wp[$file_id . $multiple_id]['_group'] = $group_id;
					$files_wp[$file_id . $multiple_id]['_file_id'] = $file_id;
					$files_wp[$file_id . $multiple_id]['_multiple_id'] = $multiple_id;
				}
			}
		}
	}

	// We have to do this, because WordPress uses the $_FILES global
	$_FILES = $files_wp;

	foreach ($files_wp as $file_id => $file) {
		if (empty($file['name']))
			continue;

		$file_meta = $atts[$file['_group']][$file['_multiple_id']][$file['_file_id']];

		// We have the multiple field, upload/update all
		foreach ($file_meta as $i => $item) {
			$prev_id = $file_meta[$i]['file'];
			if (is_numeric($prev_id))
				wp_delete_attachment($prev_id, true);

			$uploaded_id = media_handle_upload($file_id, $post_id, array(), array('action' => 'editpost'));
			if (is_numeric($uploaded_id))
				$atts[$file['_group']][$file['_multiple_id']][$file['_file_id']]['file'] = $uploaded_id;
		}
	}

	return $atts;
}


function get_simple_attribute($id = false) {
	global $post;
	
	$spa_settings = get_option('cpt_atts_' . $post->post_type);
	$cpt_atts = get_post_meta($post->ID, 'cpt_atts', true);
	
	$return = array();

	if ($id) {
		if (!isset($spa_settings['in_group'][$id]))
			return;

		$group_id = $spa_settings['in_group'][$id];

		$return['settings'] = $spa_settings['groups'][$group_id]['atts'][$id];
		if (isset($cpt_atts[$group_id][0]))
			$return['_value'] = $cpt_atts[$group_id][0][$id];
	} else {
		$return['settings'] = $spa_settings;
		$return['atts'] = $cpt_atts;
	}
	
	return $return;
}



/*
	Public API
*/

add_action('spa_value', 'print_simple_attribute_value', 10, 2);

function print_simple_attribute_value($id, $args = array()) {
	$args['echo'] = true;
	$attr = get_simple_attribute($id);
	return apply_filters('get_spa_value-' . $attr['settings']['_type'], $attr, $args);
}

add_filter('get_spa_value', 'get_simple_attribute_value', 10, 2);

function get_simple_attribute_value($id, $args = array()) {
	$attr = get_simple_attribute($id);
	return apply_filters('get_spa_value-' . $attr['settings']['_type'], $attr, $args);
}


add_action('spa_label', 'get_simple_attribute_label');

function get_simple_attribute_label($id, $args = array()) {
	$attr = get_simple_attribute($id);

	if (empty($args) || !isset($args['echo']))
		echo apply_filters('get_spa_label', $attr, $args);
	else
		return apply_filters('get_spa_label', $attr, $args);
}


add_action('spa_list', 'get_spa_list');

function get_spa_list($vars) {
	global $post;

	$spa = get_simple_attribute();

	if (empty($spa['settings']) || empty($spa['atts']))
		return;

	if (!empty($vars['exclude']))
		foreach ($vars['exclude'] as $exclude)
			unset($spa['settings']['in_group'][$exclude]);

	if (!empty($vars['include']))
		foreach ($spa['settings']['in_group'] as $name => $group)
			if (!in_array($name, $vars['include']))
				unset($spa['settings']['in_group'][$name]);

	if (empty($spa['atts']))
		return;

	$list = '<ul class="spa-list-'. $post->post_type .'">';
	foreach ($spa['settings']['in_group'] as $name => $group_id) {
		$type = $spa['settings']['groups'][$group_id]['atts'][$name]['_type'];

		if (!is_array($spa['atts'][$group_id]) || empty($spa['atts'][$group_id]))
			continue;

		foreach ($spa['atts'][$group_id] as $multiple_id => $item) {
			$value = $item[$name];
			if ($value != '') {
				$list .= apply_filters('spa_list-' . $type, $value, $spa['settings']['groups'][$group_id]['atts'], $name, $value);
			}
		}
	}
	$list .= '</ul>';

	echo apply_filters('spa_list_echo', $list, $spa);
}

add_filter('spa_list_item_template', 'spa_list_item_template_default');
function spa_list_item_template_default() {
	return '<li class="spa-list-%1$s"><strong>%2$s</strong> <span class="spa-list-value">%3$s</span></li>';
}

add_filter('spa_list-text', 'default_spa_template_text', 10, 4);
function default_spa_template_text($value, $spa, $i, $a) {
	$template = apply_filters('spa_list_item_template', '');
	return sprintf($template, $i, $spa[$i]['name'], $a);
}

add_filter('spa_list-image', 'default_spa_template_image', 10, 4);
function default_spa_template_image($value, $spa, $i, $a) {
	if (empty($a['file']))
		return;

	$att_id = $a['file'];

	$template = apply_filters('spa_list_item_template', '');
	return sprintf($template, $i, $spa['settings'][$i]['name'], wp_get_attachment_image($att_id));
}


// Format the default output of text input type
add_filter('get_spa_value-text', 'spa_format_value_text', 10, 2);

function spa_format_value_text($attr, $args) {
	if ($args['echo'])
		echo sprintf('<span class="spa-value-%s">%s</span>', $attr['settings']['_id'], $attr['_value']);

	return $attr['_value'];
}


// Format the default output of single images
add_filter('get_spa_value-image', 'spa_format_value_image', 10, 2);

function spa_format_value_image($attr, $args) {
	if (empty($attr['_value']['file']))
		return;

	if (!empty($args['image_size']))
		$img = wp_get_attachment_image($attr['_value']['file'], $args['image_size']);
	else
		$img = wp_get_attachment_image($attr['_value']['file']);

	if ($args['echo'])
		echo sprintf('<span class="spa-value-%s">%s</span>', $attr['settings']['_id'], $img);

	return $attr['_value'];
}


add_filter('get_spa_label', 'spa_format_label', 10, 1);

function spa_format_label($attr) {
	return sprintf('<span class="spa-label-%s">%s</span>', $attr['_id'], $attr['name']);
}





