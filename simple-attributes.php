<?php
/*
 Plugin Name: Simple Attributes
 Plugin URI: 
 Description: Add simple attributes to posts and custom post types
 Version: 1.7.2
 Author: Kaspars Dambis
 Author URI: http://konstruktors.com
 Text Domain: simple-attributes
 */

// We shouldn't see notice type of errors
@error_reporting(E_ALL ^ E_NOTICE);


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


add_action('admin_enqueue_scripts', 'spa_scripts_backend');

function spa_scripts_backend() {
	wp_enqueue_style( 'thickbox' );
	wp_enqueue_style( 'simple-attributes-css', plugins_url( '/sap-admin.css', __FILE__) );
	wp_enqueue_script( 'google-maps-api', 'http://maps.google.com/maps/api/js?sensor=true' );
	wp_enqueue_script( 'jquery-ui-map-full', plugins_url( '/scripts/jquery.ui.map.full.min.js', __FILE__ ), array('jquery', 'google-maps-api') );
	wp_enqueue_script( 'simple-attributes-js-backend', plugins_url( '/scripts/spa-admin.js', __FILE__), array( 'jquery', 'jquery-ui-sortable', 'media-upload', 'jquery-ui-map-full' ) );
}


add_action('wp_enqueue_scripts', 'spa_scripts_frontend');

