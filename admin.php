<?php

add_action( 'admin_enqueue_scripts', 'wpcf7cf_admin_enqueue_scripts', 11 ); // set priority so scripts and styles get loaded later.

function wpcf7cf_admin_enqueue_scripts( $hook_suffix ) {

	wp_enqueue_script('cf7cf-scripts-admin-all-pages', wpcf7cf_plugin_url( 'js/scripts_admin_all_pages.js' ),array( 'jquery' ), WPCF7CF_VERSION,true);


	if ( false === strpos( $hook_suffix, 'wpcf7' ) ) {
		return; //don't load styles and scripts if this isn't a CF7 page.
	}

	wp_enqueue_script('cf7cf-scripts-admin', wpcf7cf_plugin_url( 'js/scripts_admin.js' ),array('jquery-ui-autocomplete', 'jquery-ui-sortable'), WPCF7CF_VERSION,true);
	wp_localize_script('cf7cf-scripts-admin', 'wpcf7cf_options_0', wpcf7cf_get_settings());

}

add_filter('wpcf7_editor_panels', 'add_conditional_panel');

function add_conditional_panel($panels) {
	if ( current_user_can( 'wpcf7_edit_contact_form' ) ) {
		$panels['wpcf7cf-conditional-panel'] = array(
			'title'    => __( 'Conditional fields', 'cf7-conditional-fields' ),
			'callback' => 'wpcf7cf_editor_panel_conditional'
		);
	}
	return $panels;
}

function wpcf7cf_all_field_options($post, $selected = '-1') {
	$all_fields = $post->scan_form_tags();
	?>
	<option value="-1" <?php echo $selected == '-1'?'selected':'' ?>><?php _e( '-- Select field --', 'cf7-conditional-fields' ); ?></option>
	<?php
	foreach ($all_fields as $tag) {
		if ($tag['type'] == 'group' || $tag['name'] == '') continue;
		?>
		<option value="<?php echo $tag['name']; ?>" <?php echo $selected == $tag['name']?'selected':'' ?>><?php echo $tag['name']; ?></option>
		<?php
	}
}

function wpcf7cf_all_group_options($post, $selected = '-1') {
	$all_groups = $post->scan_form_tags(array('type'=>'group'));

	?>
	<option value="-1" <?php echo $selected == '-1'?'selected':'' ?>><?php _e( '-- Select group --', 'cf7-conditional-fields' ); ?></option>
	<?php
	foreach ($all_groups as $tag) {
		?>
		<option value="<?php echo $tag['name']; ?>" <?php echo $selected == $tag['name']?'selected':'' ?>><?php echo $tag['name']; ?></option>
		<?php
	}
}

if (!function_exists('all_operator_options')) {
	function all_operator_options($selected = 'equals') {
		$all_options = array('equals', 'not equals');
		$all_options = apply_filters('wpcf7cf_get_operators', $all_options);
		foreach($all_options as $option) {
			// backwards compat
			$selected = $selected == '≤' ? 'less than or equals' : $selected;
			$selected = $selected == '≥' ? 'greater than or equals' : $selected;
			$selected = $selected == '>' ? 'greater than' : $selected;
			$selected = $selected == '<' ? 'less than' : $selected;

			?>
			<option value="<?php echo htmlentities($option) ?>" <?php echo $selected == $option?'selected':'' ?>><?php echo htmlentities($option) ?></option>
			<?php
		}
	}
}

