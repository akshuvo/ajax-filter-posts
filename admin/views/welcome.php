<div class="gridmaster-wrap gm-welcome-container">
    <div class="container-fluid pt-0 pt-3 gm-container">
        <div class="row">
            <div class="col-md-8">
                <div class="row">
                    <div class="col-md-12">
                        <div class="gm-welcome-details">
                            <div class="gm-welcome-details-head">
                                <h2 class="gm-title-lg"> <?php echo esc_html('Welcome to GridMaster', 'gridmaster'); ?>  <span> <?php echo esc_html('Version', 'gridmaster'); ?> 1.0.0 </span> </h2>
                                <p><?php echo esc_html('We are the most powerful Post Filter in Wordpress', 'gridmaster'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="gm-welcome-sidebar">

                    <div class="gm-card">
                        <div class="gm-card-details">
                            <div class="gm-icon">
                                <span class="dashicons dashicons-editor-help"></span>
                            </div>
                            <div class="gm-card-containt">
                                <h2 class="gm-title-md"><?php echo esc_html('Support And Feedback', 'gridmaster'); ?></h2>
                                <p><?php echo esc_html('Do you feel like you need expert advice? Create a support ticket,  Create a support ticket, and we will be delighted to assist you :)', 'gridmaster'); ?>  </p>
                                <a class="btn gm-bttn btn-primary" href="#"> <?php echo esc_html('Get Support', 'gridmaster'); ?>  </a>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div> 
</div>


<style>
    .gm-welcome-container {
        overflow-y: scroll;
        height: calc(100vh - 175px);
    }
    .gm-welcome-details {
        background: #30057B;
        padding: 24px;
        display: inline-block;
        width: 100%;
        border-radius: 8px;
    }
    .gm-welcome-details :is( p, h1, h2, h3, h4, h5, h6 ){
        color: #fff;
        line-height: 1.3;
        margin: 16px 0px;
    }
    .gm-title-lg{
        font-size: 32px;
    }
    .gm-welcome-details-head h2{
        display: flex;
        align-items: stat;
        justify-content: space-between;
    }
    .gm-welcome-details-head span{
        font-size: 18px;
    }
    .gm-welcome-details p{
        font-size: 19px;
    }
    .gm-bttn {
        background: #fff;
        border: 1px solid;
        display: inline-block;
        padding: 10px 16px;
        border-radius: 6px;
        text-decoration: none;
    }
    .gm-card {
        box-shadow: 0px 13px 30px #E0E8EE;
        border-radius: 8px;
        padding: 30px;
        margin-bottom: 20px;
    }
    .gm-title-md{
        font-size: 24px;
        font-weight: 700;
    }
    .gm-icon span {
        font-size: 62px;
        color: #30057b;
    }

    .gm-icon {
        display: inline-block;
        margin-bottom: 24px;
    }
</style>