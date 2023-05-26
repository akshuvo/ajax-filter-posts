<div class="gridmaster-wrap gm-welcome-container">
    <div class="container-fluid pt-0 pt-3 gm-container">
        <div class="row">
            <div class="col-md-12">
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
        </div>
        <div class="row pt-3">
            <div class="col-md-8">
                <div class="row">
                    <div class="col-md-12">
                        <div class="gm-card gm-features-free-pro">
                            <h2 class="gm-title-lg"> <?php echo esc_html('GridMaster Features', 'gridmaster'); ?> </h2>
                            <table>
                                <tr>
                                    <th>Features</th>
                                    <th>GridMaster Free</th>
                                    <th>GridMaster Pro</th>
                                </tr>
                                <tr>
                                    <td>Feature 1</td>
                                    <td><span class="dashicons dashicons-yes"></span> </td>
                                    <td><span class="dashicons dashicons-no"></span></td>
                                </tr> <tr>
                                    <td>Feature 1</td>
                                    <td><span class="dashicons dashicons-yes"></span> </td>
                                    <td><span class="dashicons dashicons-no"></span></td>
                                </tr> <tr>
                                    <td>Feature 1</td>
                                    <td><span class="dashicons dashicons-yes"></span> </td>
                                    <td><span class="dashicons dashicons-no"></span></td>
                                </tr> <tr>
                                    <td>Feature 1</td>
                                    <td><span class="dashicons dashicons-yes"></span> </td>
                                    <td><span class="dashicons dashicons-no"></span></td>
                                </tr> <tr>
                                    <td>Feature 1</td>
                                    <td><span class="dashicons dashicons-yes"></span> </td>
                                    <td><span class="dashicons dashicons-no"></span></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="gm-update-pro">
                            <a class="btn gm-bttn btn-primary" href="#"> <?php echo esc_html('UPGRADE TO PRO!', 'gridmaster'); ?>  </a>
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
    .gm-card-containt p{
        font-size: 17px;
    }
    .gm-welcome-container {
        overflow-y: scroll;
        height: calc(100vh - 175px);
    }
    .gm-welcome-details {
        background: linear-gradient(90deg, rgba(31, 17, 206, 1) 0%, rgba(153, 29, 107, 1) 100%);
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
    .gm-features-list li{
        font-size: 16px;
    }
    .gm-update-pro {
        text-align: right;
    }
    .gm-update-pro a {
        background: linear-gradient(90deg, rgba(31, 17, 206, 1) 0%, rgba(153, 29, 107, 1) 100%);
        color: #fff;
        font-weight: 600;
        font-size: 18px;
        padding: 14px 18px;
    }


    .gm-features-free-pro table {
        border-collapse: collapse;
        width: 100%;
    }

    .gm-features-free-pro th,
    .gm-features-free-pro td {
        padding: 14px 12px;
        text-align: left;
        vertical-align: middle;
        border: 1px solid #ddd;
        font-size: 17px;
    }

    .gm-features-free-pro th {
        background-color: #f2f2f2;
        font-weight: bold;
        font-size: 19px;
    }

   .gm-features-free-pro tr:nth-child(even) {
        background-color: #f9f9f9;
    }

    .gm-features-free-pro tr:nth-child(odd) {
        background-color: #fff;
    }
    .gm-features-free-pro td:nth-child(1) {
        width: 30%;
    }

    .gm-features-free-pro td:nth-child(2), 
    .gm-features-free-pro td:nth-child(3) {
        width: 35%;
    }
    .gm-features-free-pro .dashicons-yes{
        color: #30057b;
    }
    .gm-features-free-pro .dashicons-no{
        color: red;
    }
    .gm-features-free-pro table span {
        font-size: 24px;
        display: inline;
    }
    .gm-features-free-pro .gm-title-lg {
        font-size: 32px;
        margin: 0;
        display: inline-block;
        margin-bottom: 40px;
    }

    /* .gm-card.gm-features-free-pro {
        display: flex;
        gap: 24px;
    } */

    /* .gm-features-list {
        flex: 1;
        border: 1px solid #ddd;
        padding: 30px;
        border-radius: 8px;
    }

    .gm-features-list li {
        font-size: 18px;
        padding: 8px 0px;
    }

    .gm-features-list.gm-pro {

    }
    .gm-pro :is( p, h1, h2, h3, h4, h5, h6, li ){
        color: #000;
    } */
</style>