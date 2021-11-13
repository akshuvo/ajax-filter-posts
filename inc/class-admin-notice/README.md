![Screenshot](https://raw.githubusercontent.com/akshuvo/class-admin-notice/master/class-admin-notice.png)
![Screenshot](https://raw.githubusercontent.com/akshuvo/class-admin-notice/master/class-admin-notice.png)

    /**
     * Plugin Name:  AddonMaster Admin Notice
     * Plugin URI:   https://akhtarujjaman.com
     * Author:       Akhtarujjaman Shuvo
     * Author URI:   https://akhtarujjaman.com
     * Version: 	  1.0.0
     * Description:  AddonMaster Admin Notice is a Simple plugin that can be used for make dynamic admin notices for wordpress
     * License:      GPL2
     * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
     **/


# Dynamic Admin Notice class for WordPress
This  is a simple Plugin/Class file that can be used for make dynamic admin notices for wordpress.

## How to use?
### Code Sample:

    function my_admin_notices($args){
    	$args[] = array(
    		'id' => "samplenotice",
    		'text' => "We hope you're enjoying this plugin! Could you please give a 5-star rating on WordPress to inspire us?",
    		'logo' => "https://ps.w.org/bs-shortcode-ultimate/assets/icon-256x256.png",
    		'border_color' => "#000",
    		'is_dismissable' => "true",
    		'dismiss_text' => "Dismiss",
    		'buttons' => array(
    			array(
    				'text' => "Ok, you deserve it!",
    				'link' => "#link",
    				'icon' => "dashicons dashicons-external",
    				'class' => "button-primary",
    			),
    			array(
    				'text' => "Maybe Later?",
    				'link' => "#link",
    				'icon' => "dashicons dashicons-external",
    				'class' => "button-secondary",
    			),
    		)
    
    	);
    
    	return $args;
    }
    add_filter( 'addonmaster_admin_notice', 'my_admin_notices' );

## Parameters
`id`: ( String ) This is required and should be  unique.

`text`: ( String ) Here you can write which text you want to show. Html is supported without "p" tag

`logo` : ( URL ) The url of the logo which you want to show

`border_color`: ( Hex format | #333 | #666666 ) For coloring the borders

`is_dismissable`: ( true | false ) Dismissable or not

`dismiss_text`: ( String ) Text for Dismiss button. default: Dismiss

`buttons`: ( array ) Lists of action buttons in array

   > `text`: ( String ) Text of the button
   
   > `link`: ( String ) Link of the button
   
   > `target`: ( String | _blank ) Link target. default: empty
   
   > `icon`: ( String ) Classes of icon. ex: "dashicons dashicons-external"
   
   > `class`: ( String ) Class will be added to anchor tag
