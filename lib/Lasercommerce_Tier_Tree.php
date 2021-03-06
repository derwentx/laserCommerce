<?php

include_once('Lasercommerce_Tier.php');
include_once('Lasercommerce_Abstract_Child.php');

/**
 * helper class for dealing with price tier tree
 */
class Lasercommerce_Tier_Tree extends Lasercommerce_Abstract_Child{
    public $_class = "LC_TT_";

    public static $rootID = 'default';

    private $decoded_trees = array();
    private $cached_visible_tiers = array();
    private $treeTiers;

    /**
     * Constructs the helper object
     */
    // public function __construct() {
    //     global $Lasercommerce_Plugin;
    //     $this->plugin = $Lasercommerce_Plugin;
    // }

    private static $instance;

    public static function init() {
        if ( self::$instance == null ) {
            self::$instance = new Lasercommerce_Tier_Tree();
        }
        parent::init();
    }

    public static function instance() {
        if ( self::$instance == null ) {
            self::init();
        }

        return self::$instance;
    }

    /**
     * Gets the tier tree in the form of an array of arrays
     *
     * @return array tier_tree The tree of price tiers
     */
    public function getTierTree($json_string = ''){
        $context = array_merge($this->defaultContext, array(
            'caller'=>$this->_class."GET_TIER_TREE",
            'args'=>"\$json_string=".serialize($json_string)
        ));
        if(LASERCOMMERCE_PRICING_DEBUG) $this->procedureStart('', $context);

        // if(LASERCOMMERCE_PRICING_DEBUG) $this->procedureDebug("tier_key_key".serialize(Lasercommerce_OptionsManager::TIER_KEY_KEY), $context);
        // if(LASERCOMMERCE_PRICING_DEBUG) $this->procedureDebug("tier_tree_key".serialize(Lasercommerce_OptionsManager::TIER_TREE_KEY), $context);


        if(!$json_string) $json_string = $this->get_option(Lasercommerce_OptionsManager::TIER_TREE_KEY);
        // if(LASERCOMMERCE_DEBUG) $this->procedureDebug("JSON string: $json_string", $context);

        if(isset($this->decoded_trees[$json_string])){
            $tierTree = $this->decoded_trees[$json_string];
            // if(LASERCOMMERCE_DEBUG) $this->procedureDebug("found cached: ".serialize($tierTree), $context);
        } else {
            $tierTree = json_decode($json_string, true);
            if ( !$tierTree ) {
                if(LASERCOMMERCE_DEBUG) $this->procedureDebug("could not decode", $context);
                $tierTree = array(); //array('id'=>'administrator'));
            }
            else {
                // if(LASERCOMMERCE_DEBUG) $this->procedureDebug("decoded: ".serialize($tierTree), $context);
            }
        }

        if(LASERCOMMERCE_PRICING_DEBUG) $this->procedureDebug("END", $context);

        return $tierTree;
    }

    /**
     * Used by getTreeTiers to geta flattened version of the Tree
     *
     * @param array $node an array containing the node to be flattened recursively
     * @return the tiers contained within $node
     */
    private function flattenTierTree($node = array()){
        $context = array_merge($this->defaultContext, array(
            'caller'=>$this->_class."FLATTEN_TREE_RECURSIVE",
            'args'=>"\$node=".serialize($node)
        ));
        if(LASERCOMMERCE_PRICING_DEBUG) $this->procedureStart('', $context);

        // if(LASERCOMMERCE_DEBUG) {
        //     $this->procedureDebug("node", $context);
        //     if(is_array($node)) foreach($node as $k => $v) $this->procedureDebug(" ($k, ".serialize($v).")", $context);
        // }

        if( !isset($node['id']) ) return array();

        $tiers = array();
        $tier = Lasercommerce_Tier::fromNode($node);
        if($tier){
            $tiers[] = $tier;
        }

        if( isset($node['children'] ) ){
            foreach( $node['children'] as $child ){
                // if(LASERCOMMERCE_DEBUG) $this->procedureDebug("child: ".serialize($child), $context);
                $result = $this->flattenTierTree($child);
                // if(LASERCOMMERCE_DEBUG) $this->procedureDebug("result: ".serialize($result), $context);
                $tiers = array_merge($tiers, $result);
            }
        }
        unset($node['children']);

        if(LASERCOMMERCE_PRICING_DEBUG) $this->procedureDebug("END", $context);
        return $tiers;
    }

