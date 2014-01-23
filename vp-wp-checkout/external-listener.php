<?php
// Require this file to get the database settings
require_once '../../../wp-config.php';

// We need these values into $_REQUEST var
$_REQUEST['MerchantIdentifier'] = $_POST["MerchantIdentifier"];
$_REQUEST['TransactionIdentifier'] = $_POST["TransactionIdentifier"];

function getCallbackService() {
    require_once 'integration/VirtualPiggy/Data/dtos.php';
    require_once 'integration/VirtualPiggy/Services/Interfaces/ICallbackService.php';
    require_once 'integration/VirtualPiggy/Services/Implementations/VirtualPiggyCallbackService.php';

    $service = new VirtualPiggyCallbackService();

    return $service->GetCallbackTransactionStatus();
}

function createConnection() {
    $connection = mysql_connect(DB_HOST, DB_USER, DB_PASSWORD);
    mysql_select_db(DB_NAME, $connection);

    return $connection;
}

function getOrderId($transaction_id, $db_connection) {
    $query = 'select orderId from wp_vp_transactions where transactionId="'.$transaction_id.'"';
    $result = mysql_query($query, $db_connection);

    return mysql_result($result,0);
}

function approveOrder($id, $db_connection) {
    $query = 'update wp_term_relationships set term_taxonomy_id=9 where object_id='.$id;
    mysql_query($query, $db_connection);
}

function rejectOrder($id, $db_connection) {
    $query = 'update wp_term_relationships set term_taxonomy_id=12 where object_id='.$id;
    mysql_query($query, $db_connection);
}

function addApproveComment($id, $db_connection) {
    $query = 'insert into wp_comments (comment_post_ID, comment_author, comment_date, comment_date_gmt, comment_content, comment_karma, comment_approved, comment_agent, comment_type, comment_parent, user_id) values ('.$id.', "WooCommerce", "'.date("Y-m-d h:i:s").'", "'.date("Y-m-d h:i:s").'", "Order changed from pending to processing", 0, "1", "WooCommerce", "order_note", 0, 0)';
    mysql_query($query, $db_connection);
}

function addRejectComment($id, $db_connection) {
    $query = 'insert into wp_comments (comment_post_ID, comment_author, comment_date, comment_date_gmt, comment_content, comment_karma, comment_approved, comment_agent, comment_type, comment_parent, user_id) values ('.$id.', "WooCommerce", "'.date("Y-m-d h:i:s").'", "'.date("Y-m-d h:i:s").'", "Order changed from pending to cancelled", 0, "1", "WooCommerce", "order_note", 0, 0)';
    mysql_query($query, $db_connection);
}

function approveOrRejectOrder($data) {
    $db_connection = createConnection();
    $order_id = getOrderId($data->TransactionIdentifier, $db_connection);

    if($data->Status == "Processed") {
        approveOrder($order_id, $db_connection);
        addApproveComment($order_id, $db_connection);
    } else {
        rejectOrder($order_id, $db_connection);
        addRejectComment($order_id, $db_connection);
    }
}

if(isset($_REQUEST['MerchantIdentifier']) || isset($_REQUEST['TransactionIdentifier'])) {
    require_once 'integration/VirtualPiggyWPHelper.php';

    $data = getCallbackService();

    @VirtualPiggyWPHelper::log($data, 'ASYNC');

    approveOrRejectOrder($data);

    die('Virtual Piggy (c)' . PHP_EOL);
} else {
    die ('Sorry, but you do not have permission to see this page.');
}
?>