function wpcf7cf_editor_panel_conditional($form) {

	$settings = wpcf7cf_get_settings();
	$is_text_only = $settings['conditions_ui'] === 'text_only';

	// print_r($settings);

	$form_id = isset($_GET['post']) ? $_GET['post'] : false;

	if ($form_id === false) {
		?>
		    <div class="wpcf7cf-inner-container">
				<h2><?php _e( 'Conditional fields', 'cf7-conditional-fields' ); ?></h2>
				<p><?php _e( 'You need to save your form, before you can start adding conditions.', 'cf7-conditional-fields' ); ?></p>
			</div>
		<?php
		return;
	}

	$wpcf7cf_entries = CF7CF::getConditions($form_id);


	$wpcf7cf_entries = array_values($wpcf7cf_entries);

	?>
    <div class="wpcf7cf-inner-container">

		<label class="wpcf7cf-switch" id="wpcf7cf-text-only-switch">
			<span class="label"><?php _e( 'Text mode', 'cf7-conditional-fields' ); ?></span>
			<span class="switch">
				<input type="checkbox" id="wpcf7cf-text-only-checkbox" name="wpcf7cf-text-only-checkbox" value="text_only" <?php echo $is_text_only ? 'checked':''; ?>>
				<span class="slider round"></span>
			</span>
		</label>

		<h2><?php _e( 'Conditional fields', 'cf7-conditional-fields' ); ?></h2>

		<div id="wpcf7cf-entries-ui" style="display:none">
			<?php
			print_entries_html($form);
			?>
			<div id="wpcf7cf-entries">
				<?php
				//print_entries_html($form, $wpcf7cf_entries);
				?>
			</div>
			
			<span id="wpcf7cf-add-button" title="<?php _e( 'add new rule', 'cf7-conditional-fields' ); ?>"><?php _e( '+ add new conditional rule', 'cf7-conditional-fields'); ?></span>

			<div id="wpcf7cf-a-lot-of-conditions" class="wpcf7cf-notice notice-warning" style="display:none;">
				<p>
					<strong><?php _e( 'Wow, That\'s a lot of conditions!', 'cf7-conditional-fields' ); ?></strong><br>
					<?php 
					// translators: 1. max recommended conditions
					echo sprintf( __( 'You can only add up to %d conditions using this interface.', 'cf7-conditional-fields' ), WPCF7CF_MAX_RECOMMENDED_CONDITIONS ) . ' ';
					// translators: 1,2: strong tags, 3. max recommended conditions
					printf( __( 'Please switch to %1$sText mode%2$s if you want to add more than %3$d conditions.', 'cf7-conditional-fields' ), '<strong>', '</strong>', WPCF7CF_MAX_RECOMMENDED_CONDITIONS ); ?>
				</p>
			</div>

		</div>

        <div id="wpcf7cf-text-entries">
            <div id="wpcf7cf-settings-text-wrap">

                <textarea id="wpcf7cf-settings-text" name="wpcf7cf-settings-text"><?php echo CF7CF::serializeConditions($wpcf7cf_entries) ?></textarea>
                <br>
            </div>
        </div>
    </div>
<?php
}

// duplicate conditions on duplicate form part 1.
add_filter('wpcf7_copy','wpcf7cf_copy', 10, 2);
function wpcf7cf_copy($new_form,$current_form) {

	$id = $current_form->id();
	$props = $new_form->get_properties();
	$props['messages']['wpcf7cf_copied'] = $id;
	$new_form->set_properties($props);

	return $new_form;
}

// duplicate conditions on duplicate form part 2.
add_action('wpcf7_after_save','wpcf7cf_after_save',10,1);
function wpcf7cf_after_save($contact_form) {
	$props = $contact_form->get_properties();
	$original_id = isset($props['messages']['wpcf7cf_copied']) ? $props['messages']['wpcf7cf_copied'] : 0;
	if ($original_id !== 0) {
		$post_id = $contact_form->id();
		unset($props['messages']['wpcf7cf_copied']);
		$contact_form->set_properties($props);
		CF7CF::setConditions($post_id, CF7CF::getConditions($original_id));
		return;
	}
}

// wpcf7_save_contact_form callback
add_action( 'wpcf7_save_contact_form', 'wpcf7cf_save_contact_form', 10, 1 );
function wpcf7cf_save_contact_form( $contact_form )
{

	if (  ! isset( $_POST['wpcf7cf-settings-text'] ) ) {
		return;
	}
	$post_id = $contact_form->id();
	if ( ! $post_id ) {
		return;
	}


	// we intentionally don't use sanitize_textarea_field here,
	// because basically any character is a valid character.
	// To arm agains SQL injections and other funky junky, the CF7CF::parse_conditions function is used.
	$conditions_string = stripslashes($_POST['wpcf7cf-settings-text']);
	$conditions = CF7CF::parse_conditions($conditions_string);

	CF7CF::setConditions($post_id, $conditions);

	if (isset($_POST['wpcf7cf-summary-template'])) {
		WPCF7CF_Summary::saveSummaryTemplate($_POST['wpcf7cf-summary-template'],$post_id);
	}

    return;

};

function wpcf7cf_sanitize_options($options) {
    //$options = array_values($options);
    $sanitized_options = [];
    foreach ($options as $option_entry) {
	    $sanitized_option = [];
	    $sanitized_option['then_field'] = sanitize_text_field($option_entry['then_field']);
	    foreach ($option_entry['and_rules'] as $and_rule) {
		    $sanitized_option['and_rules'][] = [
		            'if_field' => sanitize_text_field($and_rule['if_field']),
		            'operator' => $and_rule['operator'],
		            'if_value' => sanitize_text_field($and_rule['if_value']),
            ];
        }

	    $sanitized_options[] = $sanitized_option;
    }
    return $sanitized_options;
}