    public function getTreeTiers(){
        $_procedure = $this->_class."GET_TIERS: ";

        global $lasercommerce_pricing_trace;
        $lasercommerce_pricing_trace_old = $lasercommerce_pricing_trace;
        $lasercommerce_pricing_trace .= $_procedure;
        // if(LASERCOMMERCE_PRICING_DEBUG) $this->procedureDebug("BEGIN", $context);

        if(isset($this->treeTiers)){
            $tiers = $this->treeTiers;
        } else {
            $tree = $this->getTierTree();
            $tiers = array();
            foreach( $tree as $node ){
                $tiers = array_merge($tiers, $this->flattenTierTree($node));
            }
        }
        $this->treeTiers = $tiers;

        // if(LASERCOMMERCE_PRICING_DEBUG) $this->procedureDebug("END", $context);
        $lasercommerce_pricing_trace = $lasercommerce_pricing_trace_old;

        return $tiers;
    }

    public function getTier($tierID){
        $tiers = $this->getTreeTiers();
        foreach ($tiers as $tier) {
            if(strtoupper($tierID) === strtoupper($tier->id)){
                return $tier;
            }
        }
    }

    public function getTierID($tier){
        if(is_string($tier)) $tier = $this->getTier($tier);
        return $tier->id;
    }

    public function getTierName($tier){
        if(is_string($tier)) $tier = $this->getTier($tier);
        return $tier->name;
    }

    public function getTierMajor($tier){
        if(is_string($tier)) $tier = $this->getTier($tier);
        return $tier->major;
    }

    public function getTierOmniscient($tier){
        if(is_string($tier)) $tier = $this->getTier($tier);
        return $tier->omniscient;
    }

    public function getWholesaleTier(){
        return $this->getTier('WN');
        //TODO: make this edit in admin
    }

    public function getTierIDs($tiers){
        $tierIDs = array();
        if(is_array($tiers)) foreach ($tiers as $tier) {
            $tierID = $this->getTierID($tier);
            if($tierID) $tierIDs[] = $tierID;
        }
        return $tierIDs;
    }

    public function getTiers($tierIDs){
        $tiers = array();
        if(is_array($tierIDs)) foreach ($tierIDs as $tierID) {
            $tier = $this->getTier($tierID);
            if($tier) $tiers[] = $tier;
        }
        return $tiers;
    }

    /**
     * Gets a list of all the tier IDs in the provided list of tiers.
     * if no list provided, get list of all ids in the Tree
     *
     * @return array tiers A list or tiers in the tree
     */
    public function getTreeTierIDs(){
        return $this->getTierIDs($this->getTreeTiers());
    }

    public function getActiveTiers(){
        trigger_error("Deprecated function called: getActiveTiers, use getTreeTiers instead", E_USER_NOTICE);;
    }

    private function filterRolesRecursive($node, $roles){
        trigger_error("Deprecated function called: filterRolesRecursive, use filterTiersRecursive instead", E_USER_NOTICE);;
    }

    /**
     * Gets the list of roles that are deemed omniscient - These roles can see all prices
     *
     * @return array omniscienct_roles an array containing all of the omoniscient roles
     */
    public function getOmniscientRoles(){
        trigger_error("Deprecated function called: getOmniscientRoles, use getOmniscientTiers instead", E_USER_NOTICE);;
    }

    public function getOmniscientTiers($tiers = array()){
        $context = array_merge($this->defaultContext, array(
            'caller'=>$this->_class."GET_OMNISCIENT_TIERS",
            'args'=>"\$tiers=".serialize($tiers)
        ));
        if(LASERCOMMERCE_PRICING_DEBUG) $this->procedureStart('', $context);

        // if(LASERCOMMERCE_DEBUG) $this->procedureDebug("tiers: ".serialize($tiers) , $context);

        if(!$tiers) $tiers = $this->getTreeTiers();

        $omniTiers = array();
        if(is_array($tiers)) foreach ($tiers as $tier) {
            if($this->getTierOmniscient($tier)){
                $omniTiers[] = $tier;
            }
        }
        return $omniTiers;
    }


    /**
     * Used by getVisibleTiers to recursively determine the price tiers visible
     * for a user that can view a given list of tiers
     *
     * @param array $node The node to be analysed
     * @param array $tiers The list of tiers visible to the user
     * @return array $visibleTiers The list of tiers visible to the user
     */
    private function filterTiersRecursive($node, $tiers){
        $context = array_merge($this->defaultContext, array(
            'caller'=>$this->_class."FILTER_TIERS_RECURSIVE",
            'args'=>"\$tiers=".serialize($tiers).", \$node=".serialize($node)
        ));
        if(LASERCOMMERCE_PRICING_DEBUG) $this->procedureStart('', $context);

        if( !isset($node['id']) ) { //is valid array
            return array();
        }

        $visibleTiers = array();
        if( isset($node['children'] ) ) { //has children
            foreach( $node['children'] as $child ){
                $visibleTiers = array_merge($visibleTiers, $this->filterTiersRecursive($child, $tiers));
            }
        }
        unset($node['children']);

        // IF(WP_DEBUG) error_log("recusrive for node: ".$node['id']);
        // IF(WP_DEBUG) error_log("-> good node: ".in_array( $node['id'], $tiers ));
        // IF(WP_DEBUG) error_log("-> good children: ".!empty($tiers));

        if(!empty($visibleTiers) or in_array( strtoupper($node['id']), $this->getTierIDs($tiers) )){
            if(LASERCOMMERCE_DEBUG) $this->procedureDebug("adding node: ".$node['id'] , $context);
            $tier = Lasercommerce_Tier::fromNode($node);
            if($tier){
                $visibleTiers[] = $tier;
            }

        }
        // IF(WP_DEBUG) error_log("-> tiers:  ".serialize($visibleTiers));

        if(LASERCOMMERCE_PRICING_DEBUG) $this->procedureDebug("END", $context);

        return $visibleTiers;
    }

