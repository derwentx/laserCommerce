<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LaserCommerce_Admin extends WC_Settings_Page{
    private $_class = "LASERCOMMERCE_ADMIN_";

    /**
     * Constructs the settings page class, hooking into woocommerce settings api
     * 
     * @param $optionNamePrefix 
     */
    public function __construct($optionNamePrefix = 'lc_') {
        $this->id            = 'lasercommerce';
        $this->label         = __('LaserCommerce', 'lasercommerce');
        $this->optionNamePrefix = $optionNamePrefix;
        
        add_filter( 'woocommerce_settings_tabs_array', array($this, 'add_settings_page' ), 20 );
        // add_action( 'admin_enqueue_scripts', array($this, 'nestable_init'));
        add_action( 'woocommerce_settings_' . $this->id, array( $this, 'nestable_init' ) ); //TODO: check priority is right
        add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output_sections' ) );
        add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output' ) );
        // add_action( 'woocommerce_admin_field_tier_tree', array( $this, 'tier_tree_setting' ) );
        add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );
        add_action( 'woocommerce_update_option_tier_tree', array( $this, 'tier_tree_save' ) );
        
    }   

    /**
     * Initializes the nestable jquery functions responsible for the drag and drop 
     * functionality in the tier tree interface
     */
    public function nestable_init(){
        $_procedure = "LASERCOMMERCE_ADMIN: ";
        $script_loc = plugins_url('js/jquery.nestable.js', dirname(__FILE__));
        $css_loc = plugins_url('css/nestable.css', dirname(__FILE__));
        if(LASERCOMMERCE_DEBUG){
            error_log($_procedure."script_loc: ".serialize($script_loc));
            error_log($_procedure."css_loc: ".serialize($css_loc));
        }
        wp_register_script( 'jquery-nestable-js', $script_loc, array('jquery'));
        wp_register_style( 'nestable-css', $css_loc);

        wp_enqueue_script( 'jquery-nestable-js' );
        wp_enqueue_style( 'nestable-css' );
    } 
    
    /**
     * Overrides the get_sections() method of the WC_Settings Api
     * used by the api to generate the sections of the pages
     */
    public function get_sections() {
        $sections = array(
            '' => __('Advanced Pricing and Visibility', LASERCOMMERCE_DOMAIN),
        );
        
        return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
    }
    
    /**
     * Returns the appropriate settings array for the given section
     *
     * @param string $current_section 
     */
    public function get_settings( $current_section = "" ) {
        if( !$current_section ) { //Advanced Pricing and Visibility
            return apply_filters( 
                'woocommerce_lasercommerce_pricing_visibility_settings', 
                array(
                    array( 
                        'title' => __( 'LaserCommerce Advanced Pricing and Visibility Options', LASERCOMMERCE_DOMAIN ),
                        'id'    => 'options',
                        'type'  => 'title',
                    ),
                    array(
                        'name'  => 'Tier Key',
                        'description' => __('They usermeta key that determines a users tier', LASERCOMMERCE_DOMAIN),
                        'type'  => 'text',
                        'id'    => 'tier_key'
                    ),
                    array(
                        'name'  => 'Tier Tree',
                        'type'  => 'tier_tree',
                        'description'   => __('Drag classes to here from "Available User Roles"', LASERCOMMERCE_DOMAIN),
                        'id'    => 'tier_tree',
                        // 'default' => '[{"id":"special_customer","children":[{"id":"wholesale_buyer","children":[{"id":"distributor","children":[{"id":"international_distributor"}]},{"id":"mobile_operator"},{"id":"gym_owner"},{"id":"salon"},{"id":"home_studio"}]}]}]'
                    ),
                    array(
                        'type' => 'sectionend',
                        'id' => 'options'
                    )
                )
            );
        }
        //TODO: sanitize price tiers
        //TODO: enter price tiers in table
    }    
    
    /**
     * Used bt the WC_Settings_Api to output the fields in the settings array
     */
    public function output() {
        global $current_section;
        
        if( !$current_section){ //Advanced Pricing and Visibility
            $settings = $this->get_settings();
        
            WC_Admin_Settings::output_fields($settings );
        }
    }

    /**
     * Used by tier_tree_setting to recursively output the html for nestable fields 
     * this is the drag and drop field used in the configuration of the price tier
     * 
     * @param array $node The node of the tree to be displayed
     * @param array $names The array containing the mapping of roles to tier names
     */
    public function output_nestable_li($node, $names) { 
        if(isset($node['id'])) {
            ?>
                <li class="dd-item" data-id="<?php echo $node['id']; ?>">
                    <div class="dd-handle">
                        <?php echo isset($names[$node['id']])?$names[$node['id']]:$node['id']; ?>
                    </div>
                    <?php if(isset($node['children'])) { 
                    ?>
                        <ol class="dd-list">
                            <?php foreach( $node['children'] as $child ) {
                                $this->output_nestable_li($child, $names); 
                            } ?>
                        </ol>
                    <?php } ?>
                </li>
            <?php 
        }
    }

    public function output_nestable($tree, $names, $id){ ?>
        <div class="dd" id="<?php echo $id; ?>">
            <?php if( !empty($tree) ){ 
                echo '<ol class="dd-list">';
                foreach ($tree as $node) {
                    $this->output_nestable_li($node, $names);
                } 
                echo '</ol>';
            } else {
                echo '<div class="dd-empty"></div>';
            } ?>
        </div>
    <?php }
       
    /**
     * Used by the WC_Settings to output the price tiers setting html
     *
     */
    public function generate_tier_tree_html( $key, $data ) {
        $_procedure = $this->_class."GENERATE_TIER_TREE_HTML: ";
        if(LASERCOMMERCE_DEBUG) error_log($_procedure);

        $field    = $this->plugin_id . $this->id . '_' . $key;

        $defaults = array(
            'title'             => '',
            'disabled'          => false,
            'class'             => '',
            'css'               => '',
            'placeholder'       => '',
            'type'              => 'text',
            'desc_tip'          => false,
            'description'       => '',
            'custom_attributes' => array()
        );

        $data = wp_parse_args( $data, $defaults );

        global $Lasercommerce_Tier_Tree;

        $names = $Lasercommerce_Tier_Tree->getNames();
        $availableTiers = array_keys($names);
        $usedTiers = $Lasercommerce_Tier_Tree->getTiers();
        $tree = $Lasercommerce_Tier_Tree->getTierTree();
        if(!$usedTiers){
            $unusedRoles = $availableTiers;
        } else {
            $unusedRoles = array_diff($availableTiers, $usedTiers);
        }
        // IF(WP_DEBUG) error_log("-> availableRoles: ".serialize($availableTiers));
        // IF(WP_DEBUG) error_log("-> tree: ".          serialize($tree));
        // IF(WP_DEBUG) error_log("-> usedRoles: ".     serialize($usedTiers));
        // IF(WP_DEBUG) error_log("-> unusedRoles: ".   serialize($unusedRoles));
        // IF(WP_DEBUG) error_log("-> names: ".         serialize($names));
        // IF(WP_DEBUG) error_log("-> field: ".         serialize($field));

        ob_start();
?>
<tr valign="top">
    <th scope="row" class="titledesc">
        <label for="<?php echo esc_attr( $field ) ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
        <?php echo $this->get_tooltip_html( $data ); ?>
    </th>
    <td class="forminp">
        <fieldset>
            <legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
            <?php echo $this->get_description_html( $data ); ?>

            <div class="dd" id="nestable-unused">
                <ol class="dd-list">
                    <?php foreach( $unusedRoles as $role ) { ?>
                        <li class="dd-item" data-id="<?php echo $role; ?>">
                            <div class="dd-handle">
                                <?php echo isset($names[$role])?$names[$role]:$role; ?>
                            </div>
                        </li>
                    <?php } ?>
                </ol>
            </div>
            <input type="" name="<?php echo esc_attr( $field['id'] ); ?>" class="lc_admin_tier_tree" id="<?php echo esc_attr( $field['id'] ); ?>"  style="width:100%; max-width:600px" value="">    
        </fieldset>
    </td>
</tr>
<?php
        return ob_get_clean();
    }
    /**
     * used by WC_Settings API to save the price tiers field from $POST data
     * to the options in the database
     *
     * @param array $field The array specifying the field being saved
     */
    public function tier_tree_save( $field ){
        // if(WP_DEBUG) error_log('updating price tier! field: '.serialize($field).' POST '.serialize($_POST));
        if( isset( $_POST[ $field['id']]) ){
            // if(WP_DEBUG) error_log('updating option '.$field['id'].' as '.$_POST[$field['id']]);
            update_option( $field['id'], $_POST[$field['id']]);
        }
    }
    
    /** (Unfinished) Outputs a donate box section in the admin interface
     */
    public function donationBoxSection(){
        //todo: this
    }

    /**
     * Used by WC_Settings API to save all of the fields in the current section
      */
    public function save() {
        global $current_section;
        
        if( !$current_section ) {
            $settings = $this->get_settings();
            
            WC_Admin_Settings::save_fields( $settings );
        }
    }
}
?>