function print_entries_html($form, $wpcf7cf_entries = false) {

    $is_dummy = !$wpcf7cf_entries;

    if ($is_dummy) {
	    $wpcf7cf_entries = array(
		    '{id}' => array(
			    'then_field' => '-1',
			    'and_rules' => array(
				    0 => array(
					    'if_field' => '-1',
					    'operator' => 'equals',
					    'if_value' => ''
				    )
			    )
		    )
	    );
	}
	
	foreach($wpcf7cf_entries as $i => $entry) {

		// check for backwards compatibility ( < 2.0 )
		if (!key_exists('and_rules', $wpcf7cf_entries[$i]) || !is_array($wpcf7cf_entries[$i]['and_rules'])) {
			$wpcf7cf_entries[$i]['and_rules'][0] = $wpcf7cf_entries[$i];
		}

		$and_entries = array_values($wpcf7cf_entries[$i]['and_rules']);

		if ($is_dummy) {
			echo '<div id="wpcf7cf-new-entry">';
        } else {
        	echo '<div class="entry">';
        }
		?>
            <div class="wpcf7cf-if">
                <span class="label"><?php _e( 'Show', 'cf7-conditional-fields' ); ?></span>
                <select class="then-field-select"><?php wpcf7cf_all_group_options($form, $entry['then_field']); ?></select>
            </div>
            <div class="wpcf7cf-and-rules" data-next-index="<?php echo count($and_entries) ?>">
				<?php



				foreach($and_entries as $and_i => $and_entry) {
					?>
                    <div class="wpcf7cf-and-rule">
                        <span class="rule-part if-txt label"><?php _e( 'if', 'cf7-conditional-fields' ); ?></span>
                        <select class="rule-part if-field-select"><?php wpcf7cf_all_field_options( $form, $and_entry['if_field'] ); ?></select>
                        <select class="rule-part operator"><?php all_operator_options( $and_entry['operator'] ) ?></select>
                        <input class="rule-part if-value" type="text" placeholder="<?php _e( 'value', 'cf7-conditional-fields' ); ?>" value="<?php echo $and_entry['if_value'] ?>">
                        <span class="and-button"><?php _e( 'And', 'cf7-conditional-fields' ); ?></span>
                        <span title="<?php _e( 'delete rule', 'cf7-conditional-fields' ); ?>" class="rule-part delete-button"><?php _e( 'remove', 'cf7-conditional-fields' ); ?></span>
                    </div>
					<?php
				}
				?>
            </div>
		<?php
		echo '</div>';
	}
}

add_action('admin_notices', function () {

	$settings = wpcf7cf_get_settings();

	$nid = 'install-cf7';

	if (!defined('WPCF7_VERSION') && empty($settings['notice_dismissed_'.$nid])) {
		?>
			<div class="wpcf7cf-admin-notice notice notice-warning is-dismissible" data-notice-id="<?php echo $nid ?>">
				<p>
					<strong>Conditional Fields for Contact Form 7</strong> depends on Contact Form 7. Please install <a target="_blank" href="https://downloads.wordpress.org/plugin/contact-form-7.<?php echo WPCF7CF_CF7_MAX_VERSION ?>.zip">Contact Form 7</a>.
				</p>
			</div>
		<?php
		return;
	}

	$nid = 'rollback-cf7-'.WPCF7CF_CF7_MAX_VERSION;
	if ( version_compare( WPCF7CF_CF7_MAX_VERSION, WPCF7_VERSION, '<' ) && empty($settings['notice_dismissed_'.$nid]) ) {
		?>
			<div class="wpcf7cf-admin-notice notice notice-warning is-dismissible" data-notice-id="<?php echo $nid ?>">
				<p>
					<strong>Conditional Fields for Contact Form 7</strong> is not yet compatible with your current version of Contact Form 7.
					<br>If you notice any problems with your forms, please roll back to Contact Form 7 <strong>version <?php echo WPCF7CF_CF7_MAX_VERSION ?></strong>.
					<br>For a quick and safe rollback, we recommend <a href="https://wordpress.org/plugins/wp-rollback/" target="_blank">WP Rollback</a>.
				</p>
			</div>
		<?php
	}

	$nid = 'update-cf7-'.WPCF7CF_CF7_MAX_VERSION;
	if ( version_compare( WPCF7CF_CF7_MAX_VERSION, WPCF7_VERSION, '>' ) && empty($settings['notice_dismissed_'.$nid]) ) {
		?>
			<div class="wpcf7cf-admin-notice notice notice-warning is-dismissible" data-notice-id="<?php echo $nid ?>">
				<p>
					<strong>Conditional Fields for Contact Form 7</strong> is fully compatible and tested with Contact Form 7 version <?php echo WPCF7CF_CF7_MAX_VERSION ?>.
					<br>Compatibility with other versions of CF7 is not guaranteed, so please install <a target="_blank" href="https://downloads.wordpress.org/plugin/contact-form-7.<?php echo WPCF7CF_CF7_MAX_VERSION ?>.zip">CF7 version <?php echo WPCF7CF_CF7_MAX_VERSION ?></a>
				</p>
			</div>
		<?php
	}

});
