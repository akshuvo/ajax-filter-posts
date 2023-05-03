<?php

// Template review array
$templates_preivew = [
    [
        'name'      => __( 'Template 1', 'gridmaster' ),
        'id'        => 'template-1',
        'thumbnail' => GRIDMASTER_URL . '/admin/assets/tmpl-imgs/template-1.png',
        'pagination_id' => '',
        'filter_id' => '',
        'is_pro'    => 'yes',
        'preview_link'  => '',
        'query'     => ''
    ],
    [
        'name'      => __( 'Template 2', 'gridmaster' ),
        'id'        => 'template-2',
        'thumbnail' => GRIDMASTER_URL . '/admin/assets/tmpl-imgs/template-1.png',
        'pagination_id' => '',
        'filter_id' => '',
        'is_pro'    => 'yes',
        'preview_link'  => '',
        'query'     => ''
    ]
];

foreach( $templates_preivew as $template ) : ?>
<div class="gridmaster-wrap cogm-welcome-ntainer">
    <div class="container-fluid pt-0 pt-3 gm-container">
        <img src="<?php echo $template['thumbnail']; ?>" alt="xx">
    </div>
</div>
<?php endforeach;  ?>