function spa_scripts_frontend() {
	wp_enqueue_script( 'google-maps-api', 'http://maps.google.com/maps/api/js?sensor=true' );
	wp_enqueue_script( 'jquery-ui-map-full', plugins_url( '/scripts/jquery.ui.map.full.min.js', __FILE__ ), array('jquery', 'google-maps-api') );
	wp_enqueue_script( 'simple-attributes-js-frontend', plugins_url( '/scripts/spa-frontend.js', __FILE__), array( 'jquery', 'jquery-ui-map-full' ) );
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

	if (empty($post_type_id))
		$post_type_id = 'post';

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
		'taxonomy' => __('Taxonomies'),
		'location' => __('Location'),
		'post' => __('Posts'),
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

				<?php if ($group == '%group%') : $attrs['_id'] = '%group%'; ?>
					<li class="group groupframe" id="sap-group-%group%">
						<div class="header">
							<label><?php _e('Group Name'); ?>: <input type="text" name="<?php echo $group_prefix; ?>[name]" value="" /></label>
							<label>Group ID: <input type="text" class="sap-group" name="<?php echo $group_prefix; ?>[_id]" value="" /></label>
							<label><input type="checkbox" name="<?php echo $group_prefix; ?>[multiple]" value="1" /> <?php _e('Multiple'); ?></label>
						</div>
						<ul>

				<?php elseif ($prev_group !== $group) : ?>
						
					<li class="group" id="sap-group-<?php echo esc_attr($attrs['_id']); ?>">
						<div class="header">
							<label>Group Name: <input type="text" name="<?php echo esc_attr($group_prefix); ?>[name]" value="<?php echo esc_attr($attrs['name']); ?>" /></label>
							<label>Group ID: <input type="text" class="sap-group" name="<?php echo esc_attr($group_prefix); ?>[_id]" value="<?php echo esc_attr($attrs['_id']); ?>" /></label>
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
							<input type="text" class="sap-attr-name" name="<?php echo $input_prefix; ?>[name]" value="<?php echo esc_attr($atts['name']) ?>" />
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
							<input class="sap-id" type="text" name="<?php echo $input_prefix; ?>[_id]" value="<?php echo esc_attr($atts['_id']) ?>" />
						</label>
						<label class="sap-label-help">
							<?php _e('Help Text:') ?>
							<input class="sap-help" name="<?php echo $input_prefix; ?>[_help]" value="<?php echo esc_attr($atts['_help']) ?>" />
						</label>
					</div>
					
					<div class="sap-adv-type-options sap-type-<?php echo $i; ?> sap-type-<?php echo $i; ?>-dropdown sap-type-<?php echo $i; ?>-checkboxes sap-type-<?php echo $i; ?>-radioboxes">
						<h3><?php _e('Options'); ?></h3>
						<a class="button add-adv-type-options" href="#add-adv-type-options"><?php _e('Add New Option'); ?></a>
						<ul>
							<?php 
								$adv_template = '<li id="adv-type-option-%2$s"><label class="label-adv-label">Label: <input type="text" name="%1$s[freeform][%2$s][name]" value="%3$s" /></label> <label class="label-adv-id">ID: <input class="sap-id" type="text" name="%1$s[freeform][%2$s][_id]" value="%2$s" /></label> <a class="sap-delete-attr" href="#adv-type-option-%2$s">'.  __('Delete') . '</a></li>';
								
								if ( ! empty( $atts['freeform'] ) )
									foreach ( $atts['freeform'] as $adv_id => $adv_val )
										printf( $adv_template, $input_prefix, esc_attr($adv_id), esc_attr($adv_val['name']) );

								// Print the wireframe
								printf(str_replace('<li ', '<li class="frame"', $adv_template), $input_prefix, '%advanced%', '');
							?>
						</ul>
					</div>

					<div class="sap-adv-type-options sap-type-<?php echo $i; ?> sap-type-<?php echo $i; ?>-taxonomy">
						<label>
							<?php _e('Select Taxonomy:') ?>
							<select name="<?php echo $input_prefix; ?>[taxonomy]">
								<option value=""></option>
								<?php 
									$taxonomies = get_taxonomies(array('public' => true), 'objects');
									foreach ($taxonomies as $name => $value) :
										$selected = '';
										if ($name == $atts['taxonomy'])
											$selected = 'selected="selected"';
									?>
									<option value="<?php echo esc_attr($name); ?>" <?php echo $selected; ?>><?php echo esc_attr($value->labels->name); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label>
							<?php 
								$selected = '';
								if ( $atts['taxonomy-multiple'] ) 
									$selected = 'checked="checked"';
							?>
							<input type="checkbox" value="1" name="<?php echo $input_prefix; ?>[taxonomy-multiple]" <?php echo $selected; ?> /> <?php _e('Allow multiple'); ?>
						</label>
					</div>

					<div class="sap-adv-type-options sap-type-<?php echo $i; ?> sap-type-<?php echo $i; ?>-post">
						<label>
							<?php _e('Select Post Type:') ?>
							<select name="<?php echo $input_prefix; ?>[post]">
								<option value=""></option>
								<?php 
									$post_types = get_post_types(array('public' => true), 'objects');
									foreach ($post_types as $name => $value) :
										$selected = '';
										if ($name == $atts['post'])
											$selected = 'selected="selected"';
									?>
									<option value="<?php echo esc_attr($name); ?>" <?php echo $selected; ?>><?php echo esc_attr($value->labels->name); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
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
						<input type="submit" class="button-primary" value="<?php esc_attr_e('Save'); ?>" />
						<a class="remove-group" href="#sap-group-<?php echo esc_attr($attrs['_id']); ?>"><?php _e('Remove Group'); ?></a>
					</p>
				</li>

				<?php endforeach; // End groups ?>
			</ul>

			<p class="submit">
				<a id="add-group" class="button" href="#add-group"><?php _e('Add Group'); ?></a>
				<input type="submit" class="button-primary" value="<?php esc_attr_e('Save'); ?>" />
			</p>

			<div class="advanced">
				<h3>Advanced Tools</h3>
				<h4>Import</h4>
				<textarea name="<?php echo $option_name; ?>[import]"></textarea>
				<h4>Export</h4>
				<textarea><?php unset($cpt_opts['cpt']); echo serialize($cpt_opts); ?></textarea>
			</div>

			<pre class="hidden"><small><?php print_r($cpt_opts); ?></small></pre>
		</form>
	</div>

	<?php	
}


function cpt_atts_admin_validate($input) {
	// IMPORT / EXPORT
	if (!empty($input['import']))
		return unserialize($input['import']);

	// There is only a frame, no groups
	if (empty($input['groups']))
		return $input;

	// Move all _id to array keys, create assoc array
	$input['groups'] = create_attr_assoc($input['groups']);

	if (empty($input['groups']))
		return $input;

	foreach ($input['groups'] as $group_id => $atts)
		if (!empty($atts['atts']))
			foreach ($atts['atts'] as $att)
				$input['in_group'][$att['_id']] = $group_id;

	return $input;
}