    public function getAvailableTiers($user = Null){
        trigger_error("Deprecated function called: getAvailableTiers, use getVisibleTiers instead", E_USER_NOTICE);;
    }

    public function parseUserTierString($string){
        if($string and is_string($string)){
            return explode('|', $string);
        } else {
            return array();
        }
    }

    /**
     * Returns an array of tier objects that the user has directly been assigned
     */
    public function getUserTiers($user = Null){
        $context = array_merge($this->defaultContext, array(
            'caller'=>$this->_class."GET_USER_TIERS",
            'args'=>"\$user=".serialize($user)
        ));
        if(LASERCOMMERCE_PRICING_DEBUG) $this->procedureStart('', $context);

        global $Lasercommerce_Tiers_Override;
        if(isset($Lasercommerce_Tiers_Override) and is_array($Lasercommerce_Tiers_Override)){
            if(LASERCOMMERCE_PRICING_DEBUG) {
                $this->procedureDebug("Override is: ", $context);
                if(is_array($Lasercommerce_Tiers_Override)) foreach ($Lasercommerce_Tiers_Override as $value) {
                    $this->procedureDebug(" $value", $context);
                }
            }
            $tiers = $Lasercommerce_Tiers_Override;
        } else {
            if(is_numeric($user)){
                // $user = get_user_by('id', $user);
                $user_id = $user;
            } else {
                if(!$user){
                    $user = wp_get_current_user();
                }
                $user_id = $user->ID;
            }

            if(LASERCOMMERCE_DEBUG) $this->procedureDebug("user_id: ".serialize($user_id), $context);
            $tier_key = $this->get_option(Lasercommerce_OptionsManager::TIER_KEY_KEY);
            $user_tier_string = get_user_meta($user_id, $tier_key, true);
            $default_tier = $this->get_option(Lasercommerce_OptionsManager::DEFAULT_TIER_KEY);
            if(!$user_tier_string){
                if(LASERCOMMERCE_DEBUG) $this->procedureDebug("using default", $context);
                $user_tier_string = $default_tier;
            }
            if(LASERCOMMERCE_DEBUG) $this->procedureDebug("user_tier_string: ".serialize($user_tier_string), $context);
            $tierIDs = $this->parseUserTierString($user_tier_string);
            $tiers = $this->getTiers($tierIDs);
        }
        if(LASERCOMMERCE_DEBUG) $this->procedureDebug("returning user tiers: ".serialize($tiers), $context);
        return $tiers;
    }

    public function serializeTiers($tiers = array()){
        return implode("|", $this->getTierIDs($tiers));
    }

    public function serializeUserTiers() {
        $context = array_merge($this->defaultContext, array(
            'caller'=>$this->_class."SERIALIZE_USER_TIERS",
        ));
        if(LASERCOMMERCE_PRICING_DEBUG) $this->procedureStart('', $context);

        $tierString = $this->serializeTiers($this->getUserTiers());
        if(LASERCOMMERCE_PRICING_DEBUG) $this->procedureDebug("tier string: ".serialize($tierString), $context);
        return $tierString;
    }

