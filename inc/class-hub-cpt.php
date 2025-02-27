<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Hub_CPT {

    public static function register_slot_cpt() {
        $labels = array(
            'name'               => __( 'Slots', 'hub-plugin' ),
            'singular_name'      => __( 'Slot', 'hub-plugin' ),
            'add_new'            => __( 'Add New Slot', 'hub-plugin' ),
            'add_new_item'       => __( 'Add New Slot', 'hub-plugin' ),
            'edit_item'          => __( 'Edit Slot', 'hub-plugin' ),
            'new_item'           => __( 'New Slot', 'hub-plugin' ),
            'view_item'          => __( 'View Slot', 'hub-plugin' ),
            'search_items'       => __( 'Search Slots', 'hub-plugin' ),
            'not_found'          => __( 'No Slots found', 'hub-plugin' ),
            'not_found_in_trash' => __( 'No Slots found in Trash', 'hub-plugin' ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'has_archive'        => true,
            'menu_icon'          => 'dashicons-admin-post',
            'supports'           => array( 'title', 'editor', 'thumbnail' ),
            'capability_type'    => 'post',
            'rewrite'            => array( 'slug' => 'slots' ),
            'show_in_rest'       => true,
        );

        register_post_type( 'slot', $args );

        // Register custom taxonomies for the slot post type
        self::register_slot_taxonomies();
    }

    /**
     * Registers two custom taxonomies:
     * 1. slot_category (hierarchical)
     * 2. slot_owner (non-hierarchical)
     */
    private static function register_slot_taxonomies() {
        // 1) Slot Category (hierarchical)
        $cat_labels = array(
            'name'              => __( 'Slot Categories', 'hub-plugin' ),
            'singular_name'     => __( 'Slot Category', 'hub-plugin' ),
            'search_items'      => __( 'Search Slot Categories', 'hub-plugin' ),
            'all_items'         => __( 'All Slot Categories', 'hub-plugin' ),
            'parent_item'       => __( 'Parent Slot Category', 'hub-plugin' ),
            'parent_item_colon' => __( 'Parent Slot Category:', 'hub-plugin' ),
            'edit_item'         => __( 'Edit Slot Category', 'hub-plugin' ),
            'update_item'       => __( 'Update Slot Category', 'hub-plugin' ),
            'add_new_item'      => __( 'Add New Slot Category', 'hub-plugin' ),
            'new_item_name'     => __( 'New Slot Category Name', 'hub-plugin' ),
            'menu_name'         => __( 'Slot Categories', 'hub-plugin' ),
        );
        $cat_args = array(
            'hierarchical'      => true,
            'labels'            => $cat_labels,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_rest'      => true,
            'rewrite'           => array( 'slug' => 'slot-category' ),
        );
        register_taxonomy( 'slot_category', array( 'slot' ), $cat_args );

        // 2) Slot Owner (non-hierarchical, like tags)
        $owner_labels = array(
            'name'                       => __( 'Slot Owners', 'hub-plugin' ),
            'singular_name'              => __( 'Slot Owner', 'hub-plugin' ),
            'search_items'               => __( 'Search Slot Owners', 'hub-plugin' ),
            'popular_items'              => __( 'Popular Slot Owners', 'hub-plugin' ),
            'all_items'                  => __( 'All Slot Owners', 'hub-plugin' ),
            'edit_item'                  => __( 'Edit Slot Owner', 'hub-plugin' ),
            'update_item'                => __( 'Update Slot Owner', 'hub-plugin' ),
            'add_new_item'               => __( 'Add New Slot Owner', 'hub-plugin' ),
            'new_item_name'              => __( 'New Slot Owner Name', 'hub-plugin' ),
            'separate_items_with_commas' => __( 'Separate slot owners with commas', 'hub-plugin' ),
            'add_or_remove_items'        => __( 'Add or remove slot owners', 'hub-plugin' ),
            'choose_from_most_used'      => __( 'Choose from the most used slot owners', 'hub-plugin' ),
            'not_found'                  => __( 'No slot owners found.', 'hub-plugin' ),
            'menu_name'                  => __( 'Slot Owners', 'hub-plugin' ),
        );
        $owner_args = array(
            'hierarchical'          => false,
            'labels'                => $owner_labels,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'show_in_rest'          => true,
            'rewrite'               => array( 'slug' => 'slot-owner' ),
        );
        register_taxonomy( 'slot_owner', array( 'slot' ), $owner_args );
    }
}