function create_attr_assoc($arr) {
	$out = array();

	if ( is_array($arr) ) {
		unset($arr['%group%']);
		unset($arr['%%%']);
		unset($arr['%advanced%']);

		if ( isset( $arr['_type'] ) && in_array( $arr['_type'], array('text', 'file', 'image', 'textarea') ) )
			unset($arr['freeform']);
	} else {
		return $arr;
	}

	if ( is_array( $arr ) && empty( $arr ) )
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

	if (!is_array($attr_values[$group_id]) || empty($attr_values[$group_id]))
		$attr_values[$group_id] = array(array());

	// Add a dummy variable for dynamic Add New field set
	if ($is_multiple)
		$attr_values[$group_id]['%%%'] = array();

	if ($is_multiple && count($attr_values[$group_id]) < 2)
		$attr_values[$group_id][] = array();

	foreach ($attr_values[$group_id] as $multiple_id => $meta) :
		$metabox_id = 'cpt_att-' . $group_id . '-' . $multiple_id;

	$attribute_classes = array();

	if ( $is_multiple )
		$attribute_classes[] = 'sap-sortable';

	if ( ! is_numeric( $multiple_id ) )
		$attribute_classes[] = 'frame';

	?>

	<table id="<?php echo esc_attr($metabox_id); ?>" class="cpt_atts <?php echo implode(' ', $attribute_classes); ?>">
		<thead>
			<tr class="cpt_tools">
				<td colspan="2">
				<?php if ( $is_multiple ) : ?>
					<a href="#<?php echo esc_attr($metabox_id); ?>" class="cpt_atts_delete"><?php _e('Delete'); ?></a>
				<?php endif; ?>
					<span class="dragthis"></span>
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
					if ( ! isset($meta[$i]) || empty($meta[$i]) )
						$meta[$i] = '';

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
		<input type="text" name="<?php echo esc_attr($input_attributes['name']); ?>" value="<?php echo esc_attr($values); ?>" />
	<?php
}


add_action('sap_metabox-textarea', 'cpt_metabox_textarea', 10, 3);

function cpt_metabox_textarea($atts, $values, $input_attributes) {
	?>
		<textarea name="<?php echo esc_attr($input_attributes['name']); ?>"><?php echo esc_html($values); ?></textarea>
	<?php
}


add_action('sap_metabox-image', 'cpt_metabox_image', 10, 3);

function cpt_metabox_image($atts, $meta, $input_attributes) {

	$image_attr = array();
	$meta['file'] = intval( $meta['file'] );

	$image_attr = wp_get_attachment_image_src( $meta['file'] , 'large' );

	?>

	<a href="<?php echo $image_attr[0]; ?>" id="<?php echo esc_attr( $input_attributes['id'] ); ?>-image"><?php echo wp_get_attachment_image( $meta['file'], 'sap_thumb' ) ?></a>

	<input type="hidden" id="<?php echo esc_attr( $input_attributes['id'] ); ?>" name="<?php echo esc_attr( $input_attributes['name'] ); ?>[file]" value="<?php echo esc_attr( $meta['file'] ); ?>" />

	<ul class="sap-file-tools">
		<li class="upload">
			<label>
				<strong><?php _e('Upload Image'); ?></strong>
				<input type="file" name="<?php echo esc_attr( $input_attributes['name'] ); ?>[file]" />
			</label>
			<?php 
				printf(__('or <a href="%s" rel="%s" title="Choose from the existing files" class="sap-choose-existing">choose from existing</a>'), 
					esc_url(get_upload_iframe_src('library') . '&tab=library'), 
					esc_attr($input_attributes['id'])); 
			?>
		</li>
		<li class="file-meta">
			<?php
				if ( ! empty( $meta['file'] ) ) {
					$file_post = get_post( $meta['file'] );

					// Populate the fields with the default values
					if ( empty( $meta['alt'] ) )
						$meta['alt'] = get_post_meta( $meta['file'], '_wp_attachment_image_alt', true );

					if ( empty( $meta['title'] ) )
						$meta['title'] = $file_post->post_title;

					if ( empty( $meta['description'] ) )
						$meta['description'] = $file_post->post_content;
				}
			?>

			<?php if ( $file_post ) : ?>
				<label>
					<strong><?php _e('Title'); ?></strong>
					<input type="text" name="<?php echo esc_attr($input_attributes['name']); ?>[title]" value="<?php echo esc_attr( $meta['title'] ); ?>" />
				</label>
				<label>
					<strong><?php _e('Description'); ?></strong>
					<input type="text" name="<?php echo esc_attr($input_attributes['name']); ?>[description]" value="<?php echo esc_attr( $meta['description'] ); ?>" />
				</label>
				<label>
					<strong><?php _e('Alt Text'); ?></strong>
					<input type="text" name="<?php echo esc_attr($input_attributes['name']); ?>[alt]" value="<?php echo esc_attr( $meta['alt'] ); ?>" />
				</label>
			<?php endif; ?>
		</li>
	</ul>

	<?php
}


add_action('sap_metabox-file', 'cpt_metabox_file', 10, 3);