    /**
     * Gets a list of the price tiers available to a user
     *
     * @param array $user a user or userID
     * @return array $available_tiers the list of price tiers available to the user
     */
    public function getVisibleTiers($user = Null){
        $_procedure = $this->_class."GET_VISIBLE_TIERS: ";

        global $lasercommerce_pricing_trace;
        $lasercommerce_pricing_trace_old = $lasercommerce_pricing_trace;
        $lasercommerce_pricing_trace .= $_procedure;
        // if(LASERCOMMERCE_PRICING_DEBUG) $this->procedureDebug("BEGIN", $context);

        $tiers = $this->getUserTiers($user);
        if(empty($tiers)) {
            $value = array();
        } else {
            if($this->getOmniscientTiers($tiers)){
                $tiers = $this->getTreeTiers();
            }

            $tier_flat = $this->serializeTiers($tiers);
            // if(LASERCOMMERCE_DEBUG) $this->procedureDebug("tier_flat: ".serialize($tier_flat), $context);
            if(isset($this->cached_visible_tiers[$tier_flat])){
                $visibleTiers = $this->cached_visible_tiers[$tier_flat];
            } else {
                $tree = $this->getTierTree();
                $visibleTiers = array();
                foreach( $tree as $node ){
                    $visibleTiers = array_merge($visibleTiers, $this->filterTiersRecursive($node, $tiers));
                }
                $this->cached_visible_tiers[$tier_flat] = $visibleTiers;
            }
            $value = array_reverse($visibleTiers);
        }

        // if(LASERCOMMERCE_DEBUG) $this->procedureDebug("visibleTiers: ".serialize($visibleTiers), $context);

        // if(LASERCOMMERCE_PRICING_DEBUG) $this->procedureDebug("END", $context);
        $lasercommerce_pricing_trace = $lasercommerce_pricing_trace_old;

        //is this necessary any more??
        return $value;
    }

    public function getVisibleTierIDs($user = null){
        $visibleTiers = $this->getVisibleTiers($user);
        return $this->getTierIDs($visibleTiers);
    }

    public function serializeVisibleTiers(){
        $context = array_merge($this->defaultContext, array(
            'caller'=>$this->_class."SERIALIZE_VISIBLE_TIERS",
        ));
        if(LASERCOMMERCE_PRICING_DEBUG) $this->procedureStart('', $context);

        $tierString = $this->serializeTiers($this->getVisibleTiers());
        if(LASERCOMMERCE_PRICING_DEBUG) $this->procedureDebug("tier string: ".serialize($tierString), $context);
        return $tierString;
    }

    /**
     *  Check if ID is in visible tiers
     */
    public function tierIDVisible($tierID, $user = null){
        $needle = strtolower($tierID);
        $visibleTiers = $this->getVisibleTiers($user);
        foreach ($visibleTiers as $tier){
            $haystack = strtolower($this->getTierID($tier));
            if(strpos($haystack, $needle) !== false){
                return true;
            }
        }
        return false;
    }

    /**
     * Check if tier is visible to user
     */
    public function tierVisible($tier, $user = null){
        $tierID = $this->getTierID($tier);
        return $this->tierIDVisible($tierID, $user);
    }

    /**
     * Check if name is contained in any visible tiers
     */
    public function tierNameVisible($tierName, $user = null){
        $needle = strtolower($tierName);
        $visibleTiers = $this->getVisibleTiers($user);
        foreach ($visibleTiers as $tier){
            $haystack = strtolower($this->getTierName($tier));
            if(strpos($haystack, $needle) !== false){
                return true;
            }
        }
        return false;
    }

    public function getAncestors($roles){
        trigger_error("Deprecated function called: getAncestors. Not used.", E_USER_NOTICE);;
    }


    public function getMajorTiers($tiers = null){
        if(!$tiers) $tiers = $this->getTreeTiers();
        $majorTiers = array();
        if(is_array($tiers)) foreach ($tiers as $tier) {
            if(is_string($tier)) $tier = $this->getTier($tier);
            if($tier->major){
                $majorTiers[] = $tier;
            }
        }
        return $majorTiers;
    }

    public function serializeMajorTiers($tiers = null){
        $context = array_merge($this->defaultContext, array(
            'caller'=>$this->_class."SERIALIZE_MAJOR_TIERS",
        ));
        if(LASERCOMMERCE_PRICING_DEBUG) $this->procedureStart('', $context);

        $tierString = $this->serializeTiers($this->getMajorTiers());
        if(LASERCOMMERCE_PRICING_DEBUG) $this->procedureDebug("tier string: ".serialize($tierString), $context);
        return $tierString;
    }



    /**
     * Gets the postID of a given simple or variable product
     *
     * @param WC_Product $product the product to be analysed
     * @return integer $postID The postID of the simple or variable product
     */
    public function getProductPostID( $product ){
        trigger_error("Deprecated function called: getProductPostID.", E_USER_NOTICE);;
    }

    /**
     * Gets the mapping of roles to human readable names
     *
     * @return array $names the mapping of roles to human readable names
     */
    public function getNames( ){
        // $defaults = array(
        //     // '' => 'Public',
        // );

        // global $wp_roles;
        // if ( ! isset( $wp_roles ) )
        //     $wp_roles = new WP_Roles();
        // $names = $wp_roles->get_names();
        // return array_merge($defaults, $names);
        trigger_error("Deprecated function called: getNames.", E_USER_NOTICE);;

    }
}
