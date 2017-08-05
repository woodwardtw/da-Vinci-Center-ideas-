<?php
function understrap_remove_scripts() {
    wp_dequeue_style( 'understrap-styles' );
    wp_deregister_style( 'understrap-styles' );

    wp_dequeue_script( 'understrap-scripts' );
    wp_deregister_script( 'understrap-scripts' );

    // Removes the parent themes stylesheet and scripts from inc/enqueue.php
}
add_action( 'wp_enqueue_scripts', 'understrap_remove_scripts', 20 );

add_action( 'wp_enqueue_scripts', 'theme_enqueue_styles' );
function theme_enqueue_styles() {

	// Get the theme data
	$the_theme = wp_get_theme();

    wp_enqueue_style( 'child-understrap-styles', get_stylesheet_directory_uri() . '/css/child-theme.min.css', array(), $the_theme->get( 'Version' ) );
    wp_enqueue_script( 'child-understrap-scripts', get_stylesheet_directory_uri() . '/js/child-theme.min.js', array(), $the_theme->get( 'Version' ), true );
}


//gravity forms restriction of emails
//from https://gravitywiz.com/banlimit-email-domains-for-gravity-form-email-fields/
new GWEmailDomainControl(array(
    'form_id' => 1,
    'field_id' => 5,
    'domains' => array('vcu.edu', 'mymail.vcu.edu', 'mcvh-vcu.edu', 'vcuhealth.org'),
    'mode' => 'limit'
    ));

/**
 * Gravity Wiz // Gravity Forms // Email Domain Validator
 *
 * This snippets allows you to exclude a list of invalid domains or include a list of valid domains for your Gravity Form Email fields.
 *
 * @version   1.4
 * @author    David Smith <david@gravitywiz.com>
 * @license   GPL-2.0+
 * @link      http://gravitywiz.com/banlimit-email-domains-for-gravity-form-email-fields/
 */
class GW_Email_Domain_Validator {

    private $_args;

    function __construct($args) {

        $this->_args = wp_parse_args( $args, array(
            'form_id' => false,
            'field_id' => false,
            'domains' => false,
            'validation_message' => __( 'Sorry, <strong>%s</strong> email accounts are not eligible for this form.' ),
            'mode' => 'ban' // also accepts "limit"
        ) );

        // convert field ID to an array for consistency, it can be passed as an array or a single ID
        if($this->_args['field_id'] && !is_array($this->_args['field_id']))
            $this->_args['field_id'] = array($this->_args['field_id']);

        $form_filter = $this->_args['form_id'] ? "_{$this->_args['form_id']}" : '';

        add_filter("gform_validation{$form_filter}", array($this, 'validate'));

    }

    function validate($validation_result) {

        $form = $validation_result['form'];

        foreach($form['fields'] as &$field) {

            // if this is not an email field, skip
            if(RGFormsModel::get_input_type($field) != 'email')
                continue;

            // if field ID was passed and current field is not in that array, skip
            if($this->_args['field_id'] && !in_array($field['id'], $this->_args['field_id']))
                continue;

            $page_number = GFFormDisplay::get_source_page( $form['id'] );
            if( $page_number > 0 && $field->pageNumber != $page_number ) {
                continue;
            }

            if( GFFormsModel::is_field_hidden( $form, $field, array() ) ) {
                continue;
            }

            $domain = $this->get_email_domain($field);

            // if domain is valid OR if the email field is empty, skip
            if($this->is_domain_valid($domain) || empty($domain))
                continue;

            $validation_result['is_valid'] = false;
            $field['failed_validation'] = true;
            $field['validation_message'] = sprintf($this->_args['validation_message'], $domain);

        }

        $validation_result['form'] = $form;
        return $validation_result;
    }

    function get_email_domain( $field ) {
        $email = explode( '@', rgpost( "input_{$field['id']}" ) );
        return trim( rgar( $email, 1 ) );
    }

    function is_domain_valid( $domain ) {

        $mode   = $this->_args['mode'];
        $domain = strtolower( $domain );

        foreach( $this->_args['domains'] as $_domain ) {

            $_domain = strtolower( $_domain );

            $full_match   = $domain == $_domain;
            $suffix_match = strpos( $_domain, '.' ) === 0 && $this->str_ends_with( $domain, $_domain );
            $has_match    = $full_match || $suffix_match;

            if( $mode == 'ban' && $has_match ) {
                return false;
            } else if( $mode == 'limit' && $has_match ) {
                return true;
            }

        }

        return $mode == 'limit' ? false : true;
    }

    function str_ends_with( $string, $text ) {

        $length      = strlen( $string );
        $text_length = strlen( $text );

        if( $text_length > $length ) {
            return false;
        }

        return substr_compare( $string, $text, $length - $text_length, $text_length ) === 0;
    }

}

class GWEmailDomainControl extends GW_Email_Domain_Validator { }

# Configuration

new GW_Email_Domain_Validator( array(
    'form_id' => 326,
    'field_id' => 1,
    'domains' => array( 'gmail.com', 'hotmail.com', '.co.uk' ),
    'validation_message' => __( 'Oh no! <strong>%s</strong> email accounts are not eligible for this form.' ),
    'mode' => 'limit'
) );