function cpt_metabox_file($atts, $meta, $input_attributes) {
	?>

	<?php 
		if ( get_attachment_link( $meta['file'] ) ) 
			echo wp_get_attachment_link( $meta['file'] );
	?>

	<input type="hidden" id="<?php echo esc_attr($input_attributes['id']); ?>" name="<?php echo esc_attr($input_attributes['name']); ?>[file]" value="<?php if (is_array($meta)) echo esc_attr($meta['file']); ?>" />

	<ul class="sap-file-tools">
		<li class="upload">
			<label>
				<strong>Upload New File</strong>
				<input type="file" name="<?php echo esc_attr($input_attributes['name']); ?>[file]" />
			</label>
		</li>
		<li class="choose">
			<?php 
				printf(__('or <a href="%s" rel="%s" title="Choose from the existing files" class="sap-choose-existing">choose from existing</a>'), 
					esc_url(get_upload_iframe_src('library') . '&tab=library&is_sap'), 
					esc_attr($input_attributes['id'])); 
			?>
		</li>
		<li class="remove">
			<a id="sap-remove-file" href="#<?php echo esc_attr($input_attributes['id']); ?>"><?php _e('Remove'); ?></a>
		</li>
	</ul>

	<?php
}


add_action('sap_metabox-taxonomy', 'cpt_metabox_taxonomy', 10, 3);

function cpt_metabox_taxonomy($atts, $values, $input_attributes) {
	if ( isset( $atts['taxonomy_multiple'] ) ) :
	?>
		<ul>
			<?php wp_terms_checklist($post_id,
			 	array(
					'taxonomy' => $atts['taxonomy'],
					'selected_cats' => $values,
					'walker' => new Walker_SAP($input_attributes['name'])
		  		));
			?>
		</ul>
	<?php
	else :
		wp_dropdown_categories( array( 
				'hide_empty' => false,
				'name' => $input_attributes['name'],
				'taxonomy' => $atts['taxonomy'],
				'show_option_none' => __('&mdash; Select &mdash;'),
				'selected' => $values
			) );
	endif;
}

class Walker_SAP extends Walker {
	var $tree_type = 'category';
	var $db_fields = array ('parent' => 'parent', 'id' => 'term_id'); //TODO: decouple this
	var $input_name = '';

	function Walker_SAP($input_name) {
		$this->input_name = $input_name;
	}

	function start_lvl( &$output, $depth = 0, $args = array() ) {
		$indent = str_repeat("\t", $depth);
		$output .= "$indent<ul class='children'>\n";
	}

	function end_lvl( &$output, $depth = 0, $args = array() ) {
		$indent = str_repeat("\t", $depth);
		$output .= "$indent</ul>\n";
	}

	function start_el( &$output, $category, $depth, $args, $id = 0 ) {
		extract($args);
		$class = in_array( $category->term_id, $popular_cats ) ? ' class="popular-category"' : '';
		$output .= "\n<li $class>" . '<label class="selectit"><input value="' . $category->term_id . '" type="checkbox" name="'. $this->input_name .'[]" ' . checked( in_array( $category->term_id, $selected_cats ), true, false ) . disabled( empty( $args['disabled'] ), false, false ) . ' /> ' . esc_html( apply_filters('the_category', $category->name )) . '</label>';
	}

	function end_el( &$output, $category, $depth = 0, $args = array() ) {
		$output .= "</li>\n";
	}
}


add_action('sap_metabox-post', 'cpt_metabox_post', 10, 3);

function cpt_metabox_post($atts, $values, $input_attributes) {
	// TODO: Move this into footer, so that it gets called only once
	$post_search_nounce = wp_create_nonce('internal-linking');

	if ( ! is_array($values) )
		$values = array();
	else
		$values = array_filter($values); // Exclude empty values
	?>

	<label>
		<?php esc_attr_e('Search:'); ?>
		<input type="text" value="" class="cpt-search-post" rel="<?php echo esc_attr($input_attributes['id']); ?>" />
		<input type="hidden" name="cpt-post-type" value="<?php echo esc_attr($atts['post']); ?>" />
	</label>

	<input type="hidden" name="cpt-post-search-nonce" value="<?php echo esc_attr($post_search_nounce); ?>" />

	<ul class="sap-posts-list" id="<?php echo esc_attr($input_attributes['id']); ?>">
		<?php $template = '<input type="hidden" name="'. esc_attr($input_attributes['name']) .'[]" value="%s" /> %s <a href="#remove" class="remove">%s</a>'; ?>
		
		<?php foreach ($values as $i => $post_id) : $title = get_the_title($post_id); ?>
			<li><?php printf($template, $post_id, $title, __('Remove')); ?></li>
		<?php endforeach; ?>

		<li class="frame"><?php printf($template, null, null, __('Remove')); ?></li>
	</ul>

	<?php
}


