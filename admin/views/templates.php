<?php

// Template review array
$templates_preivew = [
    [
        'name'      => __( 'Template 1', 'gridmaster' ),
        'id'        => 'template-1',
        'thumbnail' => GRIDMASTER_URL . '/admin/assets/tmpl-imgs/template-1.png',
        'pagination_id' => '',
        'filter_id' => '',
        'is_pro'    => 'no',
        'preview_link'  => '',
        'query'     => ''
    ],
    [
        'name'      => __( 'Template 2', 'gridmaster' ),
        'id'        => 'template-2',
        'thumbnail' => GRIDMASTER_URL . '/admin/assets/tmpl-imgs/template-1.png',
        'pagination_id' => '',
        'filter_id' => '',
        'is_pro'    => 'no',
        'preview_link'  => '',
        'query'     => ''
    ],
    [
        'name'      => __( 'Template 3', 'gridmaster' ),
        'id'        => 'template-2',
        'thumbnail' => GRIDMASTER_URL . '/admin/assets/tmpl-imgs/template-1.png',
        'pagination_id' => '',
        'filter_id' => '',
        'is_pro'    => 'no',
        'preview_link'  => '',
        'query'     => ''
    ],
    [
        'name'      => __( 'Template 4', 'gridmaster' ),
        'id'        => 'template-2',
        'thumbnail' => GRIDMASTER_URL . '/admin/assets/tmpl-imgs/template-1.png',
        'pagination_id' => '',
        'filter_id' => '',
        'is_pro'    => 'yes',
        'preview_link'  => '',
        'query'     => ''
    ],
    [
        'name'      => __( 'Template 5', 'gridmaster' ),
        'id'        => 'template-2',
        'thumbnail' => GRIDMASTER_URL . '/admin/assets/tmpl-imgs/template-1.png',
        'pagination_id' => '',
        'filter_id' => '',
        'is_pro'    => 'yes',
        'preview_link'  => '',
        'query'     => ''
    ]
]; ?>

<div class="gridmaster-wrap cogm-welcome-ntainer">
    <div class="container-fluid pt-0 pt-3 gm-container">
        <div class="row gridmaster-template-view">

        <?php foreach( $templates_preivew as $template ) : ?>
        <div class="col-md-4 mb-4">
            <div class="gridmaster-template-card">
                <div class="template-card-thumbnail">
                    <img src="<?php echo $template['thumbnail']; ?>" alt="">
                    <?php if( $template['is_pro'] == 'yes' ){ ?>
                        <div class="template-badge pro">Pro</div>
                    <?php } else{  ?>
                        
                    <?php } ?>    

                    <div class="template-card-info">
                        <div class="template-card-actions">
                            <a href="#" class="btn gm-bttn btn-primary">Preview</a>
                            <?php 
                                if( $template['is_pro'] == 'yes' ){ ?>
                                    <a href="#" class="btn gm-bttn btn-primary bttn-pro">Upgrade to pro!</a>
                                <?php  } else{  ?>
                                    <a href="#" class="btn gm-bttn btn-primary">Use This Template</a>
                            <?php } ?>
                        </div>
                        <div class="template-card-title">
                            <h3><?php echo $template['name']; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach;  ?>
        </div>
    </div>
</div>


<style>
.gm-bttn {
    background: #fff;
    border: 1px solid;
    display: inline-block;
    padding: 10px 16px;
    border-radius: 6px;
    text-decoration: none;
}
.gridmaster-template-view img {
    width: 100%;
    border-radius: 8px;
    min-height: 340px;
    object-fit: cover;
}
.gridmaster-template-card {
    border: 1px solid #ddd;
    border-radius: 8px;
    position: relative;
}
.template-card-info {
    position: absolute;
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: rgb(233 235 255 / 70%);
    top: 0;
    left: 0;
    z-index: 100;
    transition: all 200ms;
    opacity: 0;
}
.gridmaster-template-card:hover .template-card-info{
    opacity: 1;
    transition: all 200ms;
}
.template-badge.pro {
    background: #000;
    color: #fff;
    padding: 8px 32px;
    font-size: 16px;
    border-radius: 4px;
    position: absolute;
    top: 8px;
    right: 8px;
    z-index: 999;
} 
.template-card-title {
    position: absolute;
    bottom: 8px;
}
.template-card-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
    text-align: center;
}

.template-card-actions .gm-bttn {
    background: #413ec5;
    color: #fff;
    border-radius: 0px;
}
.template-card-actions .gm-bttn.bttn-pro {
    background: linear-gradient(90deg, rgba(31, 17, 206, 1) 0%, rgba(153, 29, 107, 1) 100%);
}

</style>