<?php
// Deprecated legacy file redirected to consolidated track-request.php
$tracking = $_GET['tracking_number'] ?? $_POST['tracking_number'] ?? '';
if ($tracking) {
    header('Location: track-request.php?tracking_number=' . urlencode($tracking));
} else {
    header('Location: track-request.php');
}
exit();