add_action('wp_ajax_sap_get_file_preview', 'sap_get_file_preview');

function sap_get_file_preview() {
	die( wp_get_attachment_image($_POST['file_id'], 'sap_thumb') );
}


add_action('wp_ajax_sap_get_posts', 'sap_get_posts_ajax');

function sap_get_posts_ajax() {
	check_ajax_referer( 'internal-linking', '_ajax_linking_nonce' );

	$query = array(
		'suppress_filters' => true,
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
		'post_status' => 'publish',
		'order' => 'DESC',
		'orderby' => 'post_date',
		'posts_per_page' => 10,
	);

	if ( isset( $_POST['search'] ) )
		$query['s'] = stripslashes( $_POST['search'] );

	if ( isset( $_POST['post_type'] ) )
		$query['post_type'] = stripslashes( $_POST['post_type'] );

	// Do main query.
	$get_posts = new WP_Query;
	$posts = $get_posts->query( $query );

	// Check if any posts were found.
	if ( ! $get_posts->post_count )
		return false;

	// Build results.
	$results = array();
	foreach ( $posts as $post ) {
		$results[] = array(
			'ID' => $post->ID,
			'title' => trim( esc_html( strip_tags( get_the_title( $post ) ) ) )
		);
	}

	if ( ! isset( $results ) )
		die( '0' );

	die( json_encode( $results ) );
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
			<input type="radio" name="<?php echo esc_attr($input_attributes['name']); ?>" value="<?php echo $name; ?>" <?php echo $selected; ?> /> 
			<?php echo $value['name']; ?>
		</label>
	<?php 
	endforeach;
}


add_action('sap_metabox-dropdown', 'cpt_metabox_dropdown', 10, 3);

function cpt_metabox_dropdown($atts, $meta, $input_attributes) {
	$input_id = $atts['_id'];
	?>

	<select name="<?php echo esc_attr($input_attributes['name']); ?>">
		<option value=""><?php _e('Options'); ?></option>
		<?php 
			foreach ($atts['freeform'] as $name => $value) :
				$selected = '';
				if ($name == $meta)
					$selected = 'selected="selected"';

		?>
			<option value="<?php echo esc_attr($name); ?>" <?php echo $selected; ?>><?php echo esc_attr($value['name']); ?></option>
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
			<input type="checkbox" name="<?php echo esc_attr($input_attributes['name']); ?>[]" value="<?php echo $name; ?>" <?php echo $selected; ?> /> 
			<?php echo $value['name']; ?>
		</label>
	<?php 
	endforeach;
}


add_action('sap_metabox-location', 'cpt_metabox_location', 10, 3);

function cpt_metabox_location($atts, $values, $input_attributes) {
	?>
		<!--<p class="location-search">
			<label><?php _e('Search:'); ?> <input type="text" class="location-search-input" value="" /></label>
		</p>
		<div class="location-map <?php echo esc_attr($input_attributes['id']); ?>"></div>-->
		<p class="latlong">
			<label>
				<?php _e('Latitude:'); ?> 
				<input type="text" class="lat" name="<?php echo esc_attr($input_attributes['name']); ?>[lat]" value="<?php echo esc_attr($values['lat']); ?>" />
			</label>
			<label>
				<?php _e('Longitude:'); ?> 
				<input type="text" class="lng" name="<?php echo esc_attr($input_attributes['name']); ?>[lng]" value="<?php echo esc_attr($values['lng']); ?>" />
			</label>
		</p>
	<?php
}


add_action('save_post', 'cpt_atts_save_meta_box');

function cpt_atts_save_meta_box() {
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) 
		return;

	if ( $_POST['_cpt_save_did_run'] )
		return;

	$post_id = $_POST['post_ID'];

	if ( ! current_user_can( 'edit_post', $post_id ) )
		return;

	if ( ! empty( $_POST['sap-import'] ) ) {	
		update_post_meta( $post_id, 'cpt_atts', unserialize( stripslashes( $_POST['sap-import'] ) ) );
		return;
	}

	$atts = apply_filters( 'sap_metabox_save', $_POST['cpt_atts'], $post_id );

	if ( ! empty( $atts ) )
		update_post_meta( $post_id, 'cpt_atts', $atts );

	$_POST['_cpt_save_did_run'] = true;
}


add_filter( 'sap_metabox_save', 'sap_filter_postbox_submit', 10, 2 );

