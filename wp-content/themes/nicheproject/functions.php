<?php

function twentythirteen_child_styles() {
    wp_deregister_style( 'twentythirteen-style');
    wp_register_style('twentythirteen-style', get_template_directory_uri(). '/style.css');
    wp_enqueue_style('twentythirteen-style', get_template_directory_uri(). '/style.css');
    wp_enqueue_style( 'childtheme-style', get_stylesheet_directory_uri().'/style.css',
        array('twentythirteen-style') );
}
add_action( 'wp_enqueue_scripts', 'twentythirteen_child_styles' );

function register_my_menus() {
    register_nav_menus(
        array(
            'policy-menu' => __( 'Policy Menu' ),
        )
    );
}
add_action( 'init', 'register_my_menus' );