function sap_filter_postbox_submit( $atts, $post_id ) {
	if ( empty( $atts ) )
		return $atts;

	$settings = get_simple_attribute();

	foreach ( $atts as $group_id => $group_fields ) {
		foreach ( $group_fields as $f => $field ) {
			foreach ( $field as $field_id => $field_value ) {
				// Get the field settings
				$field_settings = $settings['groups'][ $group_id ]['atts'][ $field_id ];

				// Allow to filter submited field data
				$field_value = apply_filters( 'sap_postbox_submit_field_type-' . $field_settings['_type'], $field_value );
				$field_value = apply_filters( 'sap_postbox_submit_field_id-' . $field_settings['_id'], $field_value );
				
				$atts[ $group_id ][ $f ][ $field_id ] = $field_value;
			}
		}
	}

	return $atts;
}


/*
add_filter( 'sap_postbox_submit_field_type-image', 'sap_default_filter_field_type_image' );

function sap_default_filter_field_type_image( $values ) {
	if ( ! isset( $values['file'] ) || empty( $values['file'] ) )
		return $values;

	wp_update_post( array( 
			'ID' => $values['file'], 
			'post_title' => $values['title'],
			'post_content' => $values['description'],
		) 
	);

	update_post_meta( $values['file'], '_wp_attachment_image_alt', $values['alt'] );

	return $values;
}
*/


add_filter( 'sap_metabox_save', 'sap_filter_postbox_dummies', 8, 2 );

function sap_filter_postbox_dummies($atts, $post_id) {
	return sap_remove_by_keys( $atts, array('%%%') );
}


function sap_remove_by_keys(&$array, $keys) {
	if ( is_array( $keys ) )
		foreach ( $keys as $k => $key )
			unset( $array[$key] );
	else
		unset( $array[$keys] );

	if ( empty( $array ) )
		return $array;

	foreach ( $array as &$value )
		if ( is_array($value) )
			sap_remove_by_keys( $value, $keys );

	return $array;
}


add_filter('sap_metabox_save', 'sap_store_file_uploads', 10, 2);

function sap_store_file_uploads($atts, $post_id) {
	if ( empty( $_FILES['cpt_atts'] ) )
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

	foreach ( $files_wp as $file_id => $file ) {
		if ( empty( $file['name'] ) )
			continue;

		$file_meta = $atts[$file['_group']][$file['_multiple_id']][$file['_file_id']];

		// We have the multiple field, upload/update all
		foreach ( $file_meta as $i => $item ) {
			$prev_id = $file_meta[$i]['file'];

			if ( is_numeric( $prev_id ) )
				wp_delete_attachment( $prev_id, true );

			$uploaded_id = media_handle_upload( $file_id, $post_id, array(), array('action' => 'editpost') );

			if ( is_numeric( $uploaded_id ) )
				$atts[$file['_group']][$file['_multiple_id']][$file['_file_id']]['file'] = $uploaded_id;
		}
	}

	return $atts;
}


function get_simple_attribute($id = false, $post_data = array()) {
	global $post;

	if ( !empty($post_data) ) {
		$post = new stdClass;
		if (isset($post_data['ID']) && !isset($post_data['post_type'])) {
			$post = get_post($post_data['ID']);
		} elseif (isset($post_data['ID']) && isset($post_data['post_type'])) {
			$post->ID = $post_data['ID'];
			$post->post_type = $post_data['post_type'];
		}
	} else if ( is_singular() ) {
		$post_data = $post;
	}
	
	$spa_settings = get_option('cpt_atts_' . $post->post_type);

	if ( ! isset($post->ID) )
		return $spa_settings;

	$cpt_atts = get_post_meta($post->ID, 'cpt_atts', true);
	
	$return = array();

	if ( $id ) {
		if ( isset( $spa_settings['groups'][$id] ) ) {
			// $id is a group ID
			$group_id = $id;
			$return = $spa_settings['groups'][$group_id]['atts'];
		} else {
			// $id is a item ID
			$group_id = $spa_settings['in_group'][$id];
			$return = $spa_settings['groups'][$group_id]['atts'][$id];
		}

		if ( isset( $cpt_atts[$group_id] ) && count( $cpt_atts[$group_id] ) > 1 )
			$return['_value'] = array_values($cpt_atts[$group_id]);
		else if ( isset( $cpt_atts[$group_id][0][$id] ) )
			$return['_value'] = $cpt_atts[$group_id][0][$id]; // TODO

		$return['_value_raw'] = $return['_value'];
	} else {
		$return = $spa_settings;
		$return['_value'] = $cpt_atts;
	}

	return $return;
}


function spa_replace_template( $value, $args = array() ) {
	if ( empty( $value ) )
		return;

	$search_replace = array(
			'%id%' => $value['_id'],
			'%label%' => $value['name'],
			'%value%' => $value['_value'],		
			'%'. $value['_id'] .'_id%' => $value['_id'],
			'%'. $value['_id'] .'_label%' => $value['name'],
			'%'. $value['_id'] .'_value%' => $value['_value'],
		);

	$template = '';

	if ( isset( $args['template_' . $value['_id']] ) )
		$template = $args['template_' . $value['_id']];	
	elseif ( isset( $args['template_default'] ) )
		$template = $args['template_default'];

	if ( $template )
		return str_replace( array_keys( $search_replace ), array_values( $search_replace ), $template );

	return $value;
}


// Format the default output of text input type
add_filter( 'get_spa_value-text', 'spa_format_value_text', 10, 2 );

function spa_format_value_text( $value, $args ) {
	if ( empty( $value['_value'] ) )
		return;

	// Convert special chars into HTML entities
	$value['_value'] = wptexturize( $value['_value'] );
	$args['template_default'] = '<span class="%id% spa-text">%value%</span>';

	return spa_replace_template( $value, $args );
}


// Format the textarea output
add_filter('get_spa_value-textarea', 'spa_format_value_textarea', 10, 2);

function spa_format_value_textarea($value, $args) {
	if ( empty( $value['_value'] ) )
		return;

	$value['_value'] = apply_filters( 'the_content', $value['_value'] );
	$args['template_default'] = '<div class="%id% spa-textarea">%value%</div>';

	return spa_replace_template( $value, $args );
}

// Format the textarea output
add_filter('get_spa_value-location', 'spa_format_value_location', 10, 2);

function spa_format_value_location($value, $args) {
	if ( empty( $value['_value'] ) )
		return;

	$geo_template = '<p class="geo"><abbr class="latitude" title="%1$f">%1$f</abbr> <abbr class="longitude" title="%2$f">%2$f</abbr></p>';
	
	$value['_value'] = sprintf( $geo_template, $value['_value']['lat'], $value['_value']['lng'] );
	$args['template_default'] = '<div class="sap-location %id%"><div class="sap-location-map map"></div> %value%</div>';

	return spa_replace_template( $value, $args );
}


// Format the default output of single images
add_filter('get_spa_value-image', 'spa_format_value_image', 10, 3);

function spa_format_value_image($value, $args ) {
	if ( empty( $value['_value'] ) )
		return;

	if ( ! empty($args['image_size']) )
		$value['_value'] = wp_get_attachment_link($value['_value']['file'], $args['image_size']);
	else
		$value['_value'] = wp_get_attachment_link($value['_value']['file']);

	$args['template_default'] = '<span class="%id% spa-image">%value%</span>';

	return spa_replace_template( $value, $args );
}


// Format the textarea output
add_filter('get_spa_value-checkboxes', 'spa_format_value_checkboxes', 10, 2);

function spa_format_value_checkboxes($value, $args) {
	if ( empty( $value['_value'] ) )
		return;

	$checked_items = '';
	$args['template_default'] = '<li class="spa-checkbox-item">%value%</li>';
	
	foreach ( $value['_value'] as $c => $checkbox_id ) {
		$checkbox_value = array(
				'_id' => $value['_id'],
				'_value' => $value['freeform'][$checkbox_id]['name'],
				'_label' => $value['freeform'][$checkbox_id]['name']
			);

		$checked_items .= spa_replace_template( $checkbox_value, $args );
	}

	$value['_value'] = $checked_items;
	$args['template_default'] = '<ul class="%id% spa-checkboxes">%value%</ul>';

	return spa_replace_template( $value, $args );
}


// Format the textarea output
add_filter('get_spa_value-dropdown', 'spa_format_value_dropdown', 10, 2);

function spa_format_value_dropdown($value, $args) {
	if ( empty( $value['_value'] ) )
		return;

	if ( ! isset( $value['freeform'][ $value['_value'] ]['name'] ) )
		return;

	$value['_value'] = $value['freeform'][ $value['_value'] ]['name'];

	$args['template_default'] = '<li class="spa-dropdown">%value%</li>';

	return spa_replace_template( $value, $args );
}

// Format the textarea output
add_filter('get_spa_value-taxonomy', 'spa_format_value_taxonomy', 10, 2);

function spa_format_value_taxonomy($value, $args) {
	if ( empty( $value['_value'] ) )
		return;

	$term = get_term_by( 'id', $value['_value'], $value['taxonomy'] );
	$value['_value'] = $term->name;

	if ( $value['taxonomy-multiple'] )
		$args['template_default'] = '<li class="spa-checkbox-item">%value%</li>';
	else
		$args['template_default'] = '<span class="spa-checkbox-item">%value%</span>';

	return spa_replace_template( $value, $args );
}



/*
	Public API
*/


// Prints the attribute value
add_action('spa_value', 'print_simple_attribute_value', 10, 2);

function print_simple_attribute_value($id, $args = array()) {
	$values = get_simple_attribute($id);
	
	echo apply_filters( 'get_spa_value-' . $values['_type'], $values, $args );
}


// Returns the attribute value
add_filter('get_spa_value', 'get_simple_attribute_value', 10, 2);

function get_simple_attribute_value($id, $args = array()) {
	if ( is_array( $id ) )
		$value = $id;
	else
		$value = get_simple_attribute($id, $args);

	return apply_filters( 'get_spa_value-' . $value['_type'], $value, $args );
}



/**
 * Depricated
 */


// Depricated, use spa_value with $args['template'] instead
add_action('spa_label', 'get_simple_attribute_label');

function get_simple_attribute_label($id, $args = array()) {
	$attr = get_simple_attribute($id, $args);

	$args['template_default'] = '%label%';
	
	echo apply_filters('get_spa_label', $attr, $args);
}


// Depricated, use get_spa_value with $args['template'] instead
add_filter('get_spa_label', 'spa_format_label', 10, 1);

function spa_format_label($attr) {
	return sprintf('<span class="spa-label-%s">%s</span>', $attr['_id'], $attr['name']);
}


/*

// Can't use this reliably. Use apply_filters('get_spa_attribute') instead.
add_action('spa_list', 'get_spa_list');

function get_spa_list($vars) {
	global $post;

	$spa = get_simple_attribute();

	if ( empty( $spa['_value'] ) )
		return;

	if ( isset( $vars['exclude'] ) && ! empty( $vars['exclude'] ) )
		foreach ( $vars['exclude'] as $exclude )
			if ( isset( $spa['in_group'][$exclude] ) )
				unset( $spa['in_group'][$exclude] );

	if ( isset( $vars['include'] ) && ! empty( $vars['include'] ) ) {
		if ( is_string( $vars['include'] ) )
			$vars['include'] = array($vars['include']);

		foreach ( $spa['in_group'] as $item_id => $group_id ) {
			if ( ! isset( $spa['_value'][$group_id] ) && ! in_array( $item_id, $vars['include'] ) ) {
				unset( $spa['_value'][$item_id] ); // If include is a group
			}

			if ( isset( $spa['_value'][$group_id] ) && ! in_array( $group_id, $vars['include'] ) ) {
				unset( $spa['in_group'][$item_id] ); // If include is an entry ID
			}
		}
	}

	if ( empty($spa['_value']) || empty( $spa['in_group'] ) )
		return;

	$return = '';

	foreach ( $spa['groups'] as $group_id => $group_fields ) {
		if ( ! in_array( $group_id, $spa['in_group'] ) )
			continue;

		$group_html = '';
		$is_repeatable = false;

		if ( isset( $group_fields['multiple'] ) )
			$is_repeatable = true;

		if ( ! isset( $spa['_value'][$group_id] ) || empty( $spa['_value'][$group_id] ) )
			continue;

		foreach ( $spa['_value'][$group_id] as $r => $rep_fields ) {
			$repeatable_vars = $vars;
			$repeatable_html = '';

			foreach ( $rep_fields as $rep_field_id => $rep_field_value ) {
				$field_settings = $group_fields['atts'][$rep_field_id];
				$field_settings['_value'] = $rep_field_value;

				$item_html = apply_filters( 'get_spa_value', $field_settings, $repeatable_vars );
				
				if ( ! is_string( $item_html ) )
					continue;

				if ( $is_repeatable ) {
					$repeatable_html = $item_html;
					$repeatable_vars['template'] = $repeatable_html;
				} else {
					$repeatable_html .= $item_html;
				}
			}

			$group_html .= $repeatable_html;
		}

		if ( isset( $vars['template_' . $group_id] ) ) {
			$group_settings = array(
								'_id' => $group_id,
								'_value' => $group_html 
							);

			$group_html = spa_replace_template( $group_settings , $vars );
		}

		$return .= $group_html;
	}

	echo $return;
